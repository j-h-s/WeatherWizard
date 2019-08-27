<?php

namespace App\Services\API;

use Illuminate\Support\Facades\Log;

use App\Services\ConnectionService;
use App\Services\DatabaseService;

/**
 * Handles scraping of the DarkSky forecast and history APIs
 */
class DarkSkyService extends DatabaseService
{
    protected $connect;
    protected $openCage;
    private   $apiKey;
    private   $apiName;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ConnectionService   $connectionService,
                                OpenCageDataService $openCageDataService)
    {
        $this->connect  = $connectionService;
        $this->openCage = $openCageDataService;
        $this->apiKey   = env('API_KEY_DARKSKY');
        $this->apiName  = env('API_NAME_DARKSKY');
    }


    /**
     * Builds a URL string for the DarkSky API
     *
     * @param  object $city
     * @param  int    $timestamp  <optional>
     * @return string
     */
    private function getUrl($city, $timestamp=null) {
        $url  = "https://api.darksky.net/forecast/";
        $url .= $this->apiKey . "/" . $city->lat . "," . $city->lon;
        $url .= $timestamp ? "," . $timestamp : null;
        $url .= "?exclude=currenty,minutely,hourly,alerts,flags" . "&units=si";

        return $url;
    }


    /**
     * Retrieves data from the DarkSky forecast API
     *
     * @param  array $args
     * @return bool
     */
    public function getDarkSkyForecastData($args) {
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

        if (!$data || empty($data)) {
            Log::info($this->apiName . " forecast data not found for " . $args['city']->name);
            return false;
        }

        $today    = $data->daily->data[0];
        $tomorrow = $data->daily->data[1];

        $this->saveDarkSkyData($args['city'], $today);
        $this->saveDarkSkyData($args['city'], $tomorrow);

        return true;
    }


    /**
     * Retrieves data from the DarkSky history API
     *
     * @param  array $args
     * @return bool
     */
    public function getDarkSkyHistoryData($args) {
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
        $url  = $this->getUrl($args['city'], $args['date']->getTimestamp());
        $data = $this->connect->getData($url, $this->apiName);

        if (!$data || empty($data)) {
            Log::info($this->apiName . " history data not found for " . $args['city']->name);
            return false;
        }

        if (isset($data->code) && $data->code != 200) {
            Log::notice($data->error);
            return false;
        }

        $weather = $data->daily->data[0];
        $this->saveDarkSkyData($args['city'], $weather, $args['date']);

        return true;
    }


    /**
     * Prepares data to be saved to db
     *
     * @param  object   $city
     * @param  object   $forecast
     * @param  Datetime $date  <optional>
     * @return void
     */
    private function saveDarkSkyData($city, $forecast, $date = null) {
        $datetime = $date ? $date : new \Datetime('@' . $forecast->time);

        Log::debug("Building new Forecast object for " . $city->name . ", " . $datetime->format('Y-m-d') . ", " . $this->apiName);

        $data = [
            'date'      => $datetime,
            'city'      => $city,
            'city_name' => $city->name,
            'city_id'   => $city->id,
            'weather'   => rtrim(strtolower($forecast->summary), '.'),
            'temp_min'  => round($forecast->temperatureMin, 1),
            'temp_max'  => round($forecast->temperatureMax, 1),
            'provider'  => $this->apiName,
        ];

        $this->saveForecastData($data);
    }

}