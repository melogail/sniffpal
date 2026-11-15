<?php

namespace App\Enums;

use App\Concerns\ResolvesMorphTarget;
use App\Models\Comment;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Whitelist of the models that may be reported.
 *
 * Backing values mirror the morph map aliases registered in AppServiceProvider.
 */
enum Reportable: string
{
    use ResolvesMorphTarget;

    case Comment = 'comment';
    case Review  = 'review';
    case Pet     = 'pet';
    case User    = 'user';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Comment => Comment::class,
            self::Review  => Review::class,
            self::Pet     => Pet::class,
            self::User    => User::class,
        };
    }
}
