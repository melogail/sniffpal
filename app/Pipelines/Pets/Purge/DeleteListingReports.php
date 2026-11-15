<?php

namespace App\Pipelines\Pets\Purge;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every **pending** report filed against this listing and against the
 * comments on it. Decided reports stay as the moderation record (see
 * Comments\DeleteCommentThread\DeleteSubtreeReports).
 *
 * `reports.reportable_id` is a morph column with no foreign key, so deleting a
 * comment strands its report, and the `comments.parent_id` cascade strands the
 * reports of every reply it takes without an event.
 *
 * A stranded report sits in the moderation queue with `reportable` resolving to
 * null: an item nobody can act on or dismiss. Clearing it afterwards now needs
 * a second destructive action (Nova\Actions\PurgeOrphanedReports), which is
 * exactly the work this step exists to avoid creating.
 *
 * The alias comes from `Relation::getMorphAlias()`, never a class name: the
 * morph map is enforced, so `reportable_type` holds `comment`, and a class-name
 * comparison would delete nothing and report success.
 */
class DeleteListingReports
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        Report::query()
            ->where('reportable_type', Relation::getMorphAlias(Pet::class))
            ->where('reportable_id', $context->pet->getKey())
            ->pending()
            ->delete();

        $commentIds = $context->commentIds();

        if ($commentIds !== []) {
            Report::query()
                ->where('reportable_type', Relation::getMorphAlias(Comment::class))
                ->whereIn('reportable_id', $commentIds)
                ->pending()
                ->delete();
        }

        return $next($context);
    }
}
