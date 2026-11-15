<?php

use App\Models\Admin;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Routing\Route;

/*
 * Smoke: does every page answer at all? Run with `composer test:smoke`.
 * Behaviour belongs to the feature tests; this suite only proves nothing
 * throws or has gone missing before the full suite is paid for.
 */
pest()->group('smoke');

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Every first-party GET route that needs no parameters, as "/uri".
 *
 * @return list<string>
 */
function parameterlessGetUris(): array
{
    return applicationRoutes()
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{'))
        ->map(fn (Route $route): string => '/'.ltrim($route->uri(), '/'))
        ->unique()
        ->values()
        ->all();
}

/**
 * Request every URI and return the ones that 404 or 5xx, keyed by status.
 *
 * @param  list<string>  $uris
 * @return array<string, int>
 */
function brokenUris(TestCase $test, array $uris): array
{
    $broken = [];

    foreach ($uris as $uri) {
        $status = $test->get($uri)->getStatusCode();

        if ($status === 404 || $status >= 500) {
            $broken[$uri] = $status;
        }
    }

    return $broken;
}

test('every parameterless GET route answers a guest', function () {
    $uris = parameterlessGetUris();

    expect($uris)->not->toBeEmpty()
        ->and(brokenUris($this, $uris))->toBe([]);
});

test('every parameterless GET route answers a verified member', function () {
    $this->actingAs(User::factory()->create());

    expect(brokenUris($this, parameterlessGetUris()))->toBe([]);
});

test('the public detail pages render', function () {
    $pet = Pet::factory()->create();

    $this->get(route('pets.show', $pet))->assertOk();
    $this->get(route('profile.show', $pet->user))->assertOk();
    $this->getJson(route('api.v1.pets.show', $pet))->assertOk();
    $this->getJson(route('api.v1.profiles.show', $pet->user))->assertOk();
});

test('an owner reaches the listing editor', function () {
    $pet = Pet::factory()->create();

    $this->actingAs($pet->user)->get(route('pets.edit', $pet))->assertOk();
});

test('an admin reaches the back office', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('/nova/dashboards/main')
        ->assertOk();
});
