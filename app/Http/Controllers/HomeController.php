<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use App\Services\LakshmiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Job;
use App\Models\Symbol;

class HomeController extends Controller {
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(LakshmiService $lakshmiService, BinanceApiService $binanceApiService) {
        $jobs = [];

        foreach (Job::where('user_id', auth()->id())->get() as $job) {
            // set job for service
            $lakshmiService->setJob($job);
            $lakshmiService->setAvailableAsset();

            $tmp = $job->toArray();
            $availableAsset = $lakshmiService->availableAsset;

            $tmp['base'] = $availableAsset['base'];
            $tmp['quote'] = $availableAsset['quote'];

            $tmp["lastTimeTriggered"] =
                Carbon::createFromTimestamp(intval($job["lastTimeTriggered"] / 1000))->format('Y-m-d H:i:s e');

            if ($job->next === "BUY") {
                $tmp['roi'] = round((($availableAsset['quote'] * 100) / $tmp['start_price']) - 100, 2);
            } else {
                $price = $binanceApiService->getCurrentPrice($job->symbol);
                $tmp['roi'] = round((($availableAsset['base'] * $price['price'] * 100) / $tmp['start_price']) - 100, 2);
            }

            $jobs[] = $tmp;
        }

        return view('home', [
            "jobs" => $jobs,
            "testme" => "askldjaksd"
        ]);
    }

    public function show(Request $request, int $id) {
        $job = Job::find($id);
        $lakshmiService = app(LakshmiService::class);

        // logs
        $logs = $job->logs()->orderBy('time', 'desc')->get();

        // klines
        $klines = $lakshmiService->getSymbolHistory($job->symbol, $job->timeframe, $job->created_at);
        foreach ($klines as &$kline) {
            $kline->time = intval($kline->time / 1000);
        }

        $klinesArray = $klines->toArray();

        // emas
        $emas = [];
        foreach (["ema1", "ema2"] as $range) {
            $emas[$range] = [];

            $emaRaw = trader_ema($klines->pluck('close')->toArray(), $job->settings[$range]);

            foreach (array_column($klinesArray, "time") as $timeKey => $time) {

                if (array_key_exists($timeKey, $emaRaw)) {

                    $emas[$range][] = [
                        'time' => $time,
                        'value' => $emaRaw[$timeKey]
                    ];
                }
            }
        }

        // markers
        $markers = [];
        $logsFiltered = $logs->filter(function($value, $key) {
            return $value->type === 'SUCCESS';
        });

        foreach ($logsFiltered as $log) {
            $symbolModel = Symbol::setCollection($job->symbol);

            $candle = $symbolModel->select('time', 'open', 'close', 'high', 'low')
                ->where('symbol', $job->symbol)
                ->where('timeframe', $job->timeframe)
                ->where('time', '<=', $log->time->getPreciseTimestamp(3))
                ->orderBy('time', 'desc')
                ->first();

            $markers[] = [
                "time" => intval($candle->time / 1000),
                "action" => $log->method
            ];
        }

        $chartData = [
            "klines" => $klinesArray,
            "emas" => $emas,
            "markers" => $markers
        ];

        return view('show', [
            "chart" => json_encode($chartData),
            "job" => $job,
            "logs" => $logs->toArray()
        ]);
    }
}
