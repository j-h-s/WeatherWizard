<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'region',
        'country',
        'lat',
        'lon',
        'id_accuweather',
        'id_openweathermap',
        'chosen'
    ];

    public function forecasts() {
    	return $this->hasMany('Forecast::class');
    }
}
