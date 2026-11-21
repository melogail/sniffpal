<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration keeps the language the visitor signed up in', function () {
    $this->withUnencryptedCookie('locale', 'ar')->post(route('register.store'), [
        'name'                  => 'أحمد محمود',
        'email'                 => 'ahmed@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'ahmed@example.com')->value('locale'))->toBe('ar');
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});
