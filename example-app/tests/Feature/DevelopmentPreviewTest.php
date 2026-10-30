<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureLocalEnvironment;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DevelopmentPreviewTest extends TestCase
{
    public function test_cached_route_guard_denies_nonlocal(): void
    {
        $this->expectException(HttpException::class);
        app(EnsureLocalEnvironment::class)->handle(Request::create('/_dev/dashboard-reference'), fn () => response('unsafe'));
    }

    public function test_development_routes_are_absent(): void
    {
        $this->get('/_dev/dashboard-reference')->assertNotFound();
        $this->get('/_dev/example/pos-shell')->assertNotFound();
    }
}
