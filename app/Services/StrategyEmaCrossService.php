<?php

namespace App\Services;

use App\Models\JobLog;
use App\Models\Symbol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use phpDocumentor\Reflection\Types\Integer;

class StrategyEmaCrossService {

    //protected $exchangeService;
    protected $lakshmiService;

    public function __construct() {
        //$this->exchangeService = app(BinanceApiService::class);
        $this->lakshmiService = app(LakshmiService::class);
    }

    public function strategy() {

        Log::info("Checking strategy ...");

        $this->calcEmas();
        //$emas = $this->calcEmas($job->symbol, $job->settings['timeframe'], [$job->settings['ema1'], $job->settings['ema2']]);

        // check ema's
        //$end1 = end($emas["ema1"]);
        //$end2 = end($emas["ema2"]);
        //
        //if (empty($end1) || empty($end2)) {
        //    Log::error("EMAs are empty ...");
        //    JobLog::create([
        //        'method' => 'STRATEGY',
        //        'type' => 'ERROR',
        //        'message' => "Empty EMAs",
        //        'original_amount' => null,
        //        'executed_amount' => null,
        //        'cummulative_quote_qty' => null,
        //        'time' => Carbon::now()->timestamp * 1000,
        //        'job_id' => $job->id
        //    ]);
        //    throw new \Exception("No Emas");
        //}

    }

    private function getEmas() {
        return $this->lakshmiService->job->settings;
    }

    private function getEma1() {
        return $this->lakshmiService->job->settings['ema1'];
    }

    private function getEma2() {
        return $this->lakshmiService->job->settings['ema2'];
    }

    private function calcEmas() {
        Log::info('Start getting emas for ' . $this->lakshmiService->job->symbol . ' with timeframe ' .
            $this->lakshmiService->job->timeframe);
        Log::info('Ranges for EMAs are ' . $this->getEma1() . ', ' . $this->getEma2());

        $symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);

        // get close prices
        $klines = $symbolModel->where([
            "symbol" => $this->lakshmiService->job->symbol,
            "timeframe" => $this->lakshmiService->job->timeframe
        ])->orderBy('time', 'asc')->get();

        // TODO ... sollen wir überhaupt die letzte mit reinnehmen ... denke schon ... MARCO!!!
        // get rid of the last one and substitute it with the current price
        $currentPrice = $this->lakshmiService->exchangeService->getCurrentPrice($this->lakshmiService->job->symbol);
        $closePrices = $klines->pluck('close');
        $closePrices->pop();
        $closePrices->push($currentPrice["price"]);

        // calc emas
        $closePrices = $closePrices->toArray();
        $emasRaw = [];
        $emasRaw["ema1"] = trader_ema($closePrices, $this->getEma1());
        $emasRaw["ema2"] = trader_ema($closePrices, $this->getEma2());

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
}
