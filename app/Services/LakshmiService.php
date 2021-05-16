<?php

namespace App\Services;

use App\Models\ExchangeInfo;
use App\Models\Symbol;
use App\Models\Job;
use App\Models\JobLog;
use App\Models\Credential;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;

class LakshmiService {

    public $availableAsset = [];
    public $exchangeInfo = [];
    public $exchangeService;
    public $job;

    public function __construct() {
        $this->exchangeService = app(BinanceApiService::class);
    }

    /**
     * check if trading is possible
     *
     * @param $job
     * @throws \Exception
     */
    private function canTrade() {

        Log::info("Checking if trading is possible ...");

        // basic checks
        $errorBag = $this->canTradeBasic();
        if ($errorBag->isNotEmpty()) {
            throw new \Exception("Errors appeared in checkTradeBasic()");
        }

        // check filters
        // MIN_NOTIONAL
        if (
            $this->job->next === 'BUY' &&
            $this->availableAsset["quote"] < $this->exchangeInfo['filters']['MIN_NOTIONAL']['minNotional']
        ) {
            $errorBag->add('filter-min_notional-BUY', "Not enough " . $this->job->quote . " in spot wallet");
        }

        // MARKET_LOT_SIZE

        // max of base
        if (
            $this->job->next === 'SELL' &&
            $this->availableAsset["base"] > $this->exchangeInfo['filters']['MARKET_LOT_SIZE']['maxQty']
        ) {
            $errorBag->add('filter-market_lot_size-SELL-too-much',
                "Too much " . $this->job->base . " inside spot wallet for selling.");

        }
        // min base
        if (
            $this->job->next === 'SELL' &&
            $this->availableAsset["base"] < $this->exchangeInfo['filters']['MARKET_LOT_SIZE']['minQty']
        ) {
            $errorBag->add('filter-market_lot_size-SELL-too-less',
                "Too less " . $this->job->base . " inside spot wallet for selling.");

        }

        // LOT_SIZE
        if (
            $this->job->next === 'SELL' &&
            $this->availableAsset["base"] > $this->exchangeInfo['filters']['LOT_SIZE']['maxQty']
        ) {
            $errorBag->add('filter-lot_size-SELL-too-much', "Too much " . $this->job->base . " inside spot wallet for selling.");

        }
        // min base
        if (
            $this->job->next === 'SELL' &&
            $this->availableAsset["base"] < $this->exchangeInfo['filters']['LOT_SIZE']['minQty']
        ) {
            $errorBag->add('filter-lot_size-SELL-too-less', "Too less " . $this->job->base . " inside spot wallet for selling.");

        }

        if ($errorBag->isNotEmpty()) {
            foreach ($errorBag->getMessages() as $field => $message) {
                $msg = "error in canTrade() for " .
                    $this->exchangeInfo['symbolinfo']['symbol'] .
                    " [$field] - " . implode($message);
                $this->log($msg, "CHECK", "ERROR");
            }
            throw new \Exception("Errors appeared in checkTrade()");
        } else {
            Log::info("Trading check passed successfully");
        }
    }

    /**
     * check basic binance rules if trading is possible
     *
     * @param string $nextAction
     * @param string $symbol
     * @return MessageBag
     */
    public function canTradeBasic() {

        // check exchangeInfo permissions/**1*/
        $isSpotTradingAllowed = $this->exchangeInfo['symbolinfo']["isSpotTradingAllowed"];
        $symbolHasMarket = in_array('MARKET', $this->exchangeInfo['symbolinfo']["orderTypes"]);
        $symbolHasSpot = in_array('SPOT', $this->exchangeInfo['symbolinfo']["permissions"]);

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
        if (!$this->exchangeInfo['accountInfo']["canTrade"]) {
            $errorBag->add('account-info-can-trade', "Account doesn't allow trading");
        }
        if ($this->exchangeInfo['accountInfo']["accountType"] !== "SPOT") {
            $errorBag->add('account-info-account-type', "Account type isn't SPOT");
        }

        if ($errorBag->isNotEmpty()) {
            foreach ($errorBag->getMessages() as $field => $message) {
                $msg = "error in canTradeBasic() for " .
                    $this->exchangeInfo['symbolinfo']['symbol'] .
                    " [$field] - " . implode($message);
                $this->log($msg, "CHECK", "ERROR");
            }
        }

        return $errorBag;
    }

    /**
     * checks if startegy can be triggered now
     *
     * @param $job
     * @return bool
     * @throws \Exception
     */
    private function isJobOnTime() {
        Log::info("Checking if strategy can be triggered now...");

        // TODO ... stimmt das .. .was wenn es zwischenzeitlich einen Ausfall gab
        // prüfen ob es Kerzen danach gibt
        // wenn ja auf WAITING/READY umstellen?

        $symbolModel = Symbol::setCollection($this->job->symbol);

        $entry = $symbolModel->where([
            ['time', '<=', $this->job->lastTimeTriggered],
            ['close_time', '>=', $this->job->lastTimeTriggered]
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
            ->get();

        Log::debug("Successfully fetched symbol history of $symbol in timeframe $timeframe");

        return $result;
    }

    /**
     * handy logging function
     *
     * @param string $msg
     * @param string $type
     */
    public function log($msg, $method, $type) {

        if (!is_array($msg)) {
            Log::info($msg);
        }

        JobLog::create([
            'method' => $method,
            'type' => $type,
            'message' => $msg,
            'time' => Carbon::now(),
            'job_id' => $this->job->id
        ]);
    }

    /**
     * get available asset of this job
     *
     * @param $job
     * @return array
     */
    public function setAvailableAsset() {

        Log::info("Getting available asset for job " . $this->job->id . "(" . $this->job->symbol . "/" . $this->job->timeframe .
            ")");

        $this->availableAsset = [
            "base" => 0,
            "quote" => 0,
        ];

        // not active yet OR no logs
        // TODO ... bottleneck ... beim Job anlegen sollte was eingetragen werden?
        if (!JobLog::whereIn('method', ['BUY', 'SELL'])->where('type', 'SUCCESS')->count() ||
            ($this->job->status !== 'ACTIVE' && $this->job->status !== 'INACTIVE')) {
            $this->availableAsset = [
                "base" => 0,
                "quote" => $this->job->start_price
            ];
        } else {
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
            $lastJob = $this->job->logs()->whereIn('method', ['BUY', 'SELL'])->orderBy('time', 'desc')->first();

            // should be also valid for inactive jobs
            if ($this->job->next === "BUY") {
                $this->availableAsset = [
                    "base" => 0,
                    "quote" => $lastJob->message["cummulativeQuoteQty"],
                ];

            } else if ($this->job->next === "SELL") {
                $this->availableAsset = [
                    "base" => $lastJob->message["executedQty"],
                    "quote" => 0
                ];
            }
        }

        Log::info("- base: " . $this->availableAsset["base"] . " " . $this->job->base);
        Log::info("- quote: " . $this->availableAsset["quote"] . "  " . $this->job->quote);
    }

    /**
     * updates the exchangeinfo
     */
    public function updateExchangeInfo() {
        $exchangeInfo = $this->exchangeService->exchangeInfo();
        $entryResult = ExchangeInfo::first();
        if (!$entryResult) {
            ExchangeInfo::create([
                'info' => $exchangeInfo,
            ]);
        } else {
            $entryResult->update(['info' => $exchangeInfo]);
        }
    }

    /**
     * prepares and orders exchangeinfos
     */
    private function setExchangeInfos() {
        // all info
        if (!ExchangeInfo::first()) {
            $this->updateExchangeInfo();
        }

        $this->exchangeInfo['all'] = ExchangeInfo::first()->info;

        // specific for the symbol
        foreach ($this->exchangeInfo['all']["symbols"] as $exSymbol) {
            if ($exSymbol["symbol"] === $this->job->symbol) {
                $this->exchangeInfo['symbolinfo'] = $exSymbol;
                break;
            }
        }

        // currencies
        $this->exchangeInfo['currencies'] = [
            "base" => $this->exchangeInfo['symbolinfo']['baseAsset'],
            "quote" => $this->exchangeInfo['symbolinfo']['quoteAsset'],
        ];

        // filters
        $this->exchangeInfo['filters'] = [];
        foreach ($this->exchangeInfo['symbolinfo']['filters'] as $filter) {
            $this->exchangeInfo['filters'][$filter['filterType']] = $filter;
        }

        // account info
        $this->exchangeInfo['accountInfo'] = $this->exchangeService->accountInfo();

        // precision
        if ($this->job->next === 'BUY') {
            // tick
            $this->exchangeInfo['precision'] = intval(-log($this->exchangeInfo['filters']['PRICE_FILTER']['tickSize'], 10), 0);

        } else {
            if ($this->exchangeInfo['filters']['LOT_SIZE']['stepSize'] > 0) {
                $this->exchangeInfo['precision'] = intval(-log($this->exchangeInfo['filters']['LOT_SIZE']['stepSize'], 10), 0);
            } else if ($this->exchangeInfo['filters'] ['MARKET_LOT_SIZE']['stepSize'] > 0) {
                $this->exchangeInfo['precision'] =
                    intval(-log($this->exchangeInfo['filters']['MARKET_LOT_SIZE']['stepSize'], 10), 0);
            }
        }
    }

    /**
     * sets the right status if job isn't active
     *
     * @param $strategyService
     */
    private function checkStatus($strategyService) {
        Log::info("Job for " . $this->job->symbol . " isn't ACTIVE");
        Log::info("Going to check if status can be changed");

        // BUY
        if ($this->job->next === "BUY") {
            Log::info("Next action for job " . $this->job->id . " is BUY");

            if ($this->job->status === 'WAITING') {
                Log::info("Status for job " . $this->job->id . " is WAITING");

                if ($strategyService->check()) {
                    //if ($this->currentEmas["ema1"] >= $this->currentEmas["ema2"]) {
                    $this->job->status = "WAITING";
                } else {
                    $this->job->status = "READY";

                    $msg = "Status for job " . $this->job->id . " set to READY";
                    $this->log($msg, 'READY', 'INFO');
                }
            } else if ($this->job->status === 'READY') {
                Log::info("Status for job " . $this->job->id . " is READY");

                if ($strategyService->check()) {
                    //if ($this->currentEmas["ema1"] >= $this->currentEmas["ema2"]) {
                    $this->job->status = "ACTIVE";

                    $msg = "Status for job " . $this->job->id . " set to ACTIVE";
                    $this->log($msg, 'ACTIVE', 'INFO');
                } else {
                    $this->job->status = "READY";
                }
            }

        } // SELL
        else {
            Log::info("Next job for $this->job->id is BUY");

            if ($this->job->status === 'WAITING') {
                if (!$strategyService->check()) {
                    //if ($this->currentEmas["ema1"] <= $this->currentEmas["ema2"]) {
                    $this->job->status = "WAITING";
                } else {
                    $this->job->status = "READY";

                    $msg = "job for " . $this->job->symbol . " set to status READY";
                    $this->log($msg, 'READY', 'INFO');
                }
            } else if ($this->job->status === 'READY') {
                if (!$strategyService->check()) {
                    //if ($this->currentEmas["ema1"] <= $this->currentEmas["ema2"]) {
                    $this->job->status = "ACTIVE";

                    $msg = "job for " . $this->job->symbol . " set to status ACTIVE";
                    $this->log($msg, 'ACTIVE', 'INFO');
                } else {
                    $this->job->status = "READY";
                }
            }
        }

        $this->job->save();

    }

    /**
     * set status or trade
     */
    private function strategy() {

        // get the right strategy
        $strategyService = app($this->job->strategy);

        // job still isn't active ... set status
        if ($this->job->status !== 'ACTIVE') {
            $this->checkStatus($strategyService);
        }

        // job is active ... check if we can buy or sell
        if ($this->job->status === 'ACTIVE') {

            Log::info("Checking if trade can be triggered");

            if ($this->job->next === "BUY") {
                if (!$strategyService->check()) {
                    Log::info("Strategy hasn't triggered ... do nothing");
                } else {
                    Log::info("Strategy triggered... BUY");
                    $this->triggerTrade();
                }
            } else {
                if ($strategyService->check()) {
                    Log::info("Strategy hasn't triggered ... do nothing");
                } else {
                    Log::info("Strategy triggered... SELL");
                    $this->triggerTrade();
                }
            }
        }
    }

    /**
     * this one has to be used by the cron job
     */
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

        /**
         * TODO
         *  - wenn neuer Job, dann auch sofort prüfen ob man auf READY umschalten kann
         * - hierfür eine gernerelle update-Methode des service, die public ist
         */

        foreach (Job::where('status', '<>', 'INACTIVE')->get() as $job) {
            $this->job = $job;

            // set right credentials
            if (env('APP_ENV') === 'live') {
                $credentials = Credential::where('user_id', $job->user_id)->first();
                $this->exchangeService->setCredentials($credentials->toArray());
            }

            try {
                Log::info("----------------------------------------");

                // update symbols
                $this->updateSymbolHistory($job->symbol, $job->timeframe);

                // check if strategy can be triggered now
                if (!$this->isJobOnTime()) {
                    continue;
                }

                // get available base & quote
                $this->setAvailableAsset();

                // get necessary exchange infos
                $this->setExchangeInfos();

                // does the exchanges service allow us to trade
                $this->canTrade();

                // check the strategy
                $this->strategy();

                // set time, so it won't be triggered every time
                $this->job->lastTimeTriggered = intval(Carbon::now()->getPreciseTimestamp(3));
                $this->job->save();

            } catch (\Exception $e) {
                Log::error($e->getMessage());
                Log::error("Trading for $job->symbol failed  ... continue with next job");
                continue;
            }

            Log::info("Lakshmi finished checking strategy for job $job->id $job->symbol $job->timeframe");
        }

        // all done
        Log::info("Lakshmi has done with trading ;-)");
    }

    /**
     * actually trigger a trade
     */
    private function triggerTrade() {
        Log::info("TRADDDDE!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");

        // ====BUY====
        if ($this->job->next === "BUY") {
            $quoteOrderQty =
                floor(round($this->availableAsset['quote'] * pow(10, $this->exchangeInfo['precision']),
                    $this->exchangeInfo['precision'])) / pow(10, $this->exchangeInfo['precision']);

            Log::info("Going to buy " . $this->job->base . " for " . $quoteOrderQty . " ...");

            // BUY!!!!
            $response = $this->exchangeService->buy($this->job->symbol, $quoteOrderQty);

            // ERROR
            if (!is_array($response)) {
                $msg = "Error in BUY trade JOBID: " . $this->job->id . "): response was null";
                $this->log($msg, 'BUY', 'ERROR');
            } else if (is_array($response) && array_key_exists('code', $response)) {
                //[
                //    code => -1021,
                //    msg 0> "..."
                //]
                $msg = "Error in BUY trade " . ($response["code"] . ", JOBID: " . $this->job->id . "): " . $response["msg"]);
                $this->log($msg, 'BUY', 'ERROR');

            }// SUCCESS
            else {
                $msg =
                    "Successfully bought " . $response['executedQty'] . " of  " . $this->job->base . " (JOBID: " . $this->job->id .
                    ")";
                Log::info($msg);
                $this->log($response, 'BUY', 'SUCCESS');

                $this->job->next = "SELL";
                $this->job->save();
            }

        } // ====SELL====
        else {
            $quantity = floor(round($this->availableAsset['base'] * pow(10, $this->exchangeInfo['precision']),
                    $this->exchangeInfo['precision'])) / pow(10, $this->exchangeInfo['precision']);

            Log::info("Going to sell $quantity  " . $this->job->base);

            // SELL!!!!
            $response = $this->exchangeService->sell($this->job->symbol, $quantity);

            // error
            if (!is_array($response)) {
                $msg = "Error in SELL trade JOBID: $this->job->id): response was null";
                $this->log($msg, 'SELL', 'ERROR');
            } else if (array_key_exists('code', $response)) {
                //[
                //    code => -1021,
                //    msg 0> "..."
                //]

                $msg = "Error in SELL trade " . ($response["code"] . ", JOBID: " . $this->job->id . "): " . $response["msg"]);
                $this->log($msg, 'SELL', 'ERROR');

            }// SUCCESS
            else {
                $msg = "Successfully sold " . $response['executedQty'] . " of  " . $this->job->base . " (JOBID: " . $this->job->id .
                    ")";
                Log::info($msg);
                $this->log($response, 'SELL', 'SUCCESS');

                $this->job->next = "BUY";
                $this->job->save();
            }
        }
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

        $lastEntry = $symbolModel->where([
            "symbol" => $symbol,
            "timeframe" => $timeframe
        ])
            ->orderBy('time', 'desc')
            ->first();

        $openTime = null;
        if ($lastEntry) {
            $openTime = $lastEntry->time;
            $closeTime = $lastEntry->close_time;
            $lastEntry->delete();

            $openTimeFormatted = Carbon::createFromTimestamp(intval($openTime / 1000))->format('Y-m-d H:i:s e');
            $closeTimeFormatted = Carbon::createFromTimestamp(intval($closeTime / 1000))->format('Y-m-d H:i:s e');
            Log::info("Last entry for $symbol/$timeframe:");
            Log::info("- open time: $openTimeFormatted ($openTime)");
            Log::info("- close time: $closeTimeFormatted ($closeTime)");
        }

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

    public function setJob(Job $job) {
        $this->job = $job;
    }
}
