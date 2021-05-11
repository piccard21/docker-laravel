<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\LakshmiService;

class LakshmiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(LakshmiService::class, function ($app) {
            return new LakshmiService();
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
