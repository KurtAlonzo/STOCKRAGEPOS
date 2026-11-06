<?php

namespace Tests\Feature\Pos;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActorScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_or_changed_actor_is_rejected_before_processing(): void
    {
        $u = User::factory()->create();
        $url = '/'.$u->currentTeam->slug.'/pos';
        $this->actingAs($u)->postJson($url.'/quotes', [])->assertStatus(409)->assertJsonPath('error.code', 'session_changed');
        $this->withHeader('X-POS-Actor-Id', '999')->postJson($url.'/draft-catalog', ['lines' => []])->assertStatus(409);
        $this->withHeader('X-POS-Actor-Id', (string) $u->id)->postJson($url.'/draft-catalog', ['lines' => []])->assertOk()->assertHeader('X-POS-Store-Id', (string) $u->current_team_id);
    }
}
