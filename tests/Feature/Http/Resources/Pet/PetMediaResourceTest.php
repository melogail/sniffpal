<?php

use App\Http\Resources\Pet\PetMediaResource;
use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/*
 * `display` is a queued conversion, so right after publish the file does not
 * exist yet. The payload must fall back to the original instead of emitting a
 * URL that 404s until Horizon catches up.
 */
test('a conversion that is not generated yet falls back to the original url', function () {
    $pet     = Pet::factory()->create();
    $media   = $pet->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);
    $media->markAsConversionNotGenerated('display')->save();

    $payload = PetMediaResource::make($media->fresh())->resolve();

    expect($payload['display'])->toBe($media->getUrl())
        ->and($pet->fresh()->featuredPhotoUrl('display'))->toBe($media->getUrl());
});
