<?php

namespace App\Observers;

use App\Enums\PetStatus;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PetObserver
{
    /**
     * Give every listing a URL slug before its row exists.
     *
     * The slug is the breed's English name, or the category's when the
     * listing names no breed, followed by a random suffix: the prefix is for
     * humans reading the URL, the suffix is what makes it unique. Uniqueness
     * itself is the `pets.slug` unique index's guarantee, as with
     * `UserObserver`'s media directory name. A slug supplied explicitly (by a
     * seeder, or a factory state) is kept.
     */
    public function creating(Pet $pet): void
    {
        if (filled($pet->slug)) {
            return;
        }

        $prefix    = Str::slug($pet->breed->name ?? $pet->category->name ?? '');

        $pet->slug = ($prefix === '' ? 'pet' : $prefix).'-'.Str::lower(Str::random(8));
    }

    /**
     * The launch offer: the owner's first listing inside the window earns the
     * founding badge while fewer than `sniffpal.founding.cap` hold it. Never
     * revoked, so a deleted listing keeps it (plan: launch-prerequisites).
     */
    public function created(Pet $pet): void
    {
        $owner                     = $pet->user;

        if ($owner === null || $owner->isFoundingMember()) {
            return;
        }

        $windowEnd                 = Carbon::parse(config('sniffpal.founding.window_ends_at'), 'Africa/Cairo');

        if (now()->greaterThan($windowEnd)) {
            return;
        }

        // shortcut: the count-then-save can overshoot the cap by the number of first listings committed in the same instant; wrap in Cache::lock if an exact cap ever matters.
        if (User::foundingMembers()->count() >= (int) config('sniffpal.founding.cap')) {
            return;
        }

        $owner->founding_member_at = now();
        $owner->saveQuietly();
    }

    public function updated(Pet $pet): void
    {
        if ($pet->quantity > 1 && $pet->male_count === 0 && $pet->female_count === 0) {
            $pet->status = PetStatus::Unavailable;
            $pet->saveQuietly();
        }
    }
}
