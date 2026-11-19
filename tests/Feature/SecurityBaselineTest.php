<?php

use App\Http\Middleware\ThrottleAuthRoutes;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/*
 * Security baseline: the cross-cutting invariants a single feature test cannot
 * see. Run with `composer test:security`. Per-model authorization matrices
 * live in tests/Feature/Policies; limiter sizes in tests/Feature/Providers.
 */
pest()->group('security');

beforeEach(function () {
    $this->withoutVite();
});

const MUTATING_METHODS  = ['POST', 'PUT', 'PATCH', 'DELETE'];

/**
 * Public API writes: everything else under /api must carry auth:sanctum.
 */
const PUBLIC_API_WRITES = [
    'api.v1.auth.login',
    'api.v1.auth.register',
    'api.v1.auth.password.email',
    'api.v1.contact.store',
];

/**
 * First-party routes that write, i.e. the ones behind an App\ controller.
 *
 * @return Collection<int, Route>
 */
function mutatingApplicationRoutes(): Collection
{
    return applicationRoutes()->filter(fn (Route $route): bool => array_intersect($route->methods(), MUTATING_METHODS) !== []
        && str_starts_with((string) $route->getActionName(), 'App\\'));
}

/**
 * @param  list<string>  $middleware
 */
function middlewareContains(array $middleware, string $needle): bool
{
    return collect($middleware)->contains(fn (string $entry): bool => str_contains($entry, $needle));
}

test('every first-party write route is throttled', function () {
    $throttledByName = (new ReflectionClassConstant(ThrottleAuthRoutes::class, 'LIMITERS'))->getValue();

    $uncapped        = mutatingApplicationRoutes()
        ->reject(fn (Route $route): bool => middlewareContains(routeMiddleware($route), 'ThrottleRequests')
            || array_key_exists((string) $route->getName(), $throttledByName))
        ->map(fn (Route $route): string => $route->uri())
        ->values()
        ->all();

    expect(mutatingApplicationRoutes())->not->toBeEmpty()
        ->and($uncapped)->toBe([]);
});

test('every web write route is CSRF protected', function () {
    $unprotected = mutatingApplicationRoutes()
        ->reject(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
        ->reject(fn (Route $route): bool => middlewareContains(routeMiddleware($route), 'RequestForgery')
            || middlewareContains(routeMiddleware($route), 'Csrf'))
        ->map(fn (Route $route): string => $route->uri())
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

test('every API write route outside the public allowlist requires a token', function () {
    $open = mutatingApplicationRoutes()
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
        ->reject(fn (Route $route): bool => in_array($route->getName(), PUBLIC_API_WRITES, true))
        ->reject(fn (Route $route): bool => middlewareContains(routeMiddleware($route), 'Authenticate:sanctum'))
        ->map(fn (Route $route): string => $route->uri())
        ->values()
        ->all();

    expect($open)->toBe([]);
});

test('a guest is turned away from every guarded page', function () {
    $leaks = applicationRoutes()
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && ! str_contains($route->uri(), '{')
            && middlewareContains(routeMiddleware($route), 'Auth\\Middleware\\Authenticate'))
        ->mapWithKeys(fn (Route $route): array => ['/'.$route->uri() => $this->get('/'.$route->uri())->getStatusCode()])
        ->reject(fn (int $status): bool => in_array($status, [302, 401, 403], true))
        ->all();

    expect($leaks)->toBe([]);
});

test('an unverified member cannot publish', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('pets.store'))->assertRedirect(route('verification.notice'));

    Sanctum::actingAs($user);
    $this->postJson(route('api.v1.pets.store'))->assertForbidden();
});

test('a member cannot touch another member\'s listing', function (string $method, string $routeName) {
    $pet      = Pet::factory()->create();
    $stranger = User::factory()->create();

    Sanctum::actingAs($stranger);

    $this->actingAs($stranger)->json($method, route($routeName, $pet))->assertForbidden();
})->with([
    'web edit'   => ['GET', 'pets.edit'],
    'web delete' => ['DELETE', 'pets.destroy'],
    'api delete' => ['DELETE', 'api.v1.pets.destroy'],
]);

test('privileged columns ignore the profile form', function (string $uri, int $savedStatus) {
    $user  = User::factory()->create();

    Sanctum::actingAs($user);

    $this->actingAs($user)->json('PATCH', $uri, [
        'name'              => 'Renamed',
        'email'             => $user->email,
        'id'                => $user->getKey() + 1000,
        'email_verified_at' => null,
        'is_active'         => false,
    ])->assertStatus($savedStatus);

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Renamed')
        ->and($fresh->getKey())->toBe($user->getKey())
        ->and($fresh->hasVerifiedEmail())->toBeTrue()
        ->and($fresh->isActive())->toBeTrue();
})->with([
    'web' => ['/settings/profile', 302],
    'api' => ['/api/v1/me', 200],
]);

test('a public profile never carries private fields', function () {
    $user = User::factory()->withTwoFactor()->create(['phone' => '01001234567', 'show_phone' => false]);

    $json = $this->getJson(route('api.v1.profiles.show', $user))->assertOk()->getContent();

    expect($json)->not->toContain($user->email)
        ->not->toContain('01001234567')
        ->not->toContain('password')
        ->not->toContain('two_factor');

    $this->get(route('profile.show', $user))->assertInertia(
        fn (AssertableInertia $page) => expect(json_encode($page->toArray()['props']))->not->toContain($user->email)
    );

    expect(array_keys($user->toArray()))
        ->not->toContain('password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token');
});

test('a member session does not open the back office', function () {
    $this->actingAs(User::factory()->create())
        ->get('/nova/dashboards/main')
        ->assertRedirect();
});
