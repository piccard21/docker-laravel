<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Test;
use App\Models\Symbol;

class TestController extends Controller {
    public function index() {

        //$a = DB::connection('mongodb')->createCollection("whatever");

        $symbol = Symbol::setCollection('snxusdt_4h');


        $opens = $symbol->where([
            'symbol' => "SNXUSDT",
            'timeframe' => "4h",
        ])
            ->orderBy('open', 'desc')
            ->get();

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
            "test" => $opens
        ]);
    }
}
