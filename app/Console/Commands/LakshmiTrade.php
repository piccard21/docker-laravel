<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LakshmiService;

class LakshmiTrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lakshmi:trade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and hopefully trade.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(LakshmiService $lakshmiService)
    {
        $lakshmiService->trade();
        $this->info('lakshmi:trade finished ;-)');
        return 0;
    }
}
