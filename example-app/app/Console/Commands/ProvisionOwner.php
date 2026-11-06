<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\TeamName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProvisionOwner extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'stockrage:provision-owner';

    protected $description = 'Provision a new unverified owner and initial store';

    public function handle(CreateTeam $createTeam): int
    {
        $input = ['name' => $this->ask('Owner name'), 'email' => strtolower(trim((string) $this->ask('Email address'))), 'store' => $this->ask('Store name'), 'password' => $this->secret('Password'), 'password_confirmation' => $this->secret('Confirm password')];
        if (User::whereRaw('LOWER(email) = ?', [$input['email']])->exists()) {
            $this->error('An account with this email already exists. No ownership was changed.');

            return self::FAILURE;
        }
        $validator = Validator::make($input, [...$this->profileRules(), 'password' => $this->passwordRules(), 'store' => ['required', 'string', 'max:255', new TeamName]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $user = DB::transaction(function () use ($input, $createTeam) {
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
            $createTeam->handle($user, $input['store'], isPersonal: true);

            return $user;
        });
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
            $this->error('Account created but verification email failed. Fix outgoing email, then sign in and resend verification. Do not provision again.');

            return self::FAILURE;
        }
        $this->info('Owner and store created. Sign in and verify your email before store access.');

        return self::SUCCESS;
    }
}
