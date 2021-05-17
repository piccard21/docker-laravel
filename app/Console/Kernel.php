<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->command('run-report')->weekly()->mondays()->at('12:00')->timezone('Pacific/Auckland');
        $schedule->command('lakshmi:updateExchangeInfo')->hourlyAt(21);

        // $schedule->command('inspire')->hourly();
        //$schedule->command('lakshmi:trade')->everyMinute();
        //$schedule->command('lakshmi:trade')->everyFiveMinutes();
        $schedule->command('lakshmi:trade')->hourly();
        //$schedule->command('lakshmi:trade')->hourlyAt(1);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
