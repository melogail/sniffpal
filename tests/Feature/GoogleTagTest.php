<?php

/**
 * The Google tag is rendered only when `services.google_tag.id` is set, so
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
