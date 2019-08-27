<?php

namespace App\Services\API;

use Illuminate\Support\Facades\Log;

use App\Services\ConnectionService;
use App\Services\DatabaseService;

/**
 * Handles scraping of the Apixu forecast and history APIs
 */
class ApixuService extends DatabaseService
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
        $this->apiKey  = env('API_KEY_APIXU');
        $this->apiName = env('API_NAME_APIXU');
    }


    /**
     * Builds a URL string for the Apixu API
     *
     * @param  object $city
     * @param  string $date  <optional>
     * @return string
     */
    private function getUrl($city, $date=null) {
        $url  = "https://api.apixu.com/v1/";
        $url .= $date ? "history.json" : "forecast.json";
        $url .= "?key=" . $this->apiKey;
        $url .= "&q=" . $city->lat . "," . $city->lon;
        $url .= $date ? "&dt=" . $date . "&hour=12" : "&days=2";
        
        return $url;
    }


    /**
     * Retrieves data from the Apixu forecast API
     *
     * @param  array $args
     * @return bool
     */
    public function getApixuForecastData($args) {
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
        Log::info("Calling " . $this->apiName . " forecast API for " . $args['city']->name);
        $url  = $this->getUrl($args['city']);
        $data = $this->connect->getData($url, $this->apiName);

        if (!$data || !isset($data->forecast)) {
            Log::info($this->apiName . " forecast data not found for " . $args['city']->name);
            return false;
        }

        $today    = $data->forecast->forecastday[0];
        $tomorrow = $data->forecast->forecastday[1];

        $this->saveApixuData($args['city'], $today);
        $this->saveApixuData($args['city'], $tomorrow);

        return true;
    }


    /**
     * Retrieves data from the Apixu history API
     *
     * @param  array $args
     * @return bool
     */
    public function getApixuHistoryData($args) {
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
        Log::info("Calling " . $this->apiName . " history API for " . $args['city']->name . ", " . $args['date']->format('Y-m-d'));
        $url  = $this->getUrl($args['city'], $args['date']->format('Y-m-d'));
        $data = $this->connect->getData($url, $this->apiName);

        if (!$data || !isset($data->forecast)) {
            Log::info($this->apiName . " history data not found for " . $args['city']->name);
            return false;
        }

        $weather = $data->forecast->forecastday[0];
        $this->saveApixuData($args['city'], $weather);

        return true;
    }


    /**
     * Prepares data to be saved to db
     *
     * @param  object $city
     * @param  object $forecast
     * @return void
     */
    private function saveApixuData($city, $forecast) {
        Log::debug("Building new Forecast object for " . $city->name . ", " . $forecast->date . ", " . $this->apiName);

        $data = [
            'date'        => new \Datetime('@' . $forecast->date_epoch),
            'city'        => $city,
            'city_name'   => $city->name,
            'city_id'     => $city->id,
            'weather'     => strtolower($forecast->day->condition->text),
            'temperature' => round($forecast->day->avgtemp_c, 1),
            'temp_min'    => round($forecast->day->mintemp_c, 1),
            'temp_max'    => round($forecast->day->maxtemp_c, 1),
            'provider'    => $this->apiName,
        ];

        $this->saveForecastData($data);
    }

}