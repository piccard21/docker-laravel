<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LakshmiService;

class UpdateExchangeInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lakshmi:updateExchangeInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates DB entry of the exchangeinfo';

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
        $lakshmiService->updateExchangeInfo();
        $this->info('lakshmi:updateExchangeInfo finished ;-)');
        return 0;
    }
}
