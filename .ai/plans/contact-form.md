# Contact form

## Purpose
Lightweight "get in touch" for guests and users from the Support page, without creating a ticket thread.

## Data
`contact_messages` (name, email, subject, message).

## Backend
`app/Actions/Contact/SubmitContactMessage.php`, request `app/Http/Requests/Contact/`, controller `Web/ContactController@store`. Name and email are always required (`StoreContactRequest`); there is no link to an account.

## Routes
`POST contact` (contact.store). Public. API: `POST api/v1/contact` (`Api/V1/ContactController`), same request, action and limiter, answers `201 {message}`.

## Frontend
Inline form on `pages/Support.vue` (no modal, no topic field). Mobile: guest contact sheet on the Help screen (`lib/features/support/presentation/contact_sheet.dart`).

## Guards
Throttle `contact-messages` (5/min per IP). Stored as submitted; Nova escapes on display.

## Admin
Nova `ContactMessage` resource, read-only (`ContactMessagePolicy`).

## Tests
`tests/Feature/Http/Controllers/Web/`, `tests/Feature/Http/Controllers/Api/V1/ContactControllerTest.php`.

## Open work
- No email notification to admins on submission; messages are only visible in Nova.
- No spam protection beyond the throttle (no honeypot or captcha).
