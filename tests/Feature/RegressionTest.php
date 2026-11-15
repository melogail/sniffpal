<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

/*
 * Regression: bugs that were fixed once and are documented in .ai/rules and
 * bootstrap/app.php but had no test pinning them. Run with
 * `composer test:regression`; the full suite is the regression suite proper.
 */
pest()->group('regression');

beforeEach(function () {
    $this->withoutVite();
});

test('the home page sends no Link header (4 KB preload header 502ed behind nginx)', function () {
    $this->get(route('home'))->assertOk()->assertHeaderMissing('Link');
});

test('a deactivated account signs out after auth and before the verified gate', function () {
    $stack    = routeMiddleware(app('router')->getRoutes()->getByName('pets.store'));

    $position = fn (string $class): int|false => collect($stack)->search(fn (string $entry): bool => str_starts_with($entry, $class));

    expect($position(Authenticate::class))->toBeLessThan($position(EnsureAccountIsActive::class))
        ->and($position(EnsureAccountIsActive::class))->toBeLessThan($position(EnsureEmailIsVerified::class));
});

test('a deactivated profile is 404, not 403', function () {
    $user = User::factory()->inactive()->create();

    $this->get(route('profile.show', $user))->assertNotFound();
    $this->getJson(route('api.v1.profiles.show', $user))->assertNotFound();
});

test('a corrected write route keeps its limiter', function (string $routeName, string $limiter) {
    $stack = routeMiddleware(app('router')->getRoutes()->getByName($routeName));

    expect($stack)->toContain('Illuminate\Routing\Middleware\ThrottleRequests:'.$limiter);
})->with([
    ['notifications.read', 'content-edits'],
    ['profile.destroy', 'account-deletions'],
]);
