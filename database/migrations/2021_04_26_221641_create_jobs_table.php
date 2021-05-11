<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->char('symbol', 50);
            $table->char('timeframe', 5);
            $table->char('base', 10);
            $table->char('quote', 10);
            $table->char('strategy', 50);
            $table->json('settings');
            $table->enum('status', ['WAITING', 'READY', 'ACTIVE', 'INACTIVE']);
            $table->enum('next', ['BUY', 'SELL']);
            $table->bigInteger('lastTimeTriggered')->unsigned();
            $table->float('start_price', 20, 5)->unsigned();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jobs');
    }
}
