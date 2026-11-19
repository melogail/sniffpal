# Social login

## Status
Removed from the codebase on 2026-11-19 by decision (Mohamed): Socialite, the `SocialProvider` enum, `social_accounts`, `ResolveSocialUser`, both controllers, the `social-logins` limiter, the login/register buttons and their tests are gone. The Flutter app's social sign-in call has no endpoint until this returns.

## When it returns
- Verify the provider token's audience before resolving a user (Google tokeninfo `aud`, Facebook `debug_token` `app_id`); linking by email without that check is an account takeover.
- Refuse email linking when the provider reports the email unverified.
- Re-read the three settled decisions in `.ai/rules/actions.md` (random password, callback is the one GET that writes, link by verified email).
- Re-add the `social-logins` limiter (10/min, 60/h per IP) and the Wayfinder routes.
