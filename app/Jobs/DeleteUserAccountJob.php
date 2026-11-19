<?php

namespace App\Jobs;

use App\Actions\Profiles\DeleteUserAccount;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Self-service account deletion runs off the request: a seller with hundreds
 * of photos would otherwise delete every S3 object inside one web request and
 * time out half way. The controllers sign the member out and revoke their
 * tokens first, so the account is unreachable while the purge runs.
 */
class DeleteUserAccountJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    public function handle(DeleteUserAccount $deleteUserAccount): void
    {
        $user = User::query()->find($this->userId);

        if ($user instanceof User) {
            $deleteUserAccount->handle($user);
        }
    }
}
