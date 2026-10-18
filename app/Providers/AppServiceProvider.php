<?php

namespace App\Providers;

use App\Dominio\Geo\Clientes\NominatimClient;
use App\Dominio\Geo\Clientes\OsrmClient;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            OsrmClient::class,
            fn () => new OsrmClient(config('juego.osrm.base_url')),
        );

        $this->app->singleton(
            NominatimClient::class,
            fn () => new NominatimClient(config('juego.nominatim.base_url')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
