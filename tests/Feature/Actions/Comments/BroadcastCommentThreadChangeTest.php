<?php

use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\UpdateComment;
use App\Enums\Commentable;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Broadcasting\AnonymousEvent;
use Illuminate\Support\Facades\Event;

/**
 * The one broadcast every open thread listens for, asserted by what reaches the
 * wire: the public per-thread channel, the event name `CommentList.vue`
 * subscribes to, and a payload of ids and a count, never content.
 *
 * @param  array<string, mixed>  $payload
 */
function assertThreadChangeBroadcast(Pet $pet, array $payload): void
{
    Event::assertDispatched(AnonymousEvent::class, function (AnonymousEvent $event) use ($pet, $payload): bool {
        return $event->broadcastOn() === ["comments.pet.{$pet->getKey()}"]
            && $event->broadcastAs() === 'CommentThreadChanged'
            && $event->broadcastWith() === $payload;
    });
}

beforeEach(function () {
    Event::fake([AnonymousEvent::class]);
});

test('publishing a reply broadcasts its ids and the thread total on the listing channel', function () {
    $pet    = Pet::factory()->create();
    $parent = Comment::factory()->for($pet, 'commentable')->create();

    $reply  = app(CreateComment::class)->handle(
        author: User::factory()->create(),
        commentableType: Commentable::Pet,
        commentableId: $pet->getKey(),
        content: 'Is she vaccinated?',
        parentId: $parent->getKey(),
    );

    assertThreadChangeBroadcast($pet, [
        'action'         => 'created',
        'comment_id'     => $reply->getKey(),
        'parent_id'      => $parent->getKey(),
        'comments_count' => 2,
    ]);
});

test('editing a comment broadcasts an update without the new text', function () {
    $pet     = Pet::factory()->create();
    $comment = Comment::factory()->for($pet, 'commentable')->create();

    app(UpdateComment::class)->handle($comment, 'Edited text');

    assertThreadChangeBroadcast($pet, [
        'action'         => 'updated',
        'comment_id'     => $comment->getKey(),
        'parent_id'      => null,
        'comments_count' => 1,
    ]);
});

test('deleting a root broadcasts a count that no longer includes its replies', function () {
    $pet   = Pet::factory()->create();
    $root  = Comment::factory()->for($pet, 'commentable')->create();
    Comment::factory()->for($pet, 'commentable')->for($root, 'parent')->create();
    $other = Comment::factory()->for($pet, 'commentable')->create();

    app(DeleteComment::class)->handle($root);

    assertThreadChangeBroadcast($pet, [
        'action'         => 'deleted',
        'comment_id'     => $root->getKey(),
        'parent_id'      => null,
        'comments_count' => 1,
    ]);
    expect(Comment::query()->pluck('id')->all())->toBe([$other->getKey()]);
});
