# Business recommendations

Written 2026-11-19 from a full code and plan review (six parallel audits), then updated the same day after Mohamed's decisions: social login removed for now, mobile real-time deferred, year-two revenue = option 2, fix everything found, remove dead code. Facts below are read from the repository; nothing here is a market statistic. Update this file when a decision is taken, and record the decision, not the discussion.

## 1. Where the product stands

- The build is complete for a year-one marketplace: listings (adoption, sale, mating, litters), feed with filters and "near me", comments, likes, reviews, real-time messaging, notifications, reports with a Nova moderation flow, support tickets, EN/AR, a Sanctum API and a Flutter app. Test coverage is broad (152 files) and the CI gate is green again.
- It is not deployed. `APP_ENV=local`, `APP_LOCALE=en`, media on the local disk, no scheduler, no Horizon or Reverb supervisor entry, no trusted proxies.
- Engineering effort is now going into prose: 47% of the lines under `app/` are comments and `.ai/rules` is 369 KB (general.md alone 134 KB). Every agent or developer pays that reading cost on every change. This is the single largest drag on velocity and it is a choice, not a defect.

## 2. Launch blockers (status after the fix pass of 2026-11-19)

These were the defects the review found that would have cost users or trust on day one. All are fixed in code; what remains is deployment work.

| # | Blocker | Status |
| --- | --- | --- |
| 1 | `db:seed` in production created a known admin login | Fixed: `AdminSeeder` runs only outside production; create the first admin with `php artisan nova:user` |
| 2 | Horizon serves only `redis`; env example shipped `database` | Fixed in `.env.example` and docs; `deploy/supervisor/horizon.conf` and `reverb.conf` added. Production `.env` must still be written |
| 3 | API social login was an account takeover | Removed with social login (decision 1); the return conditions are in social-login.md |
| 4 | No trusted proxies; IP-keyed auth limiters | Fixed: `trustProxies(at: '*')`, pinned by a test. Day caps unchanged; watch the 429 rate on launch day |
| 5 | Photos 404 right after publish | Fixed: resources fall back to the original until the conversion exists |
| 6 | Any filter hid listings without a birth date | Fixed and tested |
| 7 | Liking a scrolled card collapsed the feed | Fixed in `PetLikeButton`; not browser-verified, check once with seeded listings |
| 8 | Hard-coded English strings | Routed through `t()` with Arabic translations (see localization.md) |
| 9 | Support page mailed `support@petconnect.com` | Fixed: the page reads `mail.from.address`; set `MAIL_FROM_ADDRESS` to the real support inbox |
| 10 | Password reset left mobile tokens alive | Fixed: reset and web change revoke every token; tokens expire after a year and are pruned daily |

Deployment checklist still open: write the production `.env` (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `APP_LOCALE=ar`, `APP_TIMEZONE=Africa/Cairo`, `LOG_STACK=daily`, `SESSION_SECURE_COOKIE=true`, `MEDIA_DISK=s3` with `AWS_*`, `MAIL_FROM_ADDRESS`), install the three supervisor programs, add the one cron line for `schedule:run`, confirm the Reverb proxy path.

## 3. Strategy: what to optimise

**Metric.** Registrations and post counts are inputs; the business is liquidity. Track, from day one: share of listings with at least one conversation within 7 days, conversations started per week, and completions (owner marks the pet unavailable after a conversation). Nothing in the code emits analytics events yet; add `pet_published`, `conversation_started`, `listing_shared`, `status_toggled` as the first analytics work, before any ad spend.

**Geography.** Launch Greater Cairo only. Twenty-two seeded cities produce twenty-one empty feeds. The home feed should default to the visitor's city or to Cairo, and "near me" should be the first-run prompt.

**Supply before demand.** Individual owners post once. Repeat suppliers (shelters, rescues, breeders, pet shops) post every week. Recruit ten of them by hand before the first demand push and seed around one hundred live Cairo listings. The verified-seller badge is the product they will ask for; today the only badge is email verification.

**Trust is the differentiator.** Facebook groups and Dubizzle already have the volume. SniffPal's structured health data, breed filters, reviews and a real moderation queue are the reason to switch. Two product gaps weaken that story: reviews are not tied to a conversation (anyone can review anyone), and reports cannot be re-filed after a dismissal. Gate reviews on an existing conversation and let a user report again when the content changes.

**Arabic first.** The plan says Arabic default; the code says `en`. Switch the default, fix the hard-coded strings, and write the home-page value proposition in Arabic before the first external link is shared.

**Mobile.** Decision (2026-11-19): real-time channel auth for the app is deferred; the Flutter app stays on `me/badges` polling. Its social sign-in call has no endpoint until social login returns.

## 4. Year-two revenue hypothesis

Decision (2026-11-19): **option 2, a vet and pet-shop directory with paid placement.** Nothing of it exists in code yet, so year one must build the audience it sells to and the data it needs:

- Recruit vets and pet shops as free directory entries from month one; they are also repeat suppliers of listings and the first paying customers later. Track how many profiles of this kind exist and how many conversations they receive.
- Capture location on every listing and profile (already in the schema) so a district-level directory can launch with real coverage in Cairo.
- Add a `business` flag or profile type when the first ten vets are on board, not before; a verified badge for them is the trust feature that also serves the marketplace.
- Pricing comes from the data: cost per lead on the free directory in year one sets the placement price in year two.

The listing and welfare policy page that the moderation plan promises is the legal baseline for any revenue option; it is still unwritten.

## 5. Engineering choices with business cost

- **Comment volume.** 15,800 of 33,400 lines in `app/` are prose, and six files are over 75% comment. Move the reasoning essays into `docs/architecture/` or the matching `.ai/rules` file and leave one line of "why" in code. Expected effect: faster reviews, cheaper agent runs, fewer stale sentences (the review found and fixed nine false ones about the queue alone).
- **Rules size.** `.ai/rules/general.md` is 134 KB and must be read before any edit. Split the dated case studies out of it and keep the rules.
- **Single-case enums and unused seams.** `MessageType`, `MessageStatus` and `ConversationType` stay because the Flutter app parses them from the message payload; `EnsureRecipientAccepts` stays as the seam for block/mute; `RefreshPetWithMedia` stays because the returned listing is read with its relations. Each is a one-line decision recorded in the review ledger; revisit when the mobile contract changes.
- **Deployment target.** Horizon, Reverb, Redis, S3 and SSR need a box with supervisor. Laravel Cloud or Forge removes most of the checklist in section 2. Decide the host before fixing the config, so the config is fixed once.

## 6. Thirty-day plan

- **Week 1.** Fix section 2, deploy to a staging URL on the chosen host, switch Arabic default, add the four analytics events. Ten interviews: five would-be adopters/buyers, five shelters or breeders.
- **Week 2.** Hand-recruit ten repeat suppliers; seed one hundred live Cairo listings (half from them). Write the listing and welfare policy page.
- **Week 3.** Demand test without ad spend: post ten listings into the three largest Cairo pet Facebook groups with links back. Measure registrations that lead to a conversation, not registrations.
- **Week 4.** Read the liquidity numbers. If fewer than one listing in five gets a conversation, the problem is demand; if conversations happen but owners do not reply, the problem is supplier onboarding. Decide the next month from that, not from sign-up counts.

## 7. Decisions

Taken 2026-11-19: social login removed for now; mobile real-time out of launch scope; year-two revenue is the vet and pet-shop directory; fix everything found; remove dead code.

Still open:
1. Hosting target and go-live date.
2. Brand name on the public pages (PetConnect still appears in the style guide). Arabic is now the default locale in `.env.example`.
3. Whether reviews require a prior conversation.
4. Policy on comment prose and rule size (section 5), since it changes how every future change is written.

## 8. Still unvalidated assumptions

Trust is the main buyer pain; shelters and breeders will post on a new site; Arabic search demand exists for "كلاب للبيع" and "قطط للتبني"; mating is an underserved niche; people will register to message rather than demand a WhatsApp number; anyone will pay in year two. Each of the ten interviews in week one should test at least two of these.
