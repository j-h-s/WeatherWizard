<?php

namespace App\Services\API;

use Illuminate\Support\Facades\Log;

use App\Services\ConnectionService;
use App\Services\DatabaseService;

/**
 * Handles scraping of the OpenWeatherMap forecast API
 */
class OpenWeatherMapService extends DatabaseService
{
    protected $connect;
    private   $apiKey;
    private   $apiName;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ConnectionService $connectionService)
    {
        $this->connect = $connectionService;
        $this->apiKey  = env('API_KEY_OPENWEATHERMAP');
        $this->apiName = env('API_NAME_OPENWEATHERMAP');
    }


    /**
     * Builds a URL string for the OpenWeatherMap API
     *
     * @param  object $city
     * @return string
     */
    private function getUrl($city) {
        $url  = "api.openweathermap.org/data/2.5/forecast";
        $url .= "?APPID=" . $this->apiKey;
        $url .= "&units=metric" . "&cnt=9";
        // set cnt=9 to retrieve 24 hours worth of data (24 / 3 + 1)

        if ($city->id_openweathermap) {
            $url .= "&id=" . $city->id_openweathermap;
        } else {
            $url .= "&lat=" . $city->lat . "&lon=" . $city->lon;
        }

        return $url;
    }


    /**
     * Retrieves data from the OpenWeatherMap API
     *
     * @param  array $args
     * @return bool
     */
    public function getOpenWeatherMapData($args) {
        if (!$this->apiKey) {
            Log::notice($this->apiName . " API key missing");
            return false;
        }

        // check rate limiting before calling api
        $rateLimit = $this->getRateLimit($this->apiName);
        if ($rateLimit) {
            return false;
        }

        // connect to api
        Log::info("Calling " . $this->apiName . " API for " . $args['city']->name);
        $url  = $this->getUrl($args['city']);
        $data = $this->connect->getData($url, $this->apiName);

        // if using a free account
        if (env('API_LIMIT_OPENWEATHERMAP') == 1440) {
            sleep(1); // unpaid accounts are limited to 60 calls/minute
        }

        if (!$data) {
            Log::info($this->apiName . " forecast data not found for " . $args['city']->name);
            return false;
        }

        if ($data->cod != 200) {
            Log::notice($this->apiName . ": " . $data->message);
            return false;
        }

        if (!$args['city']->id_openweathermap) {
            $args['city']->id_openweathermap = $data->city->id;
            $args['city']->save();
        }

        $today    = $data->list[0];
        $tomorrow = array_pop($data->list);

        $this->saveOpenWeatherMapData($args['city'], $today);
        $this->saveOpenWeatherMapData($args['city'], $tomorrow);

        return true;
    }


    /**
     * Prepares data to be saved to db
     *
     * @param  object $city
     * @param  object $forecast
     * @return void
     */
    private function saveOpenWeatherMapData($city, $forecast) {
        Log::debug("Building new Forecast object for " . $city->name . ", " . $forecast->dt_txt . ", " . $this->apiName);

        $data = [
            'date'        => new \Datetime('@' . $forecast->dt),
            'city'        => $city,
            'city_name'   => $city->name,
            'city_id'     => $city->id,
            'weather'     => strtolower($forecast->weather[0]->description),
            'temperature' => round($forecast->main->temp, 1),
            'temp_min'    => round($forecast->main->temp_min, 1),
            'temp_max'    => round($forecast->main->temp_max, 1),
            'provider'    => $this->apiName,
        ];

        $this->saveForecastData($data);
    }

}