<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('a thread with nobody left on the other side accepts no messages', function () {
    $sender       = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->users()->attach($sender);

    expect($conversation->acceptsMessagesFrom($sender))->toBeFalse();
});

test('the database refuses a second direct thread for the same pair', function () {
    Conversation::factory()->create(['pair_key' => '1:2']);

    expect(fn () => Conversation::factory()->create(['pair_key' => '1:2']))
        ->toThrow(UniqueConstraintViolationException::class);
});
