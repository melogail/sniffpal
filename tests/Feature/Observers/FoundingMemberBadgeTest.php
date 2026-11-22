<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The founding-member badge: awarded by PetObserver::created to the owner of
 * a listing created inside the launch window while fewer than
 * `sniffpal.founding.cap` members hold it. Never revoked.
 */
beforeEach(function () {
    config(['sniffpal.founding.cap' => 3, 'sniffpal.founding.window_ends_at' => '2027-01-31 23:59:59']);
    Carbon::setTestNow('2026-12-10 12:00:00');
});

test('the first listing inside the window awards the badge once', function () {
    $owner = User::factory()->create();

    Pet::factory()->for($owner)->create();
    Pet::factory()->for($owner)->create();

    expect($owner->fresh()->isFoundingMember())->toBeTrue()
        ->and(User::foundingMembers()->count())->toBe(1);
});

test('the badge is not awarded past the cap', function () {
    User::factory()->count(3)->create()->each(fn (User $member) => Pet::factory()->for($member)->create());
    $late = User::factory()->create();

    Pet::factory()->for($late)->create();

    expect($late->fresh()->isFoundingMember())->toBeFalse()
        ->and(User::foundingMembers()->count())->toBe(3);
});

test('the badge is not awarded after the window closes', function () {
    Carbon::setTestNow('2027-02-01 00:00:00');
    $owner = User::factory()->create();

    Pet::factory()->for($owner)->create();

    expect($owner->fresh()->isFoundingMember())->toBeFalse();
});

test('deleting the listing keeps the badge', function () {
    $owner = User::factory()->create();
    $pet   = Pet::factory()->for($owner)->create();

    $pet->delete();

    expect($owner->fresh()->isFoundingMember())->toBeTrue();
});

test('the badge is serialised on the profile and the owner byline', function () {
    $owner = User::factory()->foundingMember()->create();
    $pet   = Pet::factory()->for($owner)->create();

    $this->get(route('profile.show', $owner))
        ->assertInertia(fn ($page) => $page->where('profile.is_founding_member', true));
    $this->get(route('pets.show', $pet))
        ->assertInertia(fn ($page) => $page->where('pet.user.is_founding_member', true));
});
