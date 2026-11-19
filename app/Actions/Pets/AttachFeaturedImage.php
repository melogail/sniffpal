<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach the cover photo of a listing.
 *
 * The cover photo is not a separate collection: it is the member of the `pets`
 * collection carrying the `featured` custom property, which is what
 * Pet::featuredPhoto() and Pet::galleryPhotos() read.
 */
class AttachFeaturedImage
{
    public function handle(Pet $pet, UploadedFile $image): Media
    {
        return $pet->addMedia($image)
            ->usingFileName($image->hashName())
            ->withCustomProperties([Pet::FEATURED_PROPERTY => true])
            ->toMediaCollection(Pet::PHOTO_COLLECTION);
    }
}
