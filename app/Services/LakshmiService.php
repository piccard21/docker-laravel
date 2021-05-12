<?php

namespace App\Services;

use App\Models\Symbol;
use App\Models\Job;
use App\Models\JobLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

class LakshmiService {

    protected $exchangeService;
    public $exchangeInfo = [];
    protected $availableAsset = [];

    //protected $accountInfo;

    public function __construct() {
        $this->exchangeService = app(BinanceApiService::class);
    }

    /**
     * get history of a symbol
     *
     * @param string $symbol
     * @param string $timeframe
     * @return array
     */
    public function getSymbolHistory(string $symbol, string $timeframe, Carbon $from = null, Carbon $to = null) {
        Log::debug("Getting symbol history of $symbol in timeframe $timeframe");

        $symbolModel = Symbol::setCollection($symbol);

        $result = $symbolModel->select('time', 'open', 'close', 'high', 'low')
            ->where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->when($from, function($query, $from) {
                $query->where('time', '>=', intval($from->getPreciseTimestamp(3)));
            })
            ->when($to, function($query, $to) {
                $query->where('close_time', '<=', intval($to->getPreciseTimestamp(3)));
            })
            ->orderBy('time', 'asc')
            ->get()
            ->toArray();

        Log::debug("Successfully fetched symbol history of $symbol in timeframe $timeframe");

        return $result;
    }

    /**
     * fill symbol table with latest candles
     *
     * @param $symbol
     * @param $timeframe
     */
    public function updateSymbolHistory($symbol, $timeframe) {

        Log::info("Going to update history of $symbol with timeframe of $timeframe");

        // get last entry of combination
        $symbolModel = Symbol::setCollection($symbol);

        $lastEntry = $this->getLastSymbolEntry($symbol, $timeframe);

        $openTime = null;
        if ($lastEntry) {
            $openTime = $lastEntry->time;
            $closeTime = $lastEntry->close_time;
            $lastEntry->delete();
        }

        $openTimeFormatted = Carbon::createFromTimestamp(intval($openTime / 1000))->format('Y-m-d H:i:s e');
        $closeTimeFormatted = Carbon::createFromTimestamp(intval($closeTime / 1000))->format('Y-m-d H:i:s e');
        Log::info("Last entry for $symbol/$timeframe:");
        Log::info("- open time: $openTimeFormatted ($openTime)");
        Log::info("- close time: $closeTimeFormatted ($closeTime)");

        $last = null;
        do {
            $klines = $this->exchangeService->gethistoricaldata($symbol, $timeframe, $openTime);
            $klinesNr = count($klines);

            if ($klinesNr) {
                $symbolModel->insert($klines);
            }

            // still 1000 so there are more
            if ($klinesNr === 1000) {
                // get the last startTime and add a millisecond, so we won't get the same
                $openTime = $klines[999]['open_time'] + 1;
            } else {
                $last = end($klines);
            }
        } while ($klinesNr === 1000);

        // rename open_time to time
        DB::connection('mongodb')
            ->collection($symbol)
            ->where('symbol', $symbol)
            ->where('timeframe', $timeframe)->update([
                '$rename' => ['open_time' => 'time']
            ]);

        Log::info("Updated historcial data of $symbol with timeframe $timeframe");
        Log::info("Current entry for $symbol/$timeframe:");

        $openTimeFormatted = Carbon::createFromTimestamp(intval($last["open_time"] / 1000))->format('Y-m-d H:i:s e');
        $closeTimeFormatted = Carbon::createFromTimestamp(intval($last["close_time"] / 1000))->format('Y-m-d H:i:s e');

        Log::info("- open time: " . $openTimeFormatted . " (" . $last["open_time"] . ")");
        Log::info("- close time: " . $closeTimeFormatted . " (" . $last["close_time"] . ")");
    }

    private function getLastSymbolEntry($symbol, $timeframe) {
        $symbolModel = Symbol::setCollection($symbol);

        return $symbolModel->where([
            "symbol" => $symbol,
            "timeframe" => $timeframe
        ])
            ->orderBy('time', 'desc')
            ->first();
    }

    /**
     * get available asset of this job
     *
     * @param $job
     * @return array
     */
    public function setAvailableAsset($job) {

        Log::info("Getting available asset for job $job->id ($job->symbol/$job->timeframe)");

        $this->availableAsset = [
            "base" => 0,
            "quote" => 0,
        ];

        // not active yet OR no logs
        if (($job->status !== 'ACTIVE' && $job->status !== 'INACTIVE') || !JobLog::count()) {
            $available = [
                "base" => 0,
                "quote" => $job->start_price
            ];
        }

        /**
         * TODO wir nehmen an, dass wir immer 100% kaufen/verkaufen
         *
         * - BUY:
         *  "executedQty": "0.00718000",
         *  "cummulativeQuoteQty": "24.98898480"
         *
         *  - base: executedQty
         *  - quote: 0
         *
         * - SELL:
         *  "executedQty": "0.00718000",
         *  "cummulativeQuoteQty": "25.36212940"
         *
         * - base: 0
         * - quote: cummulativeQuoteQty
         */

        // get last job_log
        $lastJob = $job->logs()->whereIn('method', ['BUY', 'SELL'])->orderBy('time', 'desc')->first();

        // should be also valid for inactive jobs
        if ($job->next === "BUY") {
            $this->availableAsset  = [
                "base" => 0,
                "quote" => $lastJob->message["cummulativeQuoteQty"],
            ];

        } else if ($job->next === "SELL") {
            $this->availableAsset  = [
                "base" => $lastJob->message["executedQty"],
                "quote" => 0
            ];
        }

        Log::info("- base: " . $this->availableAsset ["base"] . " $job->base");
        Log::info("- quote: " . $this->availableAsset ["quote"] . " $job->quote");
    }

    /**
     * checks if startegy can be triggered now
     *
     * @param $job
     * @return bool
     * @throws \Exception
     */
    private function canStrategyTriggeredNow($job) {
        Log::info("Checking if strategy can be triggered now...");

        // TODO ... stimmt das .. .was wenn es zwischenzeitlich einen Ausfall gab
        // prüfen ob es Kerzen danach gibt
        // wenn ja auf WIATING/READY umstellen?

        $symbolModel = Symbol::setCollection($job->symbol);

        $entry = $symbolModel->where([
            ['time', '<=', $job->lastTimeTriggered],
            ['close_time', '>=', $job->lastTimeTriggered]
        ])->first();

        if (empty($entry)) {
            throw new \Exception("Cannot find symbol entry");
        }
        $closeTime = Carbon::createFromTimestamp($entry->close_time / 1000);
        if ($closeTime->greaterThan(Carbon::now())) {
            $lastEntryCloseTimeNice = Carbon::createFromTimestamp(intval($entry->close_time / 1000))
                ->addSecond()
                ->format('Y-m-d H:i:s');

            Log::info("Too early for triggering strategy ...");
            Log::info("Next candle will appear at $lastEntryCloseTimeNice");

            return false;
        }

        Log::info("Strategy can be triggered");

        return true;
    }



    /**
     * check basic binance rules if trading is possible
     *
     * @param string $nextAction
     * @param string $symbol
     * @return MessageBag
     */
    public function canTradeBasic(string $symbol, array $accountInfo) {
        Log::info("Checking canTradeBasic() ...");
        // CHECK BASIC PERMISSIONS
        $exchangeInfoSymbol = null;

        foreach ($this->exchangeInfo["symbols"] as $exSymbol) {
            if ($exSymbol["symbol"] === $symbol) {
                $exchangeInfoSymbol = $exSymbol;
                break;
            }
        }

        // check exchangeInfo permissions/**1*/
        $isSpotTradingAllowed = $exchangeInfoSymbol["isSpotTradingAllowed"];
        $symbolHasMarket = in_array('MARKET', $exchangeInfoSymbol["orderTypes"]);
        $symbolHasSpot = in_array('SPOT', $exchangeInfoSymbol["permissions"]);

        $errorBag = new MessageBag;
        if (!$isSpotTradingAllowed) {
            $errorBag->add('exchange-info-spottrading', "Spot trading not allowed");
        }
        if (!$symbolHasMarket) {
            $errorBag->add('exchange-info-ordertype', "Order types doesn't include market");
        }
        if (!$symbolHasSpot) {
            $errorBag->add('exchange-info-spot-permissions', "No spot permissions");
        }
        if (!$accountInfo["canTrade"]) {
            $errorBag->add('account-info-can-trade', "Account doesn't allow trading");
        }
        if ($accountInfo["accountType"] !== "SPOT") {
            $errorBag->add('account-info-account-type', "Account type isn't SPOT");
        }

        if ($errorBag->isNotEmpty()) {
            foreach ($errorBag->getMessages() as $field => $message) {
                Log::error("error in canTradeBasic() for $symbol [$field] - " . implode($message));
            }
        }

        return $errorBag;
    }

    private function canTrade() {

        Log::info("Checking if trading is possible ...");
    }

    public function trade() {

        Log::info('===============================================================');
        Log::info('Starting Lakshmi trading...');
        Log::info('===============================================================');

        // TODO lastTimeTriggered imemr von Anfang an setzen
        // start_price in start_amouont umbenennen
        //Job::insert([
        //    "symbol" => "ETHUSDT",
        //    "timeframe" => "4h",
        //    "base" => "ETH",
        //    "quote" => "USDT",
        //    "strategy" => "App\Services\StrategyEmaCrossService",
        //    "settings" => json_encode([
        //        "ema1" => 2,
        //        "ema2" => 3,
        //    ]),
        //    "status" => "ACTIVE",
        //    "next" => "BUY",
        //    "start_price" => 25,
        //    "lastTimeTriggered" => intval(Carbon::now()->getPreciseTimestamp(3)),
        //    "user_id" => 1
        //]);

        foreach (Job::where('status', '<>', 'INACTIVE')->get() as $job) {

            // update symbols
            $this->updateSymbolHistory($job->symbol, $job->timeframe);

            // check if strategy can be triggered now
            if (!$this->canStrategyTriggeredNow($job)) {
                continue;
            }

            /**
             * 2. canTradeBasic
             * 3. canTrade
             * 4. strategy
             */

            // get available base & quote
            $this->setAvailableAsset($job);

            $this->setExchangeInfos($job->symbol);

            return;

            // are we still able to trade?
            $this->canTrade();

            // get the right strategy
            $strategyService = app($job->strategy);

            // ok ... let's do it
            $strategyService->strategy();

            Log::info("Lakshmi successfully finished checking strategy for job $job->symbol $job->timeframe");
        }

        Log::info("Lakshmi has done with trading ;-)");
    }

    private function setExchangeInfos($symbol) {
        // all info
        $this->exchangeInfo['all'] = $this->exchangeService->exchangeInfo();

        // specific for the symbol
        foreach ($this->exchangeInfo['all']["symbols"] as $exSymbol) {
            if ($exSymbol["symbol"] === $symbol) {
                $this->exchangeInfo['symbolinfo'] = $exSymbol;
                break;
            }
        }

        // currencies
        $this->exchangeInfo['currencies']  = [
            "base" => $this->exchangeInfo['symbolinfo']['baseAsset'],
            "quote" => $this->exchangeInfo['symbolinfo']['quoteAsset'],
        ];

        // filters
        $this->exchangeInfo['filters']  = [];
        foreach ($this->exchangeInfo['symbolinfo']['filters'] as $filter) {
            $this->exchangeInfo['filters'][$filter['filterType']] = $filter;
        }

    }
}
