<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosActor
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-POS-Actor-Id') !== (string) $request->user()->id) {
            return response()->json(['error' => ['code' => 'session_changed', 'message' => 'Your signed-in account changed. Sign in as the original owner to recover this sale.', 'fieldErrors' => (object) [], 'issues' => []]], 409);
        }
        $store = Team::where('slug', $request->route('current_team'))->firstOrFail();
        $response = $next($request);
        $response->headers->set('X-POS-Actor-Id', (string) $request->user()->id);
        $response->headers->set('X-POS-Store-Id', (string) $store->id);

        return $response;
    }
}
