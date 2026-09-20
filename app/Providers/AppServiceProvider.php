<?php

namespace App\Providers;

use App\Contracts\HotspotObservationStore;
use App\Contracts\RouteProvider;
use App\Services\DatabaseHotspotObservationStore;
use App\Services\OsrmRouteProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HotspotObservationStore::class, DatabaseHotspotObservationStore::class);
        $this->app->bind(RouteProvider::class, OsrmRouteProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
