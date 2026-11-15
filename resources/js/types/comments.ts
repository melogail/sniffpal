import type { UserSummary } from './users';

/**
 * One comment — `App\Http\Resources\Comment\CommentResource`.
 *
 * The single comment payload in the application: a feed card's preview, the
 * detail page's thread, `comments.index` and `comments.replies` all emit this
 * exact shape, so there is one reader for all four.
 *
 * `author` is `CommentAuthorResource`, which is `UserSummaryResource` under
 * another name, and is **absent — not null —** when the loader did not eager
 * load `user.media`.
 *
 * `likes_count`, `is_liked`, `replies_count` and `has_reported` come from
 * subqueries on whatever query loaded the comment and fall back to neutral
 * values rather than lazy loading, so they are always present.
 */
export type CommentPreview = {
    id: number;
    content: string;
    /** Null on a top-level comment; the parent's id on a reply. */
    parent_id: number | null;
    author?: UserSummary;
    is_author: boolean;
    likes_count: number;
    is_liked: boolean;
    /** The true total, not the length of any `replies` preview. */
    replies_count: number;
    has_reported: boolean;
    created_at: string;
    updated_at: string;
};

/**
 * A top-level comment with a bounded preview of its replies.
 *
 * Threads are exactly **two levels deep** — `ValidateParentBelongsToCommentable`
 * refuses a reply to a reply — so a `CommentPreview` inside `replies` never
 * carries replies of its own.
 *
 * `replies` is absent where none were loaded: a feed card carries none at all,
 * the detail page carries `petconnect.pets.detail_reply_preview` per comment,
 * and the rest are paged in from `comments.replies`.
 */
export type Comment = CommentPreview & {
    replies?: CommentPreview[];
};

/** What `comments.index` / `comments.store` take as their morph-type segment. */
export type CommentableType = 'pet';

/**
 * `App\Actions\Comments\BroadcastCommentThreadChange` — the public
 * `comments.{type}.{id}` channel's one event. Ids and a count only; a listener
 * refetches through the gated endpoints rather than trusting the wire.
 */
export type CommentThreadChanged = {
    action: 'created' | 'updated' | 'deleted';
    comment_id: number;
    parent_id: number | null;
    /** Roots and replies — what the thread header pill shows. */
    comments_count: number;
};
