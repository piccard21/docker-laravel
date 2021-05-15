<?php

namespace App\Services;

use App\Models\JobLog;
use App\Models\Symbol;
use App\Models\Ema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use MongoDB\Client as MongoDB;

class StrategyEmaCrossService {

    protected $lakshmiService;
    private $currentEmas;
    private $symbolModel;
    private $emaModel;

    public function __construct() {
        //$this->exchangeService = app(BinanceApiService::class);
        $this->lakshmiService = app(LakshmiService::class);
        $this->symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);
        $this->setEmaModel();
    }

    /**
     * The real strategy
     *
     * @return bool
     * @throws \Exception
     */
    public function strategy() {

        Log::info("Checking strategy ...");

        $this->updateEmas();

        $this->setCurrentEmas();

        // job still isn't active ... set status
        if ($this->lakshmiService->job->status !== 'ACTIVE') {
            return $this->checkStatus();
        } // job is active ... check if we can buy or sell
        else {
            return $this->canTriggerTrade();
        }
    }

    /**
     * The core of the strategy
     *
     * @return bool
     * @throws \Exception
     */
    private function canTriggerTrade() {
        Log::info("Checking if trade can be triggered");

        if ($this->lakshmiService->job->next === "BUY") {
            if ($this->currentEmas->value1 <= $this->currentEmas->value2) {
                Log::info("EMAs haven't crossed ... do nothing");
                return false;
            } else {
                Log::info("EMAs have crossed ... BUY");
                return true;
            }
        } else {
            if ($this->currentEmas->value1 >= $this->currentEmas->value2) {
                Log::info("EMAs haven't crossed ... do nothing");
                return false;
            } else {
                Log::info("EMAs have crossed ... SELL");
                return true;
            }
        }
    }

    private function checkStatus() {
        Log::info("Job for " . $this->lakshmiService->job->symbol . " isn't ACTIVE");
        Log::info("Going to check if status can be changed");

        // BUY
        if ($this->lakshmiService->job->next === "BUY") {
            Log::info("Next action for job " . $this->lakshmiService->job->id . " is BUY");

            if ($this->lakshmiService->job->status === 'WAITING') {
                Log::info("Job status for " . $this->lakshmiService->job->id . " is WAITING");

                if ($this->currentEmas->value1 >= $this->currentEmas->value2) {
                    $this->lakshmiService->job->status = "WAITING";
                } else {
                    $this->lakshmiService->job->status = "READY";

                    $msg = "job status for " . $this->lakshmiService->job->id . " set to READY";
                    $this->lakshmiService->log($msg, 'STRATEGY', 'INFO');
                }
            } else if ($this->lakshmiService->job->status === 'READY') {
                Log::info("Job status for job " . $this->lakshmiService->job->id . " is READY");

                if ($this->currentEmas->value1 >= $this->currentEmas->value2) {
                    $this->lakshmiService->job->status = "ACTIVE";

                    $msg = "Job status for " . $this->lakshmiService->job->id . " set to ACTIVE";
                    $this->lakshmiService->log($msg, 'STRATEGY', 'INFO');
                } else {
                    $this->lakshmiService->job->status = "READY";
                }
            }

        } // SELL
        else {
            Log::info("Next job for $this->lakshmiService->job->id is BUY");

            if ($this->lakshmiService->job->status === 'WAITING') {
                if ($this->currentEmas->value1 <= $this->currentEmas->value2) {
                    $this->lakshmiService->job->status = "WAITING";
                } else {
                    $this->lakshmiService->job->status = "READY";

                    $msg = "job for " . $this->lakshmiService->job->symbol . " set to status READY";
                    $this->lakshmiService->log($msg, 'STRATEGY', 'INFO');
                }
            } else if ($this->lakshmiService->job->status === 'READY') {
                if ($this->currentEmas->value1 <= $this->currentEmas->value2) {
                    $this->lakshmiService->job->status = "ACTIVE";

                    $msg = "job for " . $this->lakshmiService->job->symbol . " set to status ACTIVE";
                    $this->lakshmiService->log($msg, 'STRATEGY', 'INFO');
                } else {
                    $this->lakshmiService->job->status = "READY";
                }
            }
        }

        $this->lakshmiService->job->save();

        return $this->lakshmiService->job->status === 'ACTIVE';
    }

    private function setEmaModel() {
        Log::info("Setting up EMA model");

        $collectionName = implode("_", [
            "EMA",
            $this->lakshmiService->job->symbol,
            $this->lakshmiService->job->timeframe
        ]);

        DB::connection('mongodb')->collection($collectionName);
        $this->emaModel = Ema::setCollection($collectionName);
    }

    private function setCurrentEmas() {
        Log::info("Getting current EMAs ...");

        $this->currentEmas = $this->emaModel->where([
            "symbol" => $this->lakshmiService->job->symbol,
            "timeframe" => $this->lakshmiService->job->timeframe,
            "ema1" => $this->getEma1Setting(),
            "ema2" => $this->getEma2Setting(),
        ])
            ->orderBy('open_time', 'desc')
            ->first();

        Log::info("Current EMA 1 is: " . $this->currentEmas->value1);
        Log::info("Current EMA 2 is: " . $this->currentEmas->value2);
    }

    /**
     * updates the EMAs in the right DB
     */
    private function updateEmas() {
        Log::info("Updating EMAs ...");

        foreach ([$this->getEma1Setting(), $this->getEma2Setting()] as $emaRange) {

            // EMA: last entry
            $lastEmaOpenTime = null;
            $lastEma = $this->emaModel
                ->where([
                    "symbol" => $this->lakshmiService->job->symbol,
                    "timeframe" => $this->lakshmiService->job->timeframe,
                    "ema" => $emaRange
                ])
                ->orderBy('open_time', 'desc')->first();

            // delete the last one
            if ($lastEma) {
                $lastEmaOpenTime = $lastEma->open_time;
                $lastEma->delete();
            }

            // KLINES: from the last EMA entry on
            $klines = $this->symbolModel->where([
                "symbol" => $this->lakshmiService->job->symbol,
                "timeframe" => $this->lakshmiService->job->timeframe
            ])
                ->when($lastEmaOpenTime, function($query) use ($lastEmaOpenTime) {
                    $query->where('time', '>=', $lastEmaOpenTime);
                })
                ->orderBy('time', 'asc');

            // calc emas & insert into DB
            $chunkSize = 1000;
            $klines->chunk($chunkSize, function($klinesChunk) use ($emaRange) {

                Log::info("Updating EMA $emaRange ... CHUNK");

                // candle close prices
                $closePrices = $klinesChunk->pluck('close')->toArray();

                // check if there's already an entry in EMA DB
                $emaAlreadyExists = $this->emaModel
                    ->where([
                        "symbol" => $this->lakshmiService->job->symbol,
                        "timeframe" => $this->lakshmiService->job->timeframe,
                        "ema" => $emaRange,
                    ])
                    ->orderBy('open_time', 'desc')->first();


                $emasBefore = null;

                // if there are already calculated EMAs, use them to fill the gap
                if ($emaAlreadyExists) {
                    $emasBefore = $this->emaModel->where([
                        "symbol" => $this->lakshmiService->job->symbol,
                        "timeframe" => $this->lakshmiService->job->timeframe,
                        "ema" => $emaRange
                    ])
                        ->where('open_time', '<', $klinesChunk->first()->time)
                        ->where('value', '<>', null)
                        ->orderBy('time', 'desc')
                        ->limit($emaRange-1)
                        ->pluck('value');

                    // TODO ... -1???
                    foreach ($emasBefore as $eb) {
                        array_unshift($closePrices, $eb);
                    }
                }

                $emasRaw = array_values(trader_ema($closePrices, $emaRange));

                $tmp = [];
                foreach ($klinesChunk as $nr => $chunk) {
                    $emaValue = isset($emasRaw[$nr]) ? $emasRaw[$nr] : null;
                    $tmp[] = [
                        "symbol" => $chunk->symbol,
                        "timeframe" => $chunk->timeframe,
                        'open_time' => $chunk->time,
                        'ema' => $emaRange,
                        'value' => $emaValue
                    ];
                }

                $this->emaModel->insert($tmp);

            });

        }
        Log::info("Finished updating EMAs");
    }

    private function getEma1Setting() {
        return $this->lakshmiService->job->settings['ema1'];
    }

    private function getEma2Setting() {
        return $this->lakshmiService->job->settings['ema2'];
    }

    // ##############################################################

    /**
     * calculate the EMAs
     * Until now from the very beginning
     *
     * @return array|array[]
     * @throws \Exception
     */
    //private function calcEmas() {
    //    Log::info('Start getting emas for ' . $this->lakshmiService->job->symbol . ' with timeframe ' .
    //        $this->lakshmiService->job->timeframe);
    //    Log::info('Ranges for EMAs are ' . $this->getEma1Setting() . ', ' . $this->getEma2Setting());
    //
    //    $symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);
    //
    //    // get close prices
    //    $klines = $symbolModel->where([
    //        "symbol" => $this->lakshmiService->job->symbol,
    //        "timeframe" => $this->lakshmiService->job->timeframe
    //    ])->orderBy('time', 'asc')->get();
    //
    //    // TODO ... MARCO!!!
    //    // get rid of the last one and substitute it with the current price
    //    $currentPrice = $this->lakshmiService->exchangeService->getCurrentPrice($this->lakshmiService->job->symbol);
    //    $closePrices = $klines->pluck('close');
    //    $closePrices->pop();
    //    $closePrices->push($currentPrice["price"]);
    //
    //    // calc emas
    //    $closePrices = $closePrices->toArray();
    //    $emasRaw = [];
    //    $emasRaw["ema1"] = trader_ema($closePrices, $this->getEma1Setting());
    //    $emasRaw["ema2"] = trader_ema($closePrices, $this->getEma2Setting());
    //
    //    if (!is_array($emasRaw["ema1"]) || !is_array($emasRaw["ema2"])) {
    //        throw new \Exception("trader_ema() didn't return an array");
    //    }
    //
    //    $emas = [
    //        "ema1" => [],
    //        "ema2" => [],
    //    ];
    //    foreach ($emasRaw as $name => $emaRaw) {
    //        foreach ($emaRaw as $key => $ema) {
    //            $emas[$name][] = [
    //                'time' => $klines->get($key)->time,    // TODO: open_time ... ist das richtig .. .denke schon?!?!
    //                'value' => $ema
    //            ];
    //        }
    //        Log::info('Finished getting ema for ' . $name);
    //    }
    //
    //    return $emas;
    //
    //}

    //private function createCollection(string $collectionName) {
    //    $collectionExists = false;
    //    foreach (DB::connection('mongodb')->listCollections() as $collectionInfo) {
    //        if ($collectionInfo["name"] === $collectionName) {
    //            $collectionExists = true;
    //            break;
    //        }
    //    }
    //
    //    if (!$collectionExists) {
    //        DB::connection('mongodb')->createCollection($collectionName);
    //    }
    //}
}
