<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Social sign-in was removed on 2026-11-19 and will return in a later stage.
 * Until then no route, prop or limiter for it may exist.
 */
test('social sign-in is not registered anywhere', function () {
    expect(Route::has('social.redirect'))->toBeFalse()
        ->and(Route::has('social.callback'))->toBeFalse()
        ->and(Route::has('api.v1.auth.social'))->toBeFalse();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->missing('socialProviders'));
});
