<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Integer;

class StrategyEmaCrossService {

    public function __construct() { }



    public function strategy($symbol, $timeframe) {

        // yes, update exactly here, 'cause we need all the close-prices
        $this->updateSymbolHistory($job->symbol, $job->settings["timeframe"]);

        Log::info("Checking strategy ...");

        // ok ... let's trade
        $accountInfo = $this->exchangeService->accountInfo();
        $response = $this->canTrade($job->symbol, $job->next);

        // TODO ... irgendwie sollte das alles in die DB!!!!!
        if ($response["errorBag"]->isNotEmpty()) {
            // TODO AUSNAMHE
            if ($response["errorBag"]->count() === 1 && $response["errorBag"]->has("filter-market_lot_size-SELL-too-much")) {
                $isTooMuchBase = true; // $symbolFilters['MARKET_LOT_SIZE']['maxQty']
            } else {
                throw new \Exception("Errors in canTrade()");
            }
        }
    }
}
