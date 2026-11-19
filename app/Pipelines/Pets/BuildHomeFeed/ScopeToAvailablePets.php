<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use App\Models\User;
use Closure;

/**
 * The home feed only ever shows listings that are still available; an owner
 * marking a pet unavailable is what takes it out of discovery.
 */
class ScopeToAvailablePets
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        $context->query
            ->available()
            ->whereHas('user', User::activeOnly(...));

        return $next($context);
    }
}
