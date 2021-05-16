<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobLogsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('job_logs', function(Blueprint $table) {
            $table->id();
            $table->enum('method', ['BUY', 'SELL', 'WAITING', 'READY', 'ACTIVE', 'CHECK']);
            $table->enum('type', ['ERROR', 'SUCCESS', 'WARNING', 'INFO']);
            $table->json('message');
            $table->dateTime('time');
            $table->bigInteger('job_id')->unsigned()->index();
            $table->timestamps();
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('job_logs');
    }
}
