<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model
{
    protected $fillable = [
        'date',
        'provider',
        'calls',
        'limit'
    ];
}
