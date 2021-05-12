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


        //$lakshmiService->updateSymbolHistory($symbol, $timeframe);
        //$klines = $lakshmiService->getSymbolHistory($symbol, $timeframe);


        return view('test.test', [
            "test" => ""
        ]);
    }


    public function trade(LakshmiService $lakshmiService) {
        $lakshmiService->trade();
    }
}
