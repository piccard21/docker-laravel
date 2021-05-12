<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\StrategyEmaCrossService;

class StrategyEmaCrossProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(StrategyEmaCrossService::class, function ($app) {
            return new StrategyEmaCrossService();
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
