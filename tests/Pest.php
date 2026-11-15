<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * First-party routes: no host of their own and not a vendor dashboard. Nova
 * answers on every host while NOVA_DOMAIN is unset, so it is dropped by path.
 *
 * @return Collection<int, Route>
 */
function applicationRoutes(): Collection
{
    return collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => $route->getDomain() === null
            && preg_match('#^(nova|nova-api|nova-vendor|horizon|telescope|pulse|_boost|_debugbar|sanctum|broadcasting|storage|livewire)(/|$)#', $route->uri()) !== 1)
        ->values();
}

/**
 * The middleware a route really runs, groups expanded and priority-sorted,
 * as "Class" or "Class:params" strings.
 *
 * @return list<string>
 */
function routeMiddleware(Route $route): array
{
    return array_values(array_filter(app('router')->gatherRouteMiddleware($route), 'is_string'));
}
