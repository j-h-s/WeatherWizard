<?php

namespace App\Services\API;

use Illuminate\Support\Facades\Log;

use App\Services\ConnectionService;
use App\Services\DatabaseService;

/**
 * Handles scraping of the AccuWeather forecast and location APIs
 */
class AccuWeatherService extends DatabaseService
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
        $this->apiKey  = env('API_KEY_ACCUWEATHER');
        $this->apiName = env('API_NAME_ACCUWEATHER');
    }


    /**
     * Builds a URL string for the AccuWeather location API
     *
     * @param  object $city
     * @return string
     */
    private function getLocationUrl($city) {
        $url  = "http://dataservice.accuweather.com/locations/v1/cities/geoposition/search";
        $url .= "?apikey=" . $this->apiKey;
        $url .= "&q=" . $city->lat . "," . $city->lon;

        return $url;
    }


    /**
     * Builds a URL string for the AccuWeather forecast API
     *
     * @param  string $id
     * @return string
     */
    private function getForecastUrl($id) {
        $url  = "http://dataservice.accuweather.com/forecasts/v1/daily/5day/";
        $url .= $id . "?apikey=" . $this->apiKey . "&metric=true";

        return $url;
    }


    /**
     * Retrieves data from the AccuWeather forecast API
     *
     * @param  array $args
     * @return bool
     */
    public function getAccuWeatherData($args) {
        if (!$this->apiKey) {
            Log::notice($this->apiName . " API key missing");
            return false;
        }

        // check rate limiting before calling api
        $rateLimit = $this->getRateLimit($this->apiName);
        if ($rateLimit) {
            return false;
        }

        $city = $args['city'];

        // check location key
        $this->getLocationKey($city);
        if (!$city->id_accuweather) {
            return false;
        }

        // connect to api
        Log::info("Calling " . $this->apiName . " forecast API for " . $city->name);
        $url  = $this->getForecastUrl($city->id_accuweather);
        $data = $this->connect->getData($url, $this->apiName);

        if (!$data || !isset($data->DailyForecasts)) {
            Log::info($this->apiName . " forecast data not found for " . $city->name);
            return false;
        }

        if (isset($data->Code) && $data->Code != 200) {
            Log::notice($data->Message);
            return false;
        }

        $forecasts = $data->DailyForecasts;

        // save forecasts for today and tomorrow
        $this->saveAccuWeatherData($city, $forecasts[0]);
        $this->saveAccuWeatherData($city, $forecasts[1]);

        return true;
    }


    /**
     * Calls the AccuWeather location API to get a location key
     *
     * @param  object $city
     * @return bool
     */
    public function getLocationKey ($city) {
        // if key already exists
        if ($city->id_accuweather) {
            Log::debug($this->apiName . " ID for " . $city->name . " = " . $city->id_accuweather);
            return true;
        }
        Log::info($this->apiName . " ID missing for " . $city->name);

        // check rate limiting before calling api
        $rateLimit = $this->getRateLimit($this->apiName);
        if ($rateLimit) {
            return false;
        }

        // if no rate limit, connect to api
        Log::info("Calling " . $this->apiName . " location API for " . $city->name);
        $url  = $this->getLocationUrl($city);
        $data = $this->connect->getData($url, $this->apiName);

        if (!$data || empty($data)) {
            Log::info($this->apiName . " location data not found for " . $city->name);
            return false;
        }

        if (isset($data->Code) && $data->Code != 200) {
            Log::notice($data->Message);
            return false;
        }

        Log::debug($this->apiName . " ID for " . $city->name . " = " . $data->Key);
        $city->id_accuweather = $data->Key;
        $city->save();
        return true;
    }


    /**
     * Prepares data to be saved to db
     *
     * @param  object $city
     * @param  object $forecast
     * @return void
     */
    private function saveAccuWeatherData($city, $forecast) {
        Log::debug("Building new Forecast object for " . $city->name . ", " . $forecast->Date . ", " . $this->apiName);

        $data = [
            'date'       => new \Datetime('@' . $forecast->EpochDate),
            'city'       => $city,
            'city_name'  => $city->name,
            'city_id'    => $city->id,
            'weather'    => strtolower($forecast->Day->IconPhrase),
            'temp_min'   => round($forecast->Temperature->Minimum->Value, 1),
            'temp_max'   => round($forecast->Temperature->Maximum->Value, 1),
            'provider'   => $this->apiName,
        ];

        $this->saveForecastData($data);
    }

}