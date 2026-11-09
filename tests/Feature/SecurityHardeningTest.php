<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Guards the request-level protections that the test environment otherwise hides (CSRF is skipped under PHPUnit). */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_exceptions_point_at_real_routes_and_cover_the_payment_webhook(): void
    {
        $excluded = app(PreventRequestForgery::class)->getExcludedPaths();
        $webhook = ltrim(route('webhooks.paypal', [], false), '/');

        $this->assertContains($webhook, $excluded, 'The PayPal webhook must be exempt from CSRF or every notification is rejected with 419.');

        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();
        foreach ($excluded as $path) {
            $this->assertContains($path, $uris, "CSRF exception «{$path}» does not match any route.");
        }
    }

    public function test_content_security_policy_has_no_eval_and_allows_the_paypal_redirect(): void
    {
        $csp = (string) $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com", $csp);
    }

    public function test_public_write_endpoints_are_rate_limited(): void
    {
        $expected = [
            'assess.project' => 'throttle:events',
            'assess.submit' => 'throttle:events',
            'academy.lesson.lab' => 'throttle:events',
            'assets.print' => 'throttle:events',
            'password.email' => 'throttle:auth',
            'password.store' => 'throttle:auth',
        ];

        foreach ($expected as $name => $throttle) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route {$name} is missing.");
            $this->assertContains($throttle, $route->gatherMiddleware(), "Route {$name} must use {$throttle}.");
        }

        // No public POST route may be left without a limiter.
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('POST', $route->methods(), true)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            if (in_array('auth', $middleware, true) || collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:'))) {
                continue;
            }
            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')),
                'Public POST route «'.$route->uri().'» has no throttle.'
            );
        }
    }
}
