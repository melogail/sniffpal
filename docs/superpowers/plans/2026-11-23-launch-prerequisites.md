# Launch Prerequisites Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the four product pieces the Facebook launch and the ads test depend on: Meta pixel with `registered` and `listing_published` events, a founding-member badge, an honest "we're new" section on the home page, and a sitemap.

**Architecture:** Each piece reuses an existing seam. The pixel is a second destination inside the existing `trackEvent()` helper and a second env-gated snippet in `app.blade.php`, exactly like the Google tag. The badge is one nullable timestamp on `users`, awarded by `PetObserver::created`, read by the two resources that already carry `is_verified`, and rendered next to the existing verified icon. The home section is one new Vue component fed by one new prop from `HomeController`. The sitemap is one controller action.

**Tech Stack:** Laravel 13, Inertia v3, Vue 3, Tailwind v4, Pest. Node tooling: `npm run types:check`, `npx vp check --fix`.

**Spec:** `/home/asciisd/Downloads/projects/sniffpal/brainstorming/2026-11-22-meeting-summary.md` §6 decisions 4, 5, 7 and §7; `/home/asciisd/Downloads/projects/sniffpal/brainstorming/01-ads-and-content-test.md` (pixel events). Decisions the spec left open are marked **(my call)** below.

## Global Constraints

- Follow `.ai/rules/index.md`: read every rule file whose glob matches a path you touch before editing it. `general.md` covers everything.
- Every user-visible string goes through `t()` and is added to **both** `lang/en.json` and `lang/ar.json` (TranslationCatalogueParityTest enforces parity). Arabic copy is Egyptian colloquial, per the Facebook plan.
- No new dependencies. No fake data. No listing is ever created by code outside tests.
- Pixel and tag render only when their env value is set; local and tests must send nothing (same pattern as `GOOGLE_TAG_ID`).
- Event names on the Meta side: `registered` → standard event `CompleteRegistration`; `listing_published` → custom event `listing_published` **(my call: the standard name lets Meta optimise ad delivery on registrations, the custom name keeps the plan's vocabulary for the listing event)**.
- Founding badge: cap `100` members, window closes `2027-01-31 23:59:59 Africa/Cairo` **(my call: covers the 4-week ads test ending 26 Jan plus slack; both values in `config/sniffpal.php` so Mohamed can move them without code)**.
- Badge is awarded once, on the user's first created listing, and is never revoked **(my call: simplest; see Review Focus for the deleted-listing case)**.
- Run `vendor/bin/pint --format agent <files>` with explicit paths on every PHP file touched. Run `npm run types:check` and `npx vp check --fix <files>` on every TS/Vue file touched.
- Commit after each task. Update `.ai/plans/pet-listings.md` (badge, sitemap) and `.ai/plans/home-feed.md` (launch section) **Open work / Frontend** sections in the same commit as the feature, per `.ai/plans/README.md`.

## Review Focus

1. A user whose first listing is later deleted keeps the badge. Expected by a reasonable person: the badge stays (it was earned by a real post; revoking on delete would punish rehoming that succeeded). Pinned in Task 2.
2. The 101st poster inside the window must not get the badge; the 100th must. Pinned in Task 2 (cap boundary test).
3. A listing created after the window closes must not award, even when fewer than 100 members exist. Pinned in Task 2.
4. A guest and a signed-in user both see the launch section; the CTA for a guest goes to register, for a user to `pets.create`. Pinned in Task 3.
5. The sitemap must exclude unavailable and soft-deleted pets and pets of inactive users, and must stay valid XML with zero pets. Pinned in Task 4.

---

### Task 1: Meta pixel

**Files:**
- Modify: `config/services.php` (next to `google_tag`)
- Modify: `.env.example` (next to `GOOGLE_TAG_ID`)
- Modify: `resources/views/app.blade.php` (directly after the Google tag `@endif`)
- Modify: `resources/js/lib/analytics.ts`
- Test: `tests/Feature/GoogleTagTest.php` (rename file to `tests/Feature/AnalyticsTagsTest.php` with `git mv`)

**Interfaces:**
- Consumes: `trackEvent(name: string, params?: Record<string, unknown>): void` from `resources/js/lib/analytics.ts` (already called from Register.vue, pets/Create.vue, StartConversationButton.vue, PetCardShareMenu.vue).
- Produces: `config('services.meta_pixel.id')`; `window.fbq` typed on `Window`; `trackEvent` fans out `sign_up` → `fbq('track','CompleteRegistration')` and `pet_posted` → `fbq('trackCustom','listing_published', params)`.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/AnalyticsTagsTest.php` (after `git mv`)

```php
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
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact tests/Feature/AnalyticsTagsTest.php`
Expected: 2 failures on the new tests, the 2 Google tag tests still pass.

- [ ] **Step 3: Add config and env**

`config/services.php`: `'meta_pixel' => ['id' => env('META_PIXEL_ID')],`. `.env.example`: `META_PIXEL_ID=` with a two-line comment in the style of the `GOOGLE_TAG_ID` block.

- [ ] **Step 4: Add the snippet to `app.blade.php`**

Wrap Meta's standard base code in `@if($metaPixelId = config('services.meta_pixel.id')) … @endif`, calling `fbq('init', '{{ $metaPixelId }}')` and `fbq('track', 'PageView')`. Omit the `<noscript>` image tag (the app does not run without JS).

- [ ] **Step 5: Extend `trackEvent` in `resources/js/lib/analytics.ts`**

Add `fbq?: (command: 'track' | 'trackCustom', name: string, params?: Record<string, unknown>) => void` to the `Window` declaration. Add a module-level map `const META_EVENTS: Record<string, { command: 'track' | 'trackCustom'; name: string }> = { sign_up: { command: 'track', name: 'CompleteRegistration' }, pet_posted: { command: 'trackCustom', name: 'listing_published' } }`. In `trackEvent`, after the gtag call, look the name up and call `window.fbq?.(command, name, params)` when found. Update the docblock to list both destinations.

- [ ] **Step 6: Run tests, types and lint**

Run: `php artisan test --compact tests/Feature/AnalyticsTagsTest.php && npm run types:check && npx vp check --fix resources/js/lib/analytics.ts`
Expected: 4 passed; no type errors; lint pass.

- [ ] **Step 7: Commit**

```bash
git add config/services.php .env.example resources/views/app.blade.php resources/js/lib/analytics.ts tests/Feature/AnalyticsTagsTest.php
git commit -m "feat(analytics): add Meta pixel with registration and listing events"
```

---

### Task 2: Founding-member badge, backend

**Files:**
- Create: `database/migrations/2026_11_23_000000_add_founding_member_at_to_users_table.php` (via `php artisan make:migration add_founding_member_at_to_users_table --no-interaction`)
- Modify: `config/sniffpal.php` (new top-level `'founding'` key)
- Modify: `app/Models/User.php` (cast, `isFoundingMember(): bool`, scope)
- Modify: `app/Observers/PetObserver.php` (new `created()` hook)
- Modify: `app/Http/Resources/User/UserSummaryResource.php`, `app/Http/Resources/Profile/ProfileResource.php` (add `is_founding_member`)
- Modify: `database/factories/UserFactory.php` (state `foundingMember()`)
- Modify: `.ai/plans/pet-listings.md` (document the award rule under Backend / Guards)
- Test: `tests/Feature/Observers/FoundingMemberBadgeTest.php`

**Interfaces:**
- Produces: `users.founding_member_at` nullable timestamp (not in `#[Fillable]`, per `.ai/rules/models.md` "Privileged columns stay out of Fillable"); `User::isFoundingMember(): bool`; `User::scopeFoundingMembers(Builder $query): Builder`; `config('sniffpal.founding.cap')` = `100`, `config('sniffpal.founding.window_ends_at')` = `'2027-01-31 23:59:59'`; `is_founding_member: bool` on every `UserSummaryResource` (hence `PetOwnerResource`) and on `ProfileResource`.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/Observers/FoundingMemberBadgeTest.php`

```php
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Carbon;

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
    User::factory()->count(3)->create()->each(fn (User $u) => Pet::factory()->for($u)->create());
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
    $pet = Pet::factory()->for($owner)->create();

    $pet->delete();

    expect($owner->fresh()->isFoundingMember())->toBeTrue();
});

test('the badge is serialised on the profile and the owner byline', function () {
    $owner = User::factory()->foundingMember()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->get(route('profile.show', $owner))->assertInertia(fn ($page) => $page->where('profile.is_founding_member', true));
    $this->get(route('pets.show', $pet))->assertInertia(fn ($page) => $page->where('pet.user.is_founding_member', true));
});
```

Route names verified 2026-11-23: `profile.show`, `pets.show`, `home`, `support`.

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact tests/Feature/Observers/FoundingMemberBadgeTest.php`
Expected: FAIL, `isFoundingMember` undefined / column missing.

- [ ] **Step 3: Migration**

`$table->timestamp('founding_member_at')->nullable()->after('email_verified_at');` and the matching `dropColumn` in `down()`. No index: the only query is a count, and `.ai/rules/migrations.md` asks for a measurement before adding one.

- [ ] **Step 4: Config**

In `config/sniffpal.php` add a `'founding'` block with a docblock in the file's style: `'cap' => 100`, `'window_ends_at' => env('FOUNDING_WINDOW_ENDS_AT', '2027-01-31 23:59:59')`. Add `FOUNDING_WINDOW_ENDS_AT=` to `.env.example` with a one-line comment.

- [ ] **Step 5: Model**

`User`: cast `'founding_member_at' => 'datetime'`; `public function isFoundingMember(): bool` (`!== null`); `#[Scope] protected function foundingMembers(Builder $query): Builder` (`whereNotNull`), following how the model declares its other scopes. Keep the column out of `#[Fillable]`.

- [ ] **Step 6: Observer**

`PetObserver::created(Pet $pet): void`: return early when the owner already has the badge, when `now()` is past `Carbon::parse(config('sniffpal.founding.window_ends_at'), 'Africa/Cairo')`, or when `User::foundingMembers()->count() >= config('sniffpal.founding.cap')`; otherwise set `founding_member_at = now()` and `saveQuietly()` on the owner. Use `$pet->user` (the relation; `whenLoaded` is irrelevant here). One-line docblock stating the rule. Note in `.ai/rules/observers.md`-style terms is not needed; the test file documents it.

- [ ] **Step 7: Resources and factory**

`UserSummaryResource::toArray`: add `'is_founding_member' => $this->isFoundingMember(),`. `ProfileResource::toArray`: same key next to `is_verified`, and add it to the array-shape docblock. `UserFactory::foundingMember(): static` sets `founding_member_at => now()`.

- [ ] **Step 8: Run tests, pint, phpstan**

Run: `php artisan test --compact tests/Feature/Observers/FoundingMemberBadgeTest.php tests/Feature/Http/Controllers/Web/ProfileControllerTest.php tests/Feature/Http/Controllers/Web/PetControllerTest.php && vendor/bin/pint --format agent <touched php files> && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: all pass, pint clean, phpstan 0 errors (if phpstan reports `Undefined constant Larastan…LARAVEL_VERSION`, run `vendor/bin/phpstan clear-result-cache` first).

- [ ] **Step 9: Document and commit**

Add the award rule and the config keys to `.ai/plans/pet-listings.md` (Backend and Guards sections).

```bash
git add database/migrations config/sniffpal.php .env.example app/Models/User.php app/Observers/PetObserver.php app/Http/Resources/User/UserSummaryResource.php app/Http/Resources/Profile/ProfileResource.php database/factories/UserFactory.php tests/Feature/Observers/FoundingMemberBadgeTest.php .ai/plans/pet-listings.md
git commit -m "feat(users): award a founding-member badge on the first listing in the launch window"
```

---

### Task 3: Founding-member badge, frontend, and the "we're new" home section

**Files:**
- Modify: `resources/js/types/users.ts` (`UserSummary`), `resources/js/types/pets.ts` (`PetOwner`), `resources/js/types/profile.ts` (`ProfileSummary`): add `is_founding_member: boolean`
- Create: `resources/js/components/FoundingMemberBadge.vue`
- Modify: `resources/js/components/profile/ProfileHeader.vue` (next to the `BadgeCheck`), `resources/js/components/pets/PetDetailHeader.vue` (next to the owner name)
- Create: `resources/js/components/home/LaunchNotice.vue`
- Modify: `resources/js/pages/Home.vue` (render `LaunchNotice` above the filter row), `app/Http/Controllers/Web/HomeController.php` (new `launch` prop), `.ai/plans/home-feed.md`
- Modify: `lang/en.json`, `lang/ar.json`
- Test: `tests/Feature/Http/Controllers/Web/HomeControllerTest.php`

**Interfaces:**
- Consumes: `is_founding_member` from Task 2; `CreatePetButton` (existing, auth-aware link to `pets.create`); `register` route from `@/routes`.
- Produces: `launch` Inertia prop on `Home`: `{ founding_open: boolean; founding_remaining: number; facebook_group_url: string | null }`; `config('sniffpal.founding.facebook_group_url')` = `env('FACEBOOK_GROUP_URL')`.

- [ ] **Step 1: Write the failing tests** in `HomeControllerTest.php`

```php
test('the home page carries the launch notice while the founding window is open', function () {
    config(['sniffpal.founding.cap' => 100, 'sniffpal.founding.facebook_group_url' => 'https://facebook.com/groups/sniffpal']);
    User::factory()->count(2)->foundingMember()->create();

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->where('launch.founding_open', true)
        ->where('launch.founding_remaining', 98)
        ->where('launch.facebook_group_url', 'https://facebook.com/groups/sniffpal'));
});

test('the launch notice closes when the cap is reached or the window has passed', function () {
    config(['sniffpal.founding.cap' => 1]);
    User::factory()->foundingMember()->create();

    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('launch.founding_open', false));
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact tests/Feature/Http/Controllers/Web/HomeControllerTest.php --filter=launch`
Expected: FAIL, `launch` prop missing.

- [ ] **Step 3: Controller prop**

In `HomeController` add `'launch' => $this->launchProps()` to the render array. `private function launchProps(): array` returns the three keys; `founding_open` is `now() < window_end && remaining > 0`; `founding_remaining` is `max(0, cap - User::foundingMembers()->count())`. Add `'facebook_group_url' => env('FACEBOOK_GROUP_URL')` to the `founding` config block and `FACEBOOK_GROUP_URL=` to `.env.example`.

- [ ] **Step 4: Types**

Add `is_founding_member: boolean;` to `UserSummary`, `PetOwner`, `ProfileSummary`. Add `export type LaunchProps = { founding_open: boolean; founding_remaining: number; facebook_group_url: string | null };` to `resources/js/types/index.ts` and `launch: LaunchProps` to `Home.vue`'s props.

- [ ] **Step 5: `FoundingMemberBadge.vue`**

Single root `<span>` with a `Sparkles` icon from `@lucide/vue`, the text `t('profile.founding_member')`, Tailwind pill classes in the "Aubergine & Lavender" style (`.ai/rules/js.md` last section: one accent, tokens only). Props: none. Render it in `ProfileHeader.vue` after `BadgeCheck` with `v-if="profile.is_founding_member"`, and in `PetDetailHeader.vue` after the owner name with `v-if="pet.user?.is_founding_member"`.

- [ ] **Step 6: `LaunchNotice.vue`**

Props: `launch: LaunchProps`. Renders nothing when `!launch.founding_open`. Otherwise a `Card` (existing `@/components/ui/card`) with: heading `t('launch.title')`, body `t('launch.body', { remaining: launch.founding_remaining })`, a `CreatePetButton` for a signed-in user or a `Button as-child` `<Link :href="register()">` for a guest (read `usePage().props.auth.user`), and when `facebook_group_url` is set an outbound `<a target="_blank" rel="noopener noreferrer">` with `t('launch.join_group')`. Place `<LaunchNotice :launch="launch" />` in `Home.vue` directly above the filter row `div.mb-6`.

- [ ] **Step 7: Copy in both catalogues**

`lang/en.json`: `"launch.title": "We launched on 23 November 2026 and we are starting from zero"`, `"launch.body": "Post a real listing now and keep the founding-member badge forever. :remaining badges left."`, `"launch.join_group": "Join the Facebook group"`, `"profile.founding_member": "Founding member"`. `lang/ar.json` (Egyptian colloquial): `"launch.title": "فتحنا يوم ٢٣ نوفمبر ٢٠٢٦ وبادئين من الصفر"`, `"launch.body": "انشر إعلان حقيقي دلوقتي وخد شارة عضو مؤسس للأبد. باقي :remaining شارة."`, `"launch.join_group": "انضم لجروب فيسبوك"`, `"profile.founding_member": "عضو مؤسس"`.

- [ ] **Step 8: Run tests, types, lint, parity**

Run: `php artisan test --compact tests/Feature/Http/Controllers/Web/HomeControllerTest.php tests/Feature/TranslationCatalogueParityTest.php && npm run types:check && npx vp check --fix <touched ts/vue files>`
Expected: all pass.

- [ ] **Step 9: Browser check**

Run the app (`composer run dev`), open `/` logged out and logged in, confirm the notice renders in `en` and `ar` (RTL) and the CTA differs. Then open a profile of a user with the badge (seed one with tinker only in local) and confirm the pill.

- [ ] **Step 10: Document and commit**

Add the `launch` prop and `LaunchNotice` to `.ai/plans/home-feed.md` Frontend section.

```bash
git add resources/js app/Http/Controllers/Web/HomeController.php config/sniffpal.php .env.example lang/en.json lang/ar.json tests/Feature/Http/Controllers/Web/HomeControllerTest.php .ai/plans/home-feed.md
git commit -m "feat(home): founding-member badge and launch notice"
```

---

### Task 4: Sitemap

**Files:**
- Create: `app/Http/Controllers/Web/SitemapController.php` (`php artisan make:controller Web/SitemapController --invokable --no-interaction`)
- Create: `resources/views/sitemap.blade.php`
- Modify: `routes/web.php` (`Route::get('sitemap.xml', SitemapController::class)->name('sitemap');`), `public/robots.txt` (append `Sitemap: https://sniffpal.com/sitemap.xml`), `.ai/plans/pet-listings.md`
- Test: `tests/Feature/Http/Controllers/Web/SitemapControllerTest.php`

**Interfaces:**
- Produces: `GET /sitemap.xml` returning `application/xml` with `<loc>` for `home`, `support` and every `pets.show` URL of an available, non-deleted pet whose owner is active.

- [ ] **Step 1: Write the failing tests**

```php
test('the sitemap lists available listings of active owners only', function () {
    $visible = Pet::factory()->create();
    $unavailable = Pet::factory()->create(['status' => PetStatus::Unavailable]);
    $deleted = Pet::factory()->create(); $deleted->delete();
    $orphan = Pet::factory()->for(User::factory()->inactive())->create();

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('home'), escape: false)
        ->assertSee(route('pets.show', $visible), escape: false)
        ->assertDontSee(route('pets.show', $unavailable), escape: false)
        ->assertDontSee(route('pets.show', $deleted), escape: false)
        ->assertDontSee(route('pets.show', $orphan), escape: false);
});

test('the sitemap is valid xml with no listings', function () {
    $xml = $this->get(route('sitemap'))->assertOk()->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse();
});
```

Only `home` and `support` are named static routes (verified 2026-11-23); `/help` and `/guidelines` are unnamed closures, add them by literal path with `url('/help')`.

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --compact tests/Feature/Http/Controllers/Web/SitemapControllerTest.php`
Expected: FAIL, route not defined.

- [ ] **Step 3: Controller**

`__invoke(): Response` selects `Pet::query()->where('status', PetStatus::Available)->whereHas('user', fn (Builder $users) => User::activeOnly($users))->select(['slug', 'updated_at'])->latest('updated_at')->limit(5000)->get()` and returns `response()->view('sitemap', [...])->header('Content-Type', 'application/xml; charset=UTF-8')`. Cache the response body for 1 hour with `Cache::remember('sitemap', 3600, …)` (`shortcut: one file, 5000 URL cap; move to a sitemap index when listings pass 5000`).

- [ ] **Step 4: Blade view**

`<?xml version="1.0" encoding="UTF-8"?>` on the first line, `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`, one `<url><loc>…</loc><lastmod>…</lastmod></url>` per entry with `{{ }}` escaping.

- [ ] **Step 5: Route, robots, run**

Add the route outside the auth group. Append the `Sitemap:` line to `public/robots.txt`.

Run: `php artisan test --compact tests/Feature/Http/Controllers/Web/SitemapControllerTest.php && vendor/bin/pint --format agent app/Http/Controllers/Web/SitemapController.php routes/web.php`
Expected: 2 passed.

- [ ] **Step 6: Document and commit**

Add the sitemap to `.ai/plans/pet-listings.md` Routes section.

```bash
git add app/Http/Controllers/Web/SitemapController.php resources/views/sitemap.blade.php routes/web.php public/robots.txt tests/Feature/Http/Controllers/Web/SitemapControllerTest.php .ai/plans/pet-listings.md
git commit -m "feat(seo): add sitemap.xml and advertise it in robots.txt"
```

---

### Task 5: Deploy and verify on production (Mohamed, not code)

- [ ] Run the migration on production.
- [ ] Set `META_PIXEL_ID`, `FACEBOOK_GROUP_URL` (once the group exists), and confirm `APP_LOCALE=ar` in the production `.env`; `config:cache`.
- [ ] Open https://sniffpal.com logged out: launch notice visible in Arabic, pixel request to `connect.facebook.net` in the network tab.
- [ ] In Meta Events Manager, Test Events: register one real test account and confirm `CompleteRegistration` arrives; this account is Mohamed's own, not a fake user.
- [ ] Ask Claude to re-read GA Realtime and confirm `sign_up` arrived too.
- [ ] Submit `https://sniffpal.com/sitemap.xml` in Google Search Console.

## Out of scope, named

- Admin-to-user in-site message for the consent flow: the Facebook plan's Task 5 falls back to email until built. Not in this plan.
- Nova column for the badge, badge in messaging bylines, revoking the badge: not built.
- Brand-name clean-up ("SniffPal" in the style guide and config key names): separate change.
