<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon authorization callback.
     *
     * The parent also admits anyone in the `local` environment; that bypass is
     * dropped so the gate below is the only thing deciding, in every environment.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in every environment.
     *
     * The subject Horizon passes in is resolved from the default guard, so it is
     * null for an admin; the answer must come from the `admin` guard's session
     * alone. See tests/Feature/Providers/HorizonServiceProviderTest.php.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (mixed $user = null): bool => Auth::guard('admin')->check());
    }
}
