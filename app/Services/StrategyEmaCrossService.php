<?php

namespace App\Services;

use App\Models\Symbol;
use App\Models\Ema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StrategyEmaCrossService {

    protected $lakshmiService;
    private $currentEmas;
    private $symbolModel;
    //private $emaModel;

    public function __construct() {
        $this->init();
    }

    private function init() {
        $this->lakshmiService = app(LakshmiService::class);
        $this->symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);
        //$this->setEmaModel();
        //$this->updateEmas();
        $this->setCurrentEmas();
    }

    /**
     * The real strategy
     *
     * @return bool
     * @throws \Exception
     */
    public function check() {

        Log::info("Checking strategy ...");

        if ($this->currentEmas["ema1"] <= $this->currentEmas["ema2"]) {
            Log::info("EMA1 is below EMA2");
            return false;
        } else {
            Log::info("EMA1 is above EMA2");
            return true;
        }
    }

    /**
     * sets the current EMAs
     */
    private function setCurrentEmas() {
        Log::info("Getting current EMAs ...");

        foreach (["ema1" => $this->getEma1Setting(), "ema2" => $this->getEma2Setting()] as $key => $emaRange) {

            $emas = $this->getEma($emaRange);
            $this->currentEmas[$key] = end($emas);

            //$currentEma = $this->emaModel->where([
            //    "symbol" => $this->lakshmiService->job->symbol,
            //    "timeframe" => $this->lakshmiService->job->timeframe,
            //    "ema" => $emaRange,
            //])
            //    ->orderBy('open_time', 'desc')
            //    ->first();
            //$this->currentEmas[$key] = $currentEma->value;

        }

        Log::info("Current EMA 1 is: " . $this->currentEmas["ema1"]);
        Log::info("Current EMA 2 is: " . $this->currentEmas["ema2"]);
    }

    /**
     * calcs the ema by  a number of candles
     *
     * @param int $range
     * @param int|null $from
     * @return mixed
     */
    private function getEma(int $range, int $from = null, int $chunkSize = 1000) {
        $closePrices = $this->symbolModel->where([
            "symbol" => $this->lakshmiService->job->symbol,
            "timeframe" => $this->lakshmiService->job->timeframe
        ])
            ->when($from, function($query) use ($from) {
                $query->where('time', '>=', $from);
            })
            ->orderBy('time', 'desc')
            ->limit($chunkSize)
            ->pluck('close');

        return trader_ema($closePrices->reverse()->toArray(), $range);
    }

    private function getEma1Setting() {
        return $this->lakshmiService->job->settings['ema1'];
    }

    private function getEma2Setting() {
        return $this->lakshmiService->job->settings['ema2'];
    }

    // #################################################################################

    /**
     * creates or gets the right mongo collection
     */
    //private function setEmaModel() {
    //    Log::info("Setting up EMA model");
    //
    //    $collectionName = implode("_", [
    //        "EMA",
    //        $this->lakshmiService->job->symbol,
    //        $this->lakshmiService->job->timeframe
    //    ]);
    //
    //    DB::connection('mongodb')->collection($collectionName);
    //    $this->emaModel = Ema::setCollection($collectionName);
    //}

    /**
     * updates the EMAs in the right DB
     */
    //private function updateEmas() {
    //    Log::info("Updating EMAs ...");
    //
    //    foreach ([$this->getEma1Setting(), $this->getEma2Setting()] as $emaRange) {
    //
    //        // EMA: last entry
    //        $lastEmaOpenTime = null;
    //        $lastEma = $this->emaModel
    //            ->where([
    //                "symbol" => $this->lakshmiService->job->symbol,
    //                "timeframe" => $this->lakshmiService->job->timeframe,
    //                "ema" => $emaRange
    //            ])
    //            ->orderBy('open_time', 'desc')->first();
    //
    //        // delete the last one
    //        if ($lastEma) {
    //            $lastEmaOpenTime = $lastEma->open_time;
    //            $lastEma->delete();
    //        }
    //
    //        // KLINES: from the last EMA entry on
    //        $klines = $this->symbolModel->where([
    //            "symbol" => $this->lakshmiService->job->symbol,
    //            "timeframe" => $this->lakshmiService->job->timeframe
    //        ])
    //            ->when($lastEmaOpenTime, function($query) use ($lastEmaOpenTime) {
    //                $query->where('time', '>=', $lastEmaOpenTime);
    //            })
    //            ->orderBy('time', 'asc');
    //
    //        // calc emas & insert into DB
    //        $chunkSize = 1000;
    //        $klines->chunk($chunkSize, function($klinesChunk) use ($emaRange) {
    //
    //            Log::info("Updating EMA $emaRange ... CHUNK");
    //
    //            // candle close prices
    //            $closePrices = $klinesChunk->pluck('close')->toArray();
    //
    //            // check if there's already an entry in EMA DB
    //            $emaAlreadyExists = $this->emaModel
    //                ->where([
    //                    "symbol" => $this->lakshmiService->job->symbol,
    //                    "timeframe" => $this->lakshmiService->job->timeframe,
    //                    "ema" => $emaRange,
    //                ])
    //                ->orderBy('open_time', 'desc')->first();
    //
    //            $emasBefore = null;
    //
    //            // if there are already calculated EMAs, use them to fill the gap
    //            if ($emaAlreadyExists) {
    //                $emasBefore = $this->emaModel->where([
    //                    "symbol" => $this->lakshmiService->job->symbol,
    //                    "timeframe" => $this->lakshmiService->job->timeframe,
    //                    "ema" => $emaRange
    //                ])
    //                    ->where('open_time', '<', $klinesChunk->first()->time)
    //                    ->where('value', '<>', null)
    //                    ->orderBy('time', 'desc')
    //                    ->limit($emaRange - 1)
    //                    ->pluck('value');
    //
    //                // TODO ... -1???
    //                foreach ($emasBefore as $eb) {
    //                    array_unshift($closePrices, $eb);
    //                }
    //            }
    //
    //            $emasRaw = array_values(trader_ema($closePrices, $emaRange));
    //
    //            $tmp = [];
    //            foreach ($klinesChunk as $nr => $chunk) {
    //                $emaValue = isset($emasRaw[$nr]) ? $emasRaw[$nr] : null;
    //                $tmp[] = [
    //                    "symbol" => $chunk->symbol,
    //                    "timeframe" => $chunk->timeframe,
    //                    'open_time' => $chunk->time,
    //                    'ema' => $emaRange,
    //                    'value' => $emaValue
    //                ];
    //            }
    //
    //            $this->emaModel->insert($tmp);
    //
    //        });
    //
    //    }
    //    Log::info("Finished updating EMAs");
    //}

}
