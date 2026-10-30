<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;

class ResolveOwnedTeam
{
    public function handle(User $user): ?Team
    {
        $team = $user->ownedTeams()->where('teams.id', $user->current_team_id)->first() ?? $user->ownedTeams()->orderBy('teams.id')->first();
        if ($team && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

return $team;
    }
}
