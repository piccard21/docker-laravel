<?php

namespace App\Services;

use App\Models\JobLog;
use App\Models\Symbol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StrategyEmaCrossService {

    protected $lakshmiService;
    private $currentEmas;
    private $symbolModel;

    public function __construct() {
        //$this->exchangeService = app(BinanceApiService::class);
        $this->lakshmiService = app(LakshmiService::class);
        $this->symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);
    }


    /**
     * The core of the strategy
     *
     * @return bool
     * @throws \Exception
     */
    private function canTriggerTrade() {
        if ($this->lakshmiService->job->next === "BUY") {
            if ($this->currentEmas["ema1"] <= $this->currentEmas["ema2"]) {
                Log::info("EMAs haven't crossed ... do nothing");
                return false;
            } else {
                Log::info("EMAs have crossed ... BUY");
                return true;
            }
        } else {
            if ($this->currentEmas["ema1"] >= $this->currentEmas["ema2"]) {
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

                if ($this->currentEmas["ema1"] >= $this->currentEmas["ema2"]) {
                    $this->lakshmiService->job->status = "WAITING";
                } else {
                    $this->lakshmiService->job->status = "READY";

                    $msg = "job status for " . $this->lakshmiService->job->id . " set to READY";
                    $this->lakshmiService->log($msg, 'STRATEGY', 'INFO');
                }
            } else if ($this->lakshmiService->job->status === 'READY') {
                Log::info("Job status for job " . $this->lakshmiService->job->id . " is READY");

                if ($this->currentEmas["ema1"] >= $this->currentEmas["ema2"]) {
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
                if ($this->currentEmas["ema1"] <= $this->currentEmas["ema2"]) {
                    $this->lakshmiService->job->status = "WAITING";
                } else {
                    $this->lakshmiService->job->status = "READY";

                    $msg = "job for " . $this->lakshmiService->job->symbol . " set to status READY";
                    $this->lakshmiService->log($msg, 'STRATEGY', 'INFO');
                }
            } else if ($this->lakshmiService->job->status === 'READY') {
                if ($this->currentEmas["ema1"] <= $this->currentEmas["ema2"]) {
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

    /**
     * The real strategy
     *
     * @return bool
     * @throws \Exception
     */
    public function strategy() {

        Log::info("Checking strategy ...");

        /**
         * TODO
         *  - nur die letzten 2 Werte end1/end2
         */

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
     * get current EMA values
     */
    // TODO ... in DB speichern ... dauert arg lange
    private function setCurrentEmas() {
        Log::info("Going to calculate current EMAs ...");

        $lastEmas = [];
        foreach ($this->lakshmiService->job->settings as $key => $emaRange) {

            $klines = $this->symbolModel->where([
                "symbol" => $this->lakshmiService->job->symbol,
                "timeframe" => $this->lakshmiService->job->timeframe
            ])->orderBy('time', 'asc');

            $klines->chunk(1000, function($klinesChunk) use (&$lastEmas, $key, $emaRange) {
                $closePrices = $klinesChunk->pluck('close')->toArray();
                if(isset($lastEmas[$key])) {
                    array_unshift($closePrices, $lastEmas[$key]);
                }

                $emas = trader_ema($closePrices, $emaRange);
                $lastEmas[$key] = end($emas);
            });
        }

        foreach ($lastEmas as $key=>$ema) {
            Log::info("Current Ema for $key is: $ema");
        }

        $this->currentEmas = $lastEmas;
    }

    // #######################################################################################

    /**
     * calculate the EMAs
     * Until now from the very beginning
     * @return array|array[]
     * @throws \Exception
     */
    private function calcEmas() {
        Log::info('Start getting emas for ' . $this->lakshmiService->job->symbol . ' with timeframe ' .
            $this->lakshmiService->job->timeframe);
        Log::info('Ranges for EMAs are ' . $this->getEma1Setting() . ', ' . $this->getEma2Setting());

        $symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);

        // get close prices
        $klines = $symbolModel->where([
            "symbol" => $this->lakshmiService->job->symbol,
            "timeframe" => $this->lakshmiService->job->timeframe
        ])->orderBy('time', 'asc')->get();

        // TODO ... MARCO!!!
        // get rid of the last one and substitute it with the current price
        $currentPrice = $this->lakshmiService->exchangeService->getCurrentPrice($this->lakshmiService->job->symbol);
        $closePrices = $klines->pluck('close');
        $closePrices->pop();
        $closePrices->push($currentPrice["price"]);

        // calc emas
        $closePrices = $closePrices->toArray();
        $emasRaw = [];
        $emasRaw["ema1"] = trader_ema($closePrices, $this->getEma1Setting());
        $emasRaw["ema2"] = trader_ema($closePrices, $this->getEma2Setting());

        if (!is_array($emasRaw["ema1"]) || !is_array($emasRaw["ema2"])) {
            throw new \Exception("trader_ema() didn't return an array");
        }

        $emas = [
            "ema1" => [],
            "ema2" => [],
        ];
        foreach ($emasRaw as $name => $emaRaw) {
            foreach ($emaRaw as $key => $ema) {
                $emas[$name][] = [
                    'time' => $klines->get($key)->time,    // TODO: open_time ... ist das richtig .. .denke schon?!?!
                    'value' => $ema
                ];
            }
            Log::info('Finished getting ema for ' . $name);
        }

        return $emas;

    }

    private function getEma1Setting() {
        return $this->lakshmiService->job->settings['ema1'];
    }

    private function getEma2Setting() {
        return $this->lakshmiService->job->settings['ema2'];
    }
}
