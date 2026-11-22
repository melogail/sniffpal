# Home feed

## Purpose
The landing page: an infinite-scroll grid of available pets with filters and a "near me" mode.

## Data
Reads `pets` (scope `available`), `categories`, `breeds`. Geo uses `pets.latitude/longitude` with a Haversine `nearby` scope (Earth radius 6371 km) and `withDistance` for ordering.

## Backend
- `app/Actions/Pets/ListHomeFeedPets.php` runs the `BuildHomeFeed` pipeline: ScopeToAvailablePets → EagerLoadFeedRelations → ApplyCategoryAndBreedFilter → ApplySearchFilter → ApplyAgeRangeFilter → ApplyListingTypeFilter → ApplyVaccinatedFilter → ApplyNearbyOrRecency → PaginateFeed.
- Request: `Pet/ListHomeFeedRequest` accepts `search`, `category_ids[]`, `breed_ids[]`, `listing_types[]`, `age_min`, `age_max`, `vaccinated`, `latitude`, `longitude`, `radius`. Bounds (radius 1–100 km, default 20; age 0–15 years) come from `config/sniffpal.php`.
- `Web/HomeController` renders `Home` with `pets` as `Inertia::scroll()` (merged pages) and `categories` as `Inertia::defer()` (only needed when the filter sheet opens).

## Routes
`GET /` (home). API equivalent `GET api/v1/pets` with the same query parameters.

## Frontend
`pages/Home.vue`; `PetFilterSheet` with `components/pets/filter/` (AnimalTypeFilter, CategoryFilterRow, BreedFilterList, AgeRangeSlider, FilterSection, FilterCheckboxList/Row, `selection.ts`, `categoryIcon.ts`), `NearbySearchButton` (uses browser geolocation and the `bounds` prop), pet cards. `PetLikeButton` posts with `except: ['pets']` so a like on a scrolled feed does not replace the merged pages (not browser-verified).

- Launch notice: `HomeController::launchProps()` passes `launch` (`founding_open`, `founding_remaining`, `facebook_group_url`, from `sniffpal.founding`); `components/home/LaunchNotice.vue` renders the "we're new" card above the filter row while the offer is open, with the publish button for members and a register link for guests. Costs one COUNT query (FEED_QUERY_COST 11 → 12).
## Guards
Public. No throttle on the feed itself.

## Tests
`tests/Feature/Http/Controllers/Web/HomeControllerTest.php`, `tests/Feature/Actions/Pets/`.

## Open work
- Home copy is feed-generic ("Discover Pets"); an Arabic value proposition for first-time visitors is still missing.
- With 22 seeded cities the feed is thin outside Greater Cairo; consider defaulting the location filter to the user's city.
