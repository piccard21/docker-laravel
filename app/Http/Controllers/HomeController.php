<?php

namespace App\Http\Controllers;

use App\Services\BinanceApiService;
use App\Services\LakshmiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Job;

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
        /**
         * 1. alle jobs vom User
         * 2. wieviel Gewinn/Verlust
         * 3. Btn Info für logs
         */

        $jobs = [];
        foreach (Job::where('user_id', auth()->id())->get() as $job) {
            // set job for service
            $lakshmiService->setJob($job);
            $lakshmiService->setAvailableAsset();


            $tmp = $job->toArray();
            $availableAsset = $lakshmiService->availableAsset;

            $tmp['base'] = $availableAsset['base'];
            $tmp['quote'] = $availableAsset['quote'];

            $tmp["lastTimeTriggered"] = Carbon::createFromTimestamp(intval($job["lastTimeTriggered"] / 1000))->format('Y-m-d H:i:s e');

            if($job->next === "BUY") {
                $tmp['roi'] = round((($availableAsset['quote'] * 100) / $tmp['start_price']) -100, 2);
            } else {
                $price = $binanceApiService->getCurrentPrice($job->symbol);
                $tmp['roi'] = round((($availableAsset['base'] * $price['price'] * 100) / $tmp['start_price']) -100, 2);
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

        return view('show', [
            "job" => $job,
            "logs" => $job->logs()->get()->toArray()
        ]);
    }
}
