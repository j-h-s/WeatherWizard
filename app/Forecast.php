<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    protected $fillable = [
        'date',
        'city_name',
        'city_id',
        'weather',
        'description',
        'temperature',
        'temp_min',
        'temp_max',
        'provider'
    ];

    public function city() {
        return $this->belongsTo('App\City');
    }
}
