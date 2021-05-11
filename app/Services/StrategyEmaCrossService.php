<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Integer;

class StrategyEmaCrossService {

    protected $exchangeService;
    protected $lakshmiService;

    public function __construct(LakshmiService $lakshmiService, BinanceApiService $binanceApiService) {
        $this->exchangeService = $binanceApiService;
        $this->lakshmiService = $lakshmiService;
    }


    public function strategy($symbol, $timeframe) {

        Log::info("Checking strategy ...");


    }
}
