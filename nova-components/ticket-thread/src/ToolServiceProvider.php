<?php

namespace Sniffpal\TicketThread;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event): void {
            Nova::mix('ticket-thread', __DIR__.'/../dist/mix-manifest.json');
        });
    }

    /**
     * Register the tool's routes.
     *
     * Behind `nova.api_middleware`, not the `nova` group: the latter is the
     * web stack alone (NovaCoreServiceProvider), and only the former carries
     * Nova's Authenticate and Authorize. Same stack as `/nova-api`.
     */
    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(config('nova.api_middleware'))
            ->prefix('nova-vendor/ticket-thread')
            ->group(__DIR__.'/../routes/api.php');
    }
}
