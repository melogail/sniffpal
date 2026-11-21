<?php

use App\Models\Comment;
use Illuminate\Support\Str;

test('soft deleting a comment leaves its replies to the thread pipeline', function () {
    $parent = Comment::factory()->create();
    $reply  = Comment::factory()->reply($parent)->create();

    $parent->delete();

    $this->assertSoftDeleted($parent);
    $this->assertModelExists($reply);
});

test('force deleting a comment deletes its replies', function () {
    $parent = Comment::factory()->create();
    $reply  = Comment::factory()->reply($parent)->create();

    $parent->forceDelete();

    $this->assertDatabaseMissing('comments', ['id' => $reply->getKey()]);
});

test('force deleting a comment deletes replies nested below its replies', function () {
    $parent      = Comment::factory()->create();
    $reply       = Comment::factory()->reply($parent)->create();
    $nestedReply = Comment::factory()->reply($reply)->create();

    $parent->forceDelete();

    $this->assertDatabaseEmpty('comments');
    $this->assertModelMissing($nestedReply);
});

test('deleting a reply leaves its parent alone', function () {
    $parent = Comment::factory()->create();
    $reply  = Comment::factory()->reply($parent)->create();

    $reply->delete();

    $this->assertModelExists($parent);
});

test('stores a comment longer than 255 characters intact', function () {
    $content = Str::repeat('a', 300);

    $comment = Comment::factory()->create(['content' => $content]);

    expect($comment->fresh()->content)->toBe($content);
});
