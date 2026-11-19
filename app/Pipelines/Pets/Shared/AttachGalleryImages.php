<?php

namespace App\Pipelines\Pets\Shared;

use App\Models\Pet;
use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Attach the gallery photos, after the listing itself is committed.
 *
 * Gallery photos share the `pets` collection with the cover photo and are
 * distinguished only by *not* carrying the `featured` custom property.
 * MediaOwnerDirectoryObserver stamps the owner directory on every row (see
 * .ai/rules/media-library.md).
 *
 * Uploads run outside the transaction on purpose: a rollback cannot un-write a
 * file, so a failure inside the transaction would leave orphaned uploads on
 * disk with no row pointing at them.
 *
 * The work is inline rather than delegated to an Action of the same name: it
 * has one caller and is not worth exercising without the pipeline.
 */
class AttachGalleryImages
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        if ($context->galleryImages === []) {
            return $next($context);
        }

        $pet = $context->pet();

        foreach ($context->galleryImages as $image) {
            $pet->addMedia($image)
                ->usingFileName($image->hashName())
                ->toMediaCollection(Pet::PHOTO_COLLECTION);
        }

        return $next($context);
    }
}
