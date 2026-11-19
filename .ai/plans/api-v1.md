# API v1

## Purpose
JSON API for the Flutter app (`~/mobile-apps/sniffpal`). Mirrors the web features one-to-one; every API controller calls the same `app/Actions` as the web controller.

## Data
`personal_access_tokens` (Sanctum). No API-only tables.

## Backend
- Controllers in `app/Http/Controllers/Api/V1/` and `Api/V1/Auth/`.
- Resources in `app/Http/Resources/{Comment,Conversation,Message,Notification,Pet,Profile,Review,Ticket,User}`; the same resource classes feed Inertia props on the web. `Ticket/TicketThreadResource` is API-only (header + thread in one body). `User/AccountResource` carries `show_phone` and `whatsapp_enabled` so the app's privacy screen reads and writes the same keys as the web.
- Form requests are shared with the web via the `*ValidationRules` concerns.
- `UseSanctumGuard` middleware makes `auth()` resolve the bearer token user on `api/*`.

## Routes (`routes/api.php`, prefix `api/v1`, name `api.v1.`)
- Public: `GET categories`, `GET options`, `GET pets`, `GET pets/{pet}`, `GET profiles/{user}`, `GET profiles/{user}/pets`, `GET profiles/{user}/reviews`, `GET comments/{type}/{id}`, `GET comments/{comment}/replies`, `GET reviews/{type}/{id}`.
- Auth: `POST auth/register|login|logout|forgot-password|social/{provider}|email/verification-notification`.
- `auth:sanctum`: `GET|PATCH|DELETE me`, `GET me/badges`, `PUT me/password`, notifications (index, read, read-all, destroy-all), conversations (index, store, show, messages index/store, read), messages (update, destroy, pin), likes (`pets/{pet}/like`, `comments/{comment}/like`, `profiles/{user}/like`), comments store/update/destroy, reviews store/update/destroy, reports store.
- `auth:sanctum` + `verified` (one group from `routes/api.php` line ~215): pets store/update/destroy/status, every like, comments, reviews, reports, conversations and messages, `PUT me/password`, `DELETE me`. Only `me`, `me/badges`, notifications and tickets are reachable unverified.
- Support: public `POST contact`; `auth:sanctum` `GET|POST me/tickets`, `GET me/tickets/{ticket}`, `POST me/tickets/{ticket}/reply|close` (see [support-tickets.md](support-tickets.md), [contact-form.md](contact-form.md)). `GET options` also carries `ticket_categories` and `bounds.support`.

## Frontend
None here; consumer is the Flutter app.

## Guards
Same named throttles as the web routes (see [rate-limiting.md](rate-limiting.md)). `BadgeController` returns `unread_conversations` and `unread_notifications` for app badges.

## Tests
`tests/Feature/Http/Controllers/Api/V1/`, `tests/Feature/Http/Resources/`.

## Open work
- No API route for saves (see [saves.md](saves.md)).
- No channel authorisation for the app: `/broadcasting/auth` carries the `web` middleware only, so a bearer client cannot subscribe to `App.Models.User.{id}`. Deferred by decision (2026-11-19).
- `POST auth/social/{provider}` was removed with social login (2026-11-19).
- Endpoint reference for app developers lives in `docs/api/endpoints.md`; regenerate it when routes change.
