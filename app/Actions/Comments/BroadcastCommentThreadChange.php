<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use Illuminate\Support\Facades\Broadcast;

/**
 * Tell every open tab showing a discussion that one of its comments changed.
 *
 * The channel is public, because guests read threads too and a listing's
 * thread is as visible as the listing. That is why the payload carries ids and
 * a count and never content: a hidden listing's comments must not leak to
 * whoever guesses its id, so the client refetches through `comments.index` /
 * `comments.replies`, which apply the listing's visibility rule.
 *
 * Called by CreateComment, UpdateComment and DeleteComment after their
 * pipelines have committed; the delete calls it with the row already gone, so
 * the count is a query and never a relation on the comment.
 */
class BroadcastCommentThreadChange
{
    public function handle(Comment $comment, string $action): void
    {
        Broadcast::on("comments.{$comment->commentable_type}.{$comment->commentable_id}")
            ->as('CommentThreadChanged')
            ->with([
                'action'         => $action,
                'comment_id'     => $comment->getKey(),
                'parent_id'      => $comment->parent_id,
                'comments_count' => Comment::query()
                    ->where('commentable_type', $comment->commentable_type)
                    ->where('commentable_id', $comment->commentable_id)
                    ->count(),
            ])
            ->send();
    }
}
