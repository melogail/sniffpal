# Social & moderation

Every social interaction is a polymorphic relation keyed by a morph alias, authorised by a policy, throttled by name, and reachable from both the web and API v1.

## Likes and saves

| Target | Web route | Limiter |
| --- | --- | --- |
| Listing | `POST pets/{pet}/like` | `pet-likes` |
| Comment | `POST comments/{comment}/like` | `comment-likes` |
| Profile | `POST profile/{user}/like` | `profile-likes` |

Likes toggle. Saves are bookmarks on listings, kept in `saves`. Both need a verified account. The `HasLikes` concern provides the relation and the toggle on each model; `ModelLikedNotification` tells the owner.

## Comments

- Polymorphic `commentable`; today only `pet` (`Commentable` enum).
- Threaded: `comments/{comment}/replies` lists a subtree; `ListCommentSubtreeIds` walks it for deletion.
- Content passes through the Comments pipeline's `CleanContent` step (trim, strip, profanity filter).
- Owners edit and delete their own comments; the listing owner is notified with `ModelCommentedNotification`.
- Nova's `DeleteCommentThread` removes a comment with its replies and their likes and reports.

## Reviews

- Polymorphic `reviewable`; today only `user` profiles (`Reviewable` enum).
- One rating plus a comment sanitised by `SanitizeReviewComment`.
- `ModelReviewedNotification` goes to the profile owner.
- Nova's `DeleteReview` is the irreversible path.

## Reports

Members report listings, profiles, comments and reviews (`Reportable` enum) with a per-target `ReportReason` and free text. `ReportFiledNotification` alerts staff. The report records `subject_user_id` (the accountable member) so decisions survive the content being deleted; resolved reports are the strike record (`User::strikeCount()`). Reporters get `ReportResolvedNotification`; the subject gets `ModerationNoticeNotification` (mail when deactivated).

Moderation happens in Nova:

- `Report` resource with filters by status, target type, reason and resolution; pending and high-priority reasons first
- `ResolveReport`: one decision select (review / dismiss / warn / remove / purge / deactivate) that runs `App\Actions\Reports\ResolveReport`, closes sibling reports and notifies both sides; `ReopenReport` undoes the record
- `PurgeOrphanedReports` removes reports whose target is gone
- Content actions (`DeleteCommentThread`, `DeleteReview`, `PurgePetListing`) and account actions (`DeactivateUser`, `DeleteUserAccount`) complete the toolset

## Support tickets

Two channels, decided by who is asking. A **member** opens a ticket: a `Ticket` header (`TicketCategory`: technical issue, account issue, listing issue, missing category, missing breed, feedback, other; `TicketStatus`: open = waiting on the team, answered = waiting on the sender, closed) with a thread of `TicketMessage` rows authored by the member or an admin. The Support page and the member's tickets page open one through the same dialog. A **guest** sends a one-off `ContactMessage` (name, email, subject, message) through the contact dialog on the Support page, `POST contact` (`contact-messages` limiter, IP-keyed), answered by email from Nova.

| Route | Limiter |
| --- | --- |
| `POST settings/tickets` | `ticket-messages` |
| `GET settings/tickets` / `GET settings/tickets/{ticket}` | — |
| `POST settings/tickets/{ticket}/reply` | `content-edits` |
| `POST settings/tickets/{ticket}/close` | `content-edits` |

Nova's Support section holds `Ticket` (badged with the open count) and `ContactMessage`. A ticket is worked with two actions: `ReplyToTicket` appends the admin's message and marks it answered, `ChangeTicketStatus` closes or reopens. Both go through `Actions\Tickets\*`, the same code a member's follow-up takes, and tell the member with `TicketRepliedNotification` — bell plus email, or email alone to the address on the row once the account is deleted.

## Notifications

Database notifications per user, queued after the write commits and broadcast on the user's private channel, read in the bell menu and at `notifications`:

| Route | Limiter |
| --- | --- |
| `POST notifications/{notification}/read` | `content-edits` |
| `POST notifications/read-all` | `inbox-actions` |
| `DELETE notifications` | `inbox-actions` |

`notificationMessage.ts` turns each notification type into a localised line on the client.
