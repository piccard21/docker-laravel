<?php

namespace App\Services;

use App\Models\Symbol;
use App\Models\Ema;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class StrategyEmaCrossService {

    protected $lakshmiService;
    private $lastEma;
    private $symbolModel;

    public function __construct() {
        $this->init();
    }

    private function init() {
        $this->lakshmiService = app(LakshmiService::class);
        $this->symbolModel = Symbol::setCollection($this->lakshmiService->job->symbol);
        $this->setLastEmas();
    }

    /**
     * The real strategy
     *
     * @return bool
     * @throws \Exception
     */
    public function check() {

        Log::info("Checking strategy ...");

        // TODO was machen mit gleich
        if ($this->lastEma["ema1"] <= $this->lastEma["ema2"]) {
            Log::info("EMA1 <= EMA2");
            return false;
        } else {
            Log::info("EMA1 > EMA2");
            return true;
        }
    }

    /**
     * sets the last EMAs
     */
    private function setLastEmas(int $chunkSize = 1000) {
        Log::info("Getting current EMAs ...");

        $from = $this->lakshmiService->getFromForHistory($this->lakshmiService->job->timeframe, $this->lakshmiService->job->created_at, 1000);

        // klines
        $klines = $this->lakshmiService->getSymbolHistory(
            $this->lakshmiService->job->symbol,
            $this->lakshmiService->job->timeframe,
            $from
        );

        // get rid of the last one
        $klines->pop();

        foreach (["ema1" => $this->getEma1Setting(), "ema2" => $this->getEma2Setting()] as $key => $range) {
            $emas = self::getEma($klines, $range);
            $last = end($emas);
            $this->lastEma[$key] = $last['value'];
        }

        Log::info("Current EMA 1 is: " . $this->lastEma["ema1"]);
        Log::info("Current EMA 2 is: " . $this->lastEma["ema2"]);
    }

    /**
     * calcs the ema by  a number of candles
     *
     * @param int $range
     * @param int|null $from
     * @return mixed
     */
    public static function getEma(Collection $klines, int $range) {
        // TODO sollte mit Collection besser sein
        $klinesArray = $klines->toArray();

        // emas
        $emas = [];
        $emaRaw = trader_ema($klines->pluck('close')->toArray(), $range);


        if (is_array($emaRaw)) {
            foreach ($emaRaw as $key => $emaValue) {
                $searched = $key+1;
                if (array_key_exists($searched, $klinesArray)) {
                    $emas[] = [
                        'time' => $klinesArray[$searched]["time"],
                        'value' => $emaValue
                    ];
                }
            }
        }

        return $emas;
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
