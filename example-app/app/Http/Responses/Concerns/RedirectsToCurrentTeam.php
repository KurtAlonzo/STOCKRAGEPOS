<?php

namespace App\Http\Responses\Concerns;

use App\Actions\Teams\ResolveOwnedTeam;
use Illuminate\Http\Request;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $team = app(ResolveOwnedTeam::class)->handle($request->user());

        return $team ? '/'.$team->slug.$redirect : route('no-store-access');
    }
}
