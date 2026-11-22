---
paths:
  - 'app/Nova/**'
---

# Nova

## Nova field rules come from the app's validation Concerns, never restated
A Nova resource that restates its field rules will drift from the application's own, and nothing catches it. `App\Nova\User` had `username: nullable|string|max:255` against `ProfileValidationRules::usernameRules()`'s `alpha_dash|min:3|max:50`, and `locale: required|string` against `Rule::in(config('sniffpal.locales.supported'))`. Verified by live PUT: `username = 'not a valid handle!!!'` and `locale = 'klingon'` both persisted. `bio` was `max:2000` against a configured 1000, the lat/lng pair had lost its `required_with`, and the Images field's `singleMediaRules(['image','max:5120'])` dropped the mimes list and doubled `max_avatar_kilobytes`.

A Select's `options()` is not a validator. The docblock claiming a wider Select "would silently write dead values" was right about the risk and wrong about the mechanism — options never reach validation.

`use` the Concern on the resource and call its per-field methods (`$this->usernameRules($this->editedUserId($request))`, `$this->localeRules(required: true)`, `$this->bioRules()`, `$this->avatarFileRules()`). Uniqueness ignore ids come off `$request->resourceId`, not `$this->resource`, so the creation form gets a plain check.

Two shapes to keep: file rules split into a `*FileRules()` method with no `sometimes|nullable`, because a media field validates per file (`ProfileValidationRules::avatarFileRules()`, `App\Concerns\PetPhotoRules::photoFileRules()`); and do not `use` a Concern that carries request-bound public helpers — `PetValidationRules` has `featuredImage()`/`galleryImages()` calling `Request::file()`, which is why the pet photo rules live in their own small trait instead.

## Declare $with = ['media'] on any resource with a media field
`Ebess\AdvancedNovaMediaLibrary\Fields\Images` resolves through `getMedia()` for every row an index renders — one query per row. Nova applies `static::$with` in `PerformsQueries::buildIndexQuery()` and in `ActionRequest`, so declaring it is the whole fix.

Measured on 25 rows: users 26 -> 2 queries, pets 26 -> 2, breeds 26 -> 2, categories 8 -> 2. `Report` was the only resource that set `$with` at all; `User`, `Pet`, `Category` and `Breed` all declare `media` now.

It matters twice over: it also removes the lazy load that the AppServiceProvider Nova exemption was quietly absorbing. Confirmed under test — with `$with` stripped those four indexes still 200 (the exemption catches them) but the query counts jump; with the old broken exemption they 500.

`$with` is index-only. Detail pages are a single `find()`, where `Builder::hydrate()` leaves `preventsLazyLoading` off anyway.

## `@extends \App\Nova\Resource<Model>` — fully qualified, or Pint turns it into the `resource` keyword
`App\Nova\Resource` is `@template TModel` / `@extends NovaResource<TModel>` since 2026-10-23, and every resource declares its model with `@extends \App\Nova\Resource<\App\Models\Pet>`. Write the FQCN with the leading backslash: Pint's `phpdoc_types` fixer lowercases a bare `Resource` in a docblock into PHP's `resource` type keyword, which PHPStan then reports as an unknown class. It happened once during the PHPStan backlog clearance and was caught by the next run.

## Custom Nova components live in nova-components/<name>, built with Nova's devtool — three traps measured 2026-10-30
`nova-components/ticket-thread` is the template: a Composer path package (`sniffpal/<name>`, `@dev`, repository `./nova-components/<name>`), `src/ToolServiceProvider.php` registering `Nova::mix()`, and a laravel-mix build whose `dist/` is committed so deploy needs no npm step. Rebuild with `cd nova-components/<name> && npm run prod` after editing the Vue. Three things the scaffold does not tell you:

1. **The `nova` middleware group is unauthenticated.** It is `config('nova.middleware')` — web + serving — so a tool route behind `['nova']` (the scaffold's stub) answers guests. Use `config('nova.api_middleware')`, the stack `/nova-api` itself runs under (Authenticate + Authorize). Pinned by `TicketThreadTest`.
2. **laravel-mix 6 breaks on webpack newer than 5.105** (`Cannot find module 'webpack/lib/SizeFormatHelpers'`). Pin `webpack` to `5.105.4` in the component's `devDependencies` and `overrides`, which is what Nova's own lockfile uses. Install the devtool from the root vendor copy with `install-links=true` in `.npmrc`, or its dependencies (mix, vue-loader) are not installed.
3. **Do not `require` Nova's `tailwind.config.js` as a preset.** It needs `lodash` from Nova's own `node_modules`, which is not there in a vendor install. Its whole value is `color → rgba(var(--colors-<name>-<shade>))` with primary = sky and gray = slate; inline that mapping (see the component's `tailwind.config.js`) with `preflight: false` and `content` scoped to the component, so only the utilities it uses are emitted. Nova's `app.css` is purged to Nova's own usage — `max-w-[80%]`, `self-end`, `rounded-2xl`, `lg:grid-cols-*` are absent — so a component with no CSS build silently loses layout.
