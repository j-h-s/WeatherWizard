<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

use App\Services\API\OpenCageDataService;

use App\City;
use App\Forecast;
use App\RateLimit;


/**
 * Handles all calls to the local database
 */
class DatabaseService
{
    protected $openCage;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(OpenCageDataService $openCageDataService) {
        $this->openCage = $openCageDataService;
    }


    ################
    ## FORECASTS
    ################

    /**
     * Checks db for multiple data sets with vague parameters
     *
     * @param  array $args
     * @return object
     */
    public function getAllForecasts($args) {
        $date = new \Datetime($args['day']);

        Log::debug("Querying all forecasts matching " . $args['city']->name . ", " . $date->format('Y-m-d') . ", " . $args['provider']);

        $query = Forecast::whereDate('date', $date)
                         ->where('city_id',  $args['city']->id);

        if ($args['provider'] != '') {
            $query->where('provider', $args['provider']);
        }

        $forecasts = $query->get();

        return $forecasts;
    }


    /**
     * Checks db for one data set with specific parameters
     *
     * @param  array  $args
     * @return object
     */
    public function getOneForecast($args) {
        Log::debug("Querying one forecast matching " . $args['city']->name . ", " . $args['date']->format('Y-m-d') . ", " . $args['provider']);

        $forecast = Forecast::whereDate('date', $args['date'])
                            ->where('city_id',  $args['city']->id)
                            ->where('provider', $args['provider'])
                            ->first();

        return $forecast;
    }


    /**
     * Saves forecast data to db
     *
     * @param  array  $data
     * @return void
     */
    public function saveForecastData($data) {
        $forecast = $this->getOneForecast($data);

        if (!$forecast) {
            Log::debug("No data found, saving new info to db");
            $forecast = new Forecast($data);
            $forecast->save();

        } else {
            Log::debug("Info already exists in db");
        }
    }


    ################
    ## CITIES
    ################

    /**
     * Checks db for multiple data sets with vague parameters
     *
     * @param  array  $args
     * @return object
     */
    public function getAllCities($args) {
        Log::debug("Querying all cities matching " . $args['name'] . " " . $args['country']);

        $query = City::where('name', $args['name']);

        if ($args['country']) {
            $query->where('country', $args['country']);
        }

        $cities = $query->orderBy('chosen', 'desc')
                        ->get();

        return $cities;
    }


    /**
     * Checks db for one data set with specific parameters
     *
     * @param  array $args
     * @return object
     */
    public function getOneCity($args) {
        Log::debug("Querying one city matching " . $args['name'] . " " . $args['country']);

        $query = City::where('name', $args['name']);

        if (isset($args['region'])) {
            $query->where('region',  $args['region']);
        }

        if ($args['country']) {
            $query->where('country', $args['country']);
        }

        $city = $query->first();

        return $city;
    }


    /**
     * Creates or updates a city's db entry
     *
     * @param  array $data
     * @return void
     */
    public function saveCityData($data) {
        $city = $this->getOneCity($data);

        if (!$city) {
            Log::debug("No data found, saving new info to db");
            $city = new City($data);
        } else {
            Log::debug("Info already exists in db, updating");
        }

        if (!$city->region && isset($data['region'])) {
            $city->region = $data['region'];
        }

        if (!$city->id_accuweather && isset($data['id_accuweather'])) {
            $city->id_accuweather = $data['id_accuweather'];
        }

        if (!$city->id_openweathermap && isset($data['id_openweathermap'])) {
            $city->id_openweathermap = $data['id_openweathermap'];
        }

        $city->save();
    }


    ################
    ## RATE LIMITS
    ################

    /**
     * Checks db for rate limiting of a given API
     *
     * @param  string $api
     * @return bool
     */
    public function getRateLimit($api) {
        Log::debug("Querying rate limit for " . $api);
        $data = RateLimit::whereDate('date', date('Y-m-d'))
                         ->where('provider', $api)
                         ->first();

        if (!$data) {
            $data = $this->createRateLimit($api);
        }

        if ($data->calls >= $data->limit -2) { // give ourselves some wiggle room
            Log::info($api . " - Rate limit reached!");
            return true;
        }

        $data->increment('calls');
        Log::info($api . ": " . $data->calls . " of " . $data->limit . " calls made");
        return false;
    }


    /**
     * Creates a new RateLimit object and saves to db
     *
     * @param  string $api
     * @return object
     */
    private function createRateLimit($api) {
        Log::debug("Creating new rate limit data for " . $api);
        $data           = new RateLimit();
        $data->date     = date('Y-m-d');
        $data->provider = $api;

        switch ($api) {
            // accuweather - 50 free calls/day (inc. location api)
            case env('API_NAME_ACCUWEATHER'):
                $data->limit = env('API_LIMIT_ACCUWEATHER');
                break;
            // apixu - 10,000 free calls/month (~330/day)
            case env('API_NAME_APIXU'):
                $data->limit = env('API_LIMIT_APIXU');
                break;
            // darksky - 1,000 free calls/day
            case env('API_NAME_DARKSKY'):
                $data->limit = env('API_LIMIT_DARKSKY');
                break;
            // openweathermap - 60 free calls/minute (1,440/day)
            case env('API_NAME_OPENWEATHERMAP'):
                $data->limit = env('API_LIMIT_OPENWEATHERMAP');
                break;
            // opencagedata - 2500 free calls/day
            case env('API_NAME_OPENCAGEDATA'):
                $data->limit = env('API_LIMIT_OPENCAGEDATA');
                break;
        }

        $data->save();
        return $data;
    }
}