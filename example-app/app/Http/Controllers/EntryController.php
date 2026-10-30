<?php

namespace App\Http\Controllers;

use App\Actions\Teams\ResolveOwnedTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntryController
{
    public function __invoke(Request $request, ResolveOwnedTeam $resolve): RedirectResponse
    {
        if (! $request->user()) {
            return to_route('login');
        } if (! $request->user()->hasVerifiedEmail()) {
            return to_route('verification.notice');
        } $team = $resolve->handle($request->user());

        return $team ? to_route('dashboard', ['current_team' => $team->slug]) : to_route('no-store-access');
    }
}
