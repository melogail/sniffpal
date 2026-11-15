<?php

namespace App\Pipelines\Comments\DeleteCommentThread;

use App\Models\Comment;
use App\Models\Report;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove the **pending** reports filed against every comment in the subtree.
 *
 * The sharpest of the orphan cases. `reports` has, like `likes`, no foreign key
 * on the morph id, so nothing in the schema removes these when the comment
 * goes. Left behind, each one sits in the moderation queue with `reportable`
 * resolving to null — an item nobody can act on or dismiss, and one more of
 * them every time a parent is deleted.
 *
 * This used to add that comment ids are recycled, so a stranded report
 * eventually collides with a genuine one through
 * `unique (user_id, reportable_type, reportable_id)`. That is retracted:
 * `$table->id()` emits `integer primary key autoincrement` on SQLite, verified
 * on the live `comments` and `reports` tables, and AUTOINCREMENT is what stops
 * an id being reused. See .ai/rules/migrations.md before repeating it. The
 * dangling queue item is reason enough for this step.
 *
 * Decided reports (resolved or rejected) are kept on purpose: the row carries
 * `subject_user_id`, the resolution, the moderator and the note, and is the
 * strike record `User::strikeCount()` reads. A decided report whose target is
 * gone is expected, not an orphan; only an undecided one is.
 */
class DeleteSubtreeReports
{
    public function handle(DeleteCommentThreadContext $context, Closure $next): mixed
    {
        Report::query()
            ->where('reportable_type', Relation::getMorphAlias(Comment::class))
            ->whereIn('reportable_id', $context->subtreeIds())
            ->pending()
            ->delete();

        return $next($context);
    }
}
