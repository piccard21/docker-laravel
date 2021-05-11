<?php

namespace App\Http\Controllers;

use App\Services\LakshmiService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Test;
use App\Models\Symbol;
use App\Services\BinanceApiService;
use Illuminate\Support\Facades\Log;

class TestController extends Controller {
    public function index(LakshmiService $lakshmiService) {



        $symbol = "ETHUSDT";
        $timeframe = "1h";


        Log::debug("before UPDATE " . Carbon::now()->format('Y-m-d H:i:s e'));
        $lakshmiService->updateSymbol($symbol, $timeframe);

        Log::debug("after UPDATE " . Carbon::now()->format('Y-m-d H:i:s e'));
        $klines = $lakshmiService->getSymbolHistory($symbol, $timeframe);
        Log::debug("after getKlines " . Carbon::now()->format('Y-m-d H:i:s e'));



        //$symbolModel = Symbol::setCollection('snxusdt_4h');
        //$opens = $symbol->where([
        //    'symbol' => "SNXUSDT",
        //    'timeframe' => "4h",
        //])
        //    ->orderBy('open', 'desc')
        //    ->get();

        //$toInsert=[];
        //foreach (range(0, 10000) as $i) {
        //    $toInsert[] =     [
        //        'symbol' => "SNXUSDT",
        //        'timeframe' => "4h",
        //        'open' => rand(5, 15213)
        //    ];
        //}
        //$symbol->insert($toInsert);

        //$students = Student::all();

        return view('test.test', [
            "test" => $klines
        ]);
    }
}
