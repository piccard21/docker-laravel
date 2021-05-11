<?php

namespace App\Services;

use App\Models\Symbol;
use App\Models\Job;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LakshmiService {

    protected $exchangeService;

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

        $startTime = null;
        if ($lastEntry) {
            $startTime = $lastEntry->time;
            $lastEntry->delete();
        }

        $startTimeFormatted = Carbon::createFromTimestamp(intval($startTime / 1000))->format('Y-m-d H:i:s e');
        $startTimeTimestampMilliFormatted = intval($startTime / 1000);
        Log::info("Starttime for update is $startTimeFormatted ($startTimeTimestampMilliFormatted)");

        $last = null;
        do {
            $klines = $this->exchangeService->gethistoricaldata($symbol, $timeframe, $startTime);
            $klinesNr = count($klines);

            if ($klinesNr) {
                $symbolModel->insert($klines);
            }

            // still 1000 so there are more
            if ($klinesNr === 1000) {
                // get the last startTime and add a millisecond, so we won't get the same
                $startTime = $klines[999]['open_time'] + 1;
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
        Log::info("Last entry time: " . Carbon::createFromTimestamp(intval($last["open_time"] / 1000))->format('Y-m-d H:i:s e'));
        Log::info("Last entry close_time: " .
            Carbon::createFromTimestamp(intval($last["close_time"] / 1000))->format('Y-m-d H:i:s e'));
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

    private function canStrategyTriggeredNow($job) {
        Log::info("Checking if strategy can be triggered now...");

        $symbolModel = Symbol::setCollection($job->symbol);

        $entry = $symbolModel->where([
            ['time', '<=', $job->lastTimeTriggered],
            ['close_time', '>=', $job->lastTimeTriggered]
        ])->first();

        if (empty($entry)) {
            // TODO .. .kann eigentlich nicht passieren
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

        return true;
    }

    public function trade() {

        Log::info('===============================================================');
        Log::info('Starting Lakshmi trading...');
        Log::info('===============================================================');

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

            // get the right strategy
            $strategyService = app($job->strategy);

            // ok ... let's do it
            $strategyService->strategy();

            Log::info("Lakshmi successfully finished checking strategy for job $job->symbol $job->timeframe");
        }

        Log::info("Lakshmi has done with trading ;-)");
    }
}
