<?php

use App\Enums\PetStatus;
use App\Models\Pet;
use App\Models\User;

/**
 * `GET sitemap.xml`: the public pages plus every listing a crawler should
 * index — available, not soft-deleted, owner active — and nothing else.
 */
test('the sitemap lists available listings of active owners only', function () {
    $visible     = Pet::factory()->create(['status' => PetStatus::Available]);
    $unavailable = Pet::factory()->create(['status' => PetStatus::Unavailable]);
    $deleted     = Pet::factory()->create(['status' => PetStatus::Available]);
    $deleted->delete();
    $orphan      = Pet::factory()->for(User::factory()->inactive())->create(['status' => PetStatus::Available]);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('home').'</loc>', escape: false)
        ->assertSee('<loc>'.route('support').'</loc>', escape: false)
        ->assertSee(route('pets.show', $visible), escape: false)
        ->assertDontSee(route('pets.show', $unavailable), escape: false)
        ->assertDontSee(route('pets.show', $deleted), escape: false)
        ->assertDontSee(route('pets.show', $orphan), escape: false);
});

test('the sitemap is valid xml with no listings', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse();
});
