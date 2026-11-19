# Reports and moderation

## Purpose
Users flag content; moderators decide in Nova; both sides are told the outcome. Today only comments and reviews are reportable and a decision is a bare status change. This plan extends reporting to listings and profiles and turns "Change Status" into "Resolve", which performs the decision and records it.

## Review (2026-11-19)
Fixed the same day: bulk `ResolveReport` decides each target once; a strike is one upheld target however many reported it (`User::strikeCount()`), shown as "N (M in last 90 days)" on the User and Report detail; the Warn note is `required_if`; "Reports Against" reads `User::reportsAgainst()` (`subject_user_id`); `reports.user_id` is nullable and nulls on reporter deletion; the unique index on (user_id, reportable_type, reportable_id) is a plain index and the duplicate guard checks pending reports only, so a member can report the same target again after a decision. The pipeline directory is `Pipelines/Reports/Resolve/`.

## Status (2026-11-01)
Every step of the delivery order below is implemented, including the Flutter app (`~/mobile-apps/sniffpal`: report sheet without category, reasons keyed by target, report buttons on listing and profile). Deactivated members are hidden from the feed, listing show, profile show and the comment and review loaders (`User::active()` scope). A soft-deleted listing still resolves from its report (`Report::reportable()` is `withTrashed()`). Not done, by decision: Nova `dependsOn` narrowing of the decision select (the Action refuses a bad fit instead), and `moderator_note` is `strip_tags` + trim rather than the comment sanitizer.

## What existed before this plan (inventoried 2026-10-30)
- `reports` (user_id, reportable_type/id, category, reason, description, metadata, status). Reportables: Comment, Review. Enums `ReportCategory`, `ReportReason`, `ReportStatus` (pending → reviewed/resolved/rejected). `status` is not fillable; `ChangeReportStatus` is its only writer.
- `CreateReport` runs SubmitReport: ResolveReportable → EnsureNotSelfReport → EnsureNotAlreadyReported → PersistReport → NotifyModerators (`ReportFiledNotification` to every admin). Unique `(user_id, reportable_type, reportable_id)`.
- Routes `POST reports/{type}/{id}` (web + API v1), `throttle:reports` 5/min.
- `ReportDialog.vue` on comment and review items; `reportCategories` / `reportReasons` are page props.
- Nova `Report` resource (read only, pending first, oldest first), actions `ChangeReportStatus`, `PurgeOrphanedReports`; `Moderation` dashboard.
- Content removal already exists as app Actions reused by Nova: `DeletePet` (soft), `PurgePet` (hard), `DeleteComment` (subtree), `DeleteReview`, `DeactivateUser`, `DeleteUserAccount`. Comment and pet purge pipelines **delete the reports on the content** (`DeleteSubtreeReports`, `DeleteListingReports`), so a decision that removes content currently erases its own record.
- Known gaps: reporter never hears back; reported user never hears why; `DeactivateUser` does not hide the user's listings from the feed (`is_active` is not consulted by any pet query); `ReportCategory` carries Bug/Technical/Feedback, which belong to support tickets.

## Decisions (settled by this plan unless Mohamed overrides)
1. **Four targets**: Comment, Review, Pet, User (profile). A conversation message is not reportable in v1; report the profile instead.
2. **Reason is the classification; category goes.** `reports.category` and `ReportCategory` are dropped (tickets own bug/feedback). Reasons are per target (see matrix). Cost: migration, Nova filter, `BuildClientOptions`, `OptionController`, API docs, dialog, mobile app. Not deployed yet, so no data migration.
3. **Status stays the state machine; resolution says what was done.** `Pending` = new. `Reviewed` = looked at, waiting (second opinion / asked the user to fix). `Resolved` = violation, action taken (`resolution` set). `Rejected` = no violation (dismissed). No enum value changes.
4. **The report is the moderation record.** New `subject_user_id` on reports (the author/owner being reported, filled at submit time) means the record, strikes and "reports against this user" survive the content being deleted. Cleanup pipelines delete only **pending** reports on removed content; resolved rows are kept with a null `reportable`. `PurgeOrphanedReports` keeps its job for the rest.
5. **No automation in v1.** No auto-hide at N reports, no auto-ban at N strikes. The queue is ordered so the dangerous ones come first and the strike count is shown to the moderator. Thresholds are v2 once there is data.
6. **Deactivated users disappear with their content.** Feed, listing show, profile show and comment/review loaders exclude content whose owner is inactive. Needed before "deactivate" can be a moderation outcome.

## Reasons per target
| Target | Reasons (`ReportReason`) | Priority |
| --- | --- | --- |
| Pet | `scam_or_fraud`, `animal_welfare`, `prohibited_animal`, `misleading_listing` (wrong breed/category/price/photos), `duplicate_listing`, `no_longer_available`, `spam`, `inappropriate_content`, `other` | scam, welfare, prohibited = high |
| User | `scammer`, `impersonation`, `harassment`, `spam`, `inappropriate_content`, `other` | scammer, impersonation, harassment = high |
| Comment | `spam`, `harassment`, `hate_speech`, `false_information`, `inappropriate_content`, `other` | hate_speech, harassment = high |
| Review | `fake_review`, `harassment`, `hate_speech`, `inappropriate_content`, `spam`, `other` | harassment, hate_speech = high |

One enum with all cases; `ReportReason::forTarget(Reportable)` returns the subset; `ReportReason::isHighPriority()` drives queue order. `StoreReportRequest` validates `reason` against `forTarget`.

## What each resolution does
`ReportResolution` enum: `content_removed`, `content_purged`, `warned`, `account_deactivated`. Dismiss is `status = rejected` with no resolution.

| Resolution | Pet | Comment | Review | User | Side effects (all) |
| --- | --- | --- | --- | --- | --- |
| **Dismiss** (status rejected) | nothing | nothing | nothing | nothing | reporter notified "no violation found" |
| **Warn** | listing stays; owner notified with reason + guideline link | same | same | user notified | reporter notified "action taken"; counts as a strike |
| **Remove content** | `DeletePet` (soft delete, owner cannot restore) | `DeleteComment` subtree | `DeleteReview` | n/a | subject notified "removed because {reason}"; sibling pending reports on the same target resolved the same way; reporter(s) notified; strike |
| **Purge content** | `PurgePet` (photos gone; illegal/animal-cruelty media) | n/a (remove is already hard) | n/a | n/a | as Remove |
| **Deactivate account** | `DeactivateUser` on the owner; listings vanish via decision 6 | on the author | on the author | on the user | subject gets email (database notification is unreachable once deactivated); every pending report whose `subject_user_id` is that user is resolved; reporters notified; `ReactivateUser` is the undo |

Account deletion stays a separate, manual `DeleteUserAccount` on the User resource. It is irreversible and never a one-click report outcome.

**Moderator guidance (goes into the admin help text and the guidelines page):** first upheld report → warn; second → remove; third within 90 days, or any scam/welfare/prohibited-animal → deactivate. The strike count is on the report detail, so the moderator does not have to look it up.

## Data (migration)
- `reports`: add `subject_user_id` (FK users, nullOnDelete, indexed), `resolution` (string, nullable), `moderator_note` (text, nullable), `resolved_by` (FK admins, nullOnDelete), `resolved_at` (timestamp, nullable). Drop `category`. Index `(reportable_type, reportable_id, status)` for sibling lookup and `(subject_user_id, status, resolved_at)` for strikes.
- Enums: `Reportable` += `Pet`, `User` (morph aliases already exist). `ReportReason` reshaped per the matrix. New `ReportResolution`. Delete `ReportCategory`.
- `Contracts\Reportable` gains `reportSubject(): User` (the one accountable user); `HasReport` on Pet and User. `reportSubjects()` already exists on Comment/Review; keep it, add the singular.
- `User::upheldReportsCount(?CarbonInterval $window)`: count of `reports` where `subject_user_id = id and status = resolved` (optionally `resolved_at` within window).

## Backend
- `PersistReport` fills `subject_user_id` from `reportSubject()`.
- New `app/Actions/Reports/ResolveReport.php` running `app/Pipelines/Reports/ResolveReport/`: `RecordDecision` (status, resolution, note, resolved_by, resolved_at on the report and on sibling pending reports for the same target or, for deactivation, the same subject) → `ApplyResolution` (match on resolution → existing `DeletePet` / `PurgePet` / `DeleteComment` / `DeleteReview` / `DeactivateUser` Actions; warn and dismiss apply nothing) → `NotifySubject` → `NotifyReporters`. One `DB::transaction` opened by the caller (the Nova action), per `.ai/rules/nova-actions.md`. RecordDecision runs before ApplyResolution so the rows exist when the cleanup pipelines run; those pipelines change to `->pending()` only.
- `app/Actions/Reports/DismissReport.php` is `ResolveReport` with status rejected; same pipeline, no ApplyResolution work. One Action with a nullable resolution is enough; do not create two.
- Notifications (database; mail added for deactivation only): `ReportResolvedNotification` (to reporters; payload `outcome: upheld|dismissed`, `reportable_type`, reason), `ModerationNoticeNotification` (to subject; payload `kind: warning|content_removed|account_deactivated`, `reportable_type`, `reason`, `note`). Translation keys only, per `.ai/rules/notifications.md`.
- Decision 6: a `whereHas('user', active)` scope applied in the feed, pet show, profile show and the comment/review loaders. Measure the feed query before and after; if it costs, fall back to setting `pets.status = unavailable` inside `DeactivateUser` and restoring nothing on reactivate (owner re-publishes).

## Routes
No new routes. `POST reports/{type}/{id}` now accepts `pet` and `user`. `GET api/v1/options` ships `report_reasons` keyed by target type instead of a flat list.

## Frontend (user interface)
1. **Entry points.** Pet listing page: "Report listing" in the listing's overflow menu (next to share), hidden for the owner and for guests. Profile page: "Report profile" in the header overflow menu, hidden on your own profile. Comments and reviews keep the existing flag. Every entry point opens the same `ReportDialog`.
2. **`ReportDialog`.** Drop the category select. `reasons` prop becomes the subset for `reportableType` (page prop `reportReasons[type]`). Add a one-line hint under the reason for pet reports ("If the animal is in danger contact [rescue line] directly; we review reports within 24h"). Keep the free-text details. Success toast: "Thanks, we'll let you know what we decide."
3. **Already reported.** `has_reported` is already loaded for comments/reviews; add it to `PetDetailResource` and the profile resource so the menu item reads "Reported" and is disabled.
4. **Notifications inbox.** `NotificationRow` learns three rows: report resolved (link to the content if it still exists, else no link), warning (link to the guidelines page), content removed (link to the guidelines page). Account deactivation is mail only.
5. **Guidelines page.** One page in the existing docs site (`routes/docs.php`): what is not allowed per target, what happens on each strike, how to appeal (reply to the mail / open a support ticket). Every moderation notice links here. The listing/welfare policy Mohamed owes is this page; the moderators apply it.
6. **Removed listing.** Soft-deleted, so it is simply gone from "My listings"; the notification is the explanation. No banner UI. Appeals go through support tickets, which already exist.
7. **Locale.** EN + AR keys for every new reason label, notification row and the guidelines page.

## Admin (Nova)
1. **`Report` resource.** `MorphTo` types add `Pet` and `User`. New fields: `Resolution` badge, `Moderator Note`, `Resolved By`, `Resolved At`; detail shows **"Strikes against subject: N (M in last 90 days)"** and **"Other pending reports on this target: K"** (one `withCount` each). Default order becomes pending → high-priority reason → oldest.
2. **`ResolveReport` action** replaces `ChangeReportStatus`. Fields: `Decision` select (Dismiss, Warn, Remove content, Purge content, Deactivate account; options narrowed to what the target allows via `dependsOn` on the resource type), `Moderator note` (shown to the subject when warning/removing; required for Warn), `Notify reporter` (default on). Runs the app Action inside one transaction with the standard `Throwable` → `ActionResponse::danger` sentence. Bulk-safe: each report in the selection resolves its own target.
3. **`ReopenReport`** small action: status back to pending, clears resolution (for a mistaken dismissal). Not needed for undoing a removal: `ReactivateUser` exists, soft-deleted pets can be restored from the Pet resource with trashed shown.
4. **Filters.** Drop `ReportCategoryFilter`; add `ReportableTypeFilter` and `ReportResolutionFilter`. `ReportReasonFilter` stays.
5. **`User` resource.** `HasMany` "Reports against" (via `subject_user_id`) and a `Strikes` number on detail. The existing `DeactivateUser` help text points moderators to resolve the report instead so the reporter gets notified.
6. **Pet / Comment / Review resources.** `HasMany` "Reports" panel so a moderator who lands on the content sees why it was flagged.
7. **Moderation dashboard.** `PendingReports` and `ReportsByStatus` stay; add `Partition` by reportable type. Nothing else.
8. **Audit.** `resolved_by` + `resolved_at` + `moderator_note` is the audit log for moderation. Closes that line of `nova-admin.md` open work for reports only.

## Guards
- `throttle:reports` stays 5/min; add a daily cap of 20 per user (RateLimitServiceProvider) so one account cannot flood the queue.
- Self-report and duplicate guards unchanged; `EnsureNotSelfReport` compares against `reportSubject()` so an owner cannot report their own listing or profile.
- Reporter identity is never shown to the subject. Notification payloads to the subject carry reason and note only.
- `moderator_note` is sanitised like comment content before it is stored (reuse `Pipelines/Comments/Shared/CleanContent`).

## Tests
- `tests/Feature/Actions/Reports/ResolveReportTest.php`: one dataset per (target × resolution) row of the matrix asserting the side effect, the sibling resolution, the two notifications, the strike count, and that a dismissed report sends only the reporter notification.
- `CreateReportTest`: pet and user targets; reason outside `forTarget` → 422; self-report of own listing → 422; `subject_user_id` filled.
- `tests/Feature/Nova/Actions/ResolveReportTest.php`: transaction rollback sentence; options narrowed per type.
- Cleanup pipelines: resolved reports survive `DeleteComment` / `PurgePet`, pending ones do not.
- Decision 6: deactivated owner's listing is absent from feed and 404 on show.
- Prop contract: `pets/Show` and `profile/Show` carry `reportReasons` keyed by type (extend `CommentsDialogPropContractTest`).

## Delivery order
1. Data + enums + `subject_user_id` + Pet/User reportable + `ResolveReport` + notifications + cleanup pipelines keep resolved rows. (backend, tests)
2. Nova: resource fields, `ResolveReport` action, filters, user strikes, HasMany panels. (backend, tests)
3. UI: entry points on listing and profile, dialog reasons per type, notification rows, guidelines page, EN/AR. (frontend, prop-contract tests)
4. API v1 options + docs; mobile app picks up the new targets and keyed reasons.
5. Decision 6 (inactive owners hidden) can ship with step 1 if the feed query cost is acceptable; otherwise with step 2.

## Open work (after this plan)
- Thresholds: auto-hide a listing at 3 distinct high-priority reports in 24h, auto-flag a user at 3 strikes in 90 days. Needs real data first.
- Reporting a message inside a conversation.
- Appeal flow beyond "open a support ticket".
- Nova permission tiers (moderator vs owner) are still a single role.
