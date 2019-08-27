<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateForecastsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTimeTz('date');
            $table->string('city_name')->nullable();
            $table->integer('city_id')->unsigned();
            $table->foreign('city_id')
                  ->references('id')->on('cities');
            $table->string('weather');
            $table->float('temperature')->nullable();
            $table->float('temp_min')->nullable();
            $table->float('temp_max')->nullable();
            $table->string('provider');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('forecasts');
    }
}
