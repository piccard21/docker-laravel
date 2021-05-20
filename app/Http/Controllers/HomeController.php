<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use App\Services\LakshmiService;
use App\Services\StrategyEmaCrossService;
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

            // 1st base
            $firstBase = $job->logs()->where([
                ['method', 'BUY'],
                ['type', 'SUCCESS'],
            ])->first();
            $tmp['firstBase'] = $firstBase ? $firstBase->message['executedQty'] : '-';

            // last time triggered
            $tmp["lastTimeTriggered"] =
                Carbon::createFromTimestamp(intval($job["lastTimeTriggered"] / 1000))->format('Y-m-d H:i:s e');

            // ROI
            $tmp['roi'] = [];

            // ROI
            if ($job->next === "BUY") {
                $tmp['roi']['quote'] = round((($availableAsset['quote'] * 100) / $tmp['start_price']) - 100, 2);
                $tmp['roi']['base'] = 0;
            } else {
                $price = $binanceApiService->getCurrentPrice($job->symbol);
                $tmp['roi']['quote'] = round((($availableAsset['base'] * $price['price'] * 100) / $tmp['start_price']) - 100, 2);
                $tmp['roi']['base'] = round((($availableAsset['base'] * 100) / $tmp['firstBase'] ) - 100, 2);
            }



            $jobs[] = $tmp;
        }

        return view('home', [
            "jobs" => $jobs,
            "testme" => "askldjaksd"
        ]);
    }

    public function show(Request $request, int $id) {
        $lakshmiService = app(LakshmiService::class);

        $job = Job::find($id);

        // logs
        $logs = $job->logs()->orderBy('id', 'desc')->get();

        // klines
        // TODO ... true für keine Millisekunden

        $klines = $lakshmiService->getSymbolHistory(
            $job->symbol,
            $job->timeframe,
            $lakshmiService->getFromForHistory($job->timeframe, $job->created_at, 1000)
        );

        foreach ($klines as &$kline) {
            $kline->time = intval($kline->time / 1000);
        }

        // emas
        $emas = [];
        foreach (["ema1", "ema2"] as $range) {
            $emas[$range] = StrategyEmaCrossService::getEma($klines, $job->settings[$range]);
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
            "klines" => $klines->toArray(),
            "emas" => $emas,
            "markers" => array_reverse($markers)    // set right order for lightweightchart, otherwise markers disappear
        ];

        return view('show', [
            "chart" => json_encode($chartData),
            "job" => $job,
            "logs" => $logs->toArray()
        ]);
    }
}
