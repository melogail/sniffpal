<?php

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use App\Notifications\ModelCommentedNotification;
use App\Notifications\ModelLikedNotification;
use App\Notifications\ModelReviewedNotification;
use App\Notifications\NewMessageNotification;
use App\Notifications\ReportFiledNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

/**
 * Notifications leave the request through the queue. The in-app ones wait for
 * the surrounding transaction to commit so a worker on a separate connection
 * (Redis in production) never looks up a row that is not visible yet.
 */
test('every in-app notification is queued after commit', function (string $class) {
    expect(is_subclass_of($class, ShouldQueueAfterCommit::class))->toBeTrue();
})->with([
    ModelLikedNotification::class,
    ModelCommentedNotification::class,
    ModelReviewedNotification::class,
    NewMessageNotification::class,
    ReportFiledNotification::class,
]);

test('the verification mail is queued', function () {
    expect(is_subclass_of(VerifyEmailNotification::class, ShouldQueue::class))->toBeTrue();
});

test('liking a listing queues the owner notification instead of sending it inline', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $pet   = Pet::factory()->for($owner)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('pets.like', $pet))
        ->assertRedirect();

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof ModelLikedNotification
        && $job->notifiables->contains($owner));
});

test('like and comment notifications are dropped, not failed, when their model is gone before the worker runs', function () {
    $liked     = new SendQueuedNotifications(User::factory()->create(), new ModelLikedNotification(Like::factory()->create()));
    $commented = new SendQueuedNotifications(User::factory()->create(), new ModelCommentedNotification(Comment::factory()->create()));

    expect($liked->deleteWhenMissingModels)->toBeTrue()
        ->and($commented->deleteWhenMissingModels)->toBeTrue();
});
