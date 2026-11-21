<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove the undecided reports filed against the member's content and profile.
 *
 * `reports.reportable_id` is a morph column with no foreign key, so nothing
 * cascades these. Only **pending** rows go: a resolved report is the moderation
 * record (strikes, "resolved by", the note) and is kept with a null target and,
 * through `subject_user_id`'s nullOnDelete, a null subject. Reports the member
 * filed themselves cascade on `reports.user_id`.
 */
class DeleteContentReports
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        $targets = [
            Relation::getMorphAlias(Review::class)  => $context->reviewIds(),
            Relation::getMorphAlias(Comment::class) => $context->commentIds(),
            Relation::getMorphAlias(Pet::class)     => $context->petIds(),
            Relation::getMorphAlias(User::class)    => [$context->user->getKey()],
        ];

        Report::query()
            ->pending()
            ->where(function (Builder $query) use ($targets): void {
                foreach ($targets as $type => $ids) {
                    $query->orWhere(fn (Builder $query): Builder => $query
                        ->where('reportable_type', $type)
                        ->whereIn('reportable_id', $ids));
                }
            })
            ->forceDelete();

        return $next($context);
    }
}
