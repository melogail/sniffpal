# Pet listings

## Purpose
The core object: a user posts a pet for adoption, sale or mating, with photos, health data, traits and a location. Includes litters (quantity, male/female counts, gender `mixed`).

## Data
`pets` table and `media` (collection `pets`, custom property `featured`). See [data-model.md](data-model.md). Slug is generated in `PetObserver` as `{breed-or-category-slug}-{8 random chars}` and is the route key; `views` is a non-fillable counter.

## Backend
- Actions (`app/Actions/Pets/`): CreatePet, UpdatePet, DeletePet (soft), PurgePet (hard, with dependent content), TogglePetStatus, LoadPetDetail, RecordPetView, ListPetCategories, AttachFeaturedImage, EnsureSingleFeaturedPhoto. The media owner directory is stamped by `MediaOwnerDirectoryObserver`.
- Pipelines (`app/Pipelines/Pets/`):
  - Shared: ResolveCategoryAndBreed, NormalizeBasicAttributes, NormalizeLocation, NormalizeHealthData, NormalizeTraits, NormalizeAdditionalInfo, EnsurePhotosAreDecodable, AttachGalleryImages, RefreshPetWithMedia.
  - Create: PersistPet, AttachFeaturedImage.
  - Update: PersistPet, ReplaceFeaturedImage, RemoveDeletedMedia, EnsureGalleryCapacity.
  - Purge: CollectListingContent, DeleteListingComments, DeleteListingLikes, DeleteListingSaves, DeleteListingReports, PurgeListingRecord.
- Requests: `Pet/StorePetRequest`, `Pet/UpdatePetRequest` (rules in `PetValidationRules`, `PetPhotoRules`).
- Resources: `app/Http/Resources/Pet/` (card, detail, media, owner, category/breed options). `PetDetailResource` is also the edit-form contract.
- Policy: `PetPolicy` (owner-only update/delete/status).
- Founding badge: `PetObserver::created` stamps `users.founding_member_at` on the owner's first listing while `now()` is before `sniffpal.founding.window_ends_at` (Africa/Cairo) and fewer than `sniffpal.founding.cap` members hold it. Never revoked. Serialised as `is_founding_member` on `UserSummaryResource` (hence every byline and `PetOwnerResource`) and `ProfileResource`. Tests: `tests/Feature/Observers/FoundingMemberBadgeTest.php`.
- Controllers: `Web/PetController`, `Api/V1/PetController`.

## Routes
- Web: `GET pets/create`, `POST pets`, `GET pets/{pet}`, `GET pets/{pet}/edit`, `PUT pets/{pet}`, `DELETE pets/{pet}`, `PATCH pets/{pet}/status`, `POST pets/{pet}/like`. Create/edit/delete need `auth` + `verified`.
- API: see [api-v1.md](api-v1.md).
- `GET sitemap.xml` (`Web/SitemapController`, named `sitemap`): public pages plus available listings of active owners, newest first, capped at 5000, cached one hour under `sitemap`. Advertised in `public/robots.txt`. Test: `SitemapControllerTest`.

## Frontend
`pages/pets/{Create,Edit,Show}.vue`; components under `resources/js/components/pets/` (card, gallery carousel via embla, share buttons Facebook/X/WhatsApp, age/litter labels). Client-side image compression with `browser-image-compression`; Google Maps loader for the location picker.

## Guards
Throttles `pet-listings` (5/min, 30/h) and `pet-listing-edits` (10/min, 60/h). Photos are decode-verified server-side (`app/MediaLibrary/ImageDecodeVerifier`) and bounded to 8000×8000 px (`Rule::dimensions`) before decoding. Contact buttons on the listing are hidden from guests.

## Admin
Nova `Pet` resource with `PurgePetListing` action. Metric `NewPets`.

## Tests
`tests/Feature/Actions/Pets/`, `tests/Feature/Http/Controllers/Web/PetControllerTest.php`, `tests/Feature/Observers/`, `tests/Feature/MediaLibrary/`.

## Open work
- Per-page Open Graph title/description for listing pages (only a default og:image exists).
- `views` is a vanity counter and is unthrottled on a public GET; do not build metrics on it.
- Analytics events (post created, shared, conversation started) are not emitted anywhere.
- Listings of a deactivated owner are hidden from the feed (`ScopeToAvailablePets`) and 404 on show (`Pet::resolveRouteBinding`); `LoadPetDetail`, `ListCommentThread`, `ListReviews` and the feed card preview (`EagerLoadFeedRelations`) all filter by author state via `User::activeOnly()`.
