<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\BackTestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::resource('student',StudentController::class);

########################################################

Route::prefix('test')->group(function() {
    Route::get('/', [TestController::class, 'index'])->name('test');
    //Route::get('/buy', [TestController::class, 'buy'])->name('testbuy');
    //Route::get('/sell', [TestController::class, 'sell'])->name('testsell');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');


// backtest
Route::prefix('backtest')->group(function() {
    Route::get('/', [BackTestController::class, 'index'])->name('backtest');
    //Route::post('/backtest', [BackTestController::class, 'backtest'])->name('backtest');
    //Route::get('/symbols', [BackTestController::class, 'symbols'])->name('symbols');
});
