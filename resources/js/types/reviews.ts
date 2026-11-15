import type { SelectOption } from './pets';
import type { UserSummary } from './users';

/**
 * One review — `App\Http\Resources\Review\ReviewResource`.
 *
 * `author` is `ReviewAuthorResource`, which is `UserSummaryResource` under
 * another name, and is absent (not null) when the loader did not eager load
 * `user.media`.
 */
export type Review = {
    id: number;
    rate: number;
    comment: string | null;
    author?: UserSummary;
    is_author: boolean;
    has_reported: boolean;
    can_edit: boolean;
    can_delete: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * The review scale and comment ceiling — the `reviewBounds` prop on
 * `profile.show`.
 *
 * Built by `App\Concerns\ReviewValidationRules::reviewBounds()` from the same
 * accessors the `min:` / `max:` rules are built from, so the star widget and
 * the validator cannot disagree. Nothing may hardcode 5 again.
 */
export type ReviewBounds = {
    min_rate: number;
    max_rate: number;
    max_comment_length: number;
};

export type ReportReason =
    | 'spam'
    | 'harassment'
    | 'hate_speech'
    | 'false_information'
    | 'inappropriate_content'
    | 'scam_or_fraud'
    | 'animal_welfare'
    | 'prohibited_animal'
    | 'misleading_listing'
    | 'duplicate_listing'
    | 'no_longer_available'
    | 'impersonation'
    | 'fake_review'
    | 'other';

/** What `reports.store` takes as its morph-type segment. */
export type ReportableType = 'comment' | 'review' | 'pet' | 'user';

/**
 * The reason options a report dialog offers, keyed by target type.
 *
 * A page prop on every page that hosts a report control (`home`, `pets.show`,
 * `profile.show`), not a separate endpoint, so a page has to accept and
 * forward it. `ReportDialog` picks the list for its own `reportableType`.
 */
export type ReportReasonsByType = Record<
    ReportableType,
    SelectOption<ReportReason>[]
>;

/** What `reviews.store` takes as its morph-type segment. */
export type ReviewableType = 'user';
