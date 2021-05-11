<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class BinanceApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(BinanceApiService::class, function ($app) {
            return new BinanceApiService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
