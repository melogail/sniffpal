<?php

/**
 * The Google tag and the Meta pixel render only when their id is set, so
 * local and test traffic never reaches analytics.
 */
test('the document head loads the google tag when an id is configured', function () {
    config(['services.google_tag.id' => 'GTM-TEST123']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('https://www.googletagmanager.com/gtm.js?id=GTM-TEST123', escape: false);
});

test('the document head has no google tag when no id is configured', function () {
    config(['services.google_tag.id' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('googletagmanager.com');
});

test('the document head loads the meta pixel when an id is configured', function () {
    config(['services.meta_pixel.id' => '123456789']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee("fbq('init', '123456789')", escape: false)
        ->assertSee('connect.facebook.net/en_US/fbevents.js', escape: false);
});

test('the document head has no meta pixel when no id is configured', function () {
    config(['services.meta_pixel.id' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('fbevents.js');
});
