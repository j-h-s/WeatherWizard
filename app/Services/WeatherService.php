<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

use App\Services\API\ApixuService;
use App\Services\API\AccuWeatherService;
use App\Services\API\DarkSkyService;
use App\Services\API\OpenWeatherMapService;
use App\Services\API\openCageDataService;

/**
 * Entry point for all WeatherWizard functions
 */
class WeatherService extends DatabaseService
{
    protected $apixu;
    protected $accuWeather;
    protected $darkSky;
    protected $openWeatherMap;
    protected $openCage;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ApixuService          $apixuService,
                                AccuWeatherService    $accuWeatherService,
                                DarkSkyService        $darkSkyService,
                                OpenWeatherMapService $openWeatherMapService,
                                openCageDataService   $openCageDataService)
    {
        $this->apixu          = $apixuService;
        $this->accuWeather    = $accuWeatherService;
        $this->darkSky        = $darkSkyService;
        $this->openWeatherMap = $openWeatherMapService;
        $this->openCage       = $openCageDataService;
    }


    /**
     * Builds an array of cities in the db matching user input
     *
     * @param  array $args
     * @return object
     */
    public function getCityList($args) {
        $list = $this->getAllCities($args);

        if (!$list || $list->isEmpty()) {
            Log::info("No data found for " . $args['name'] . ", populating...");
            $this->openCage->getCityData($args);
            $list = $this->getAllCities($args);
        }

        Log::debug(count($list) . " cities found matching " . $args['name']);
        return $list;
    }


    /**
     * Retrieves weather data from given APIs
     *
     * @param  array  $args
     * @return object
     */
    public function getForecastData($args) {
        $api          = $args['provider'];
        $args['date'] = new \Datetime($args['day']);

        // if user doesn't specify a provider, use all

        if ($api == '' || $api == env('API_NAME_ACCUWEATHER')) {
            $this->getAllData($args, env('API_NAME_ACCUWEATHER'));
        }

        if ($api == '' || $api == env('API_NAME_APIXU')) {
            $this->getAllData($args, env('API_NAME_APIXU'));
        }

        if ($api == '' || $api == env('API_NAME_DARKSKY')) {
            $this->getAllData($args, env('API_NAME_DARKSKY'));
        }

        if ($api == '' || $api == env('API_NAME_OPENWEATHERMAP')) {
            $this->getAllData($args, env('API_NAME_OPENWEATHERMAP'));
        }

        return $this->getAllForecasts($args);
    }


    /**
     * Processes forecast data for each API
     *
     * @param  array  $args
     * @param  string $api
     * @return void
     */
    private function getAllData($args, $api) {
        // check db before calling api
        $args['provider'] = $api;
        $found = $this->getOneForecast($args);

        if ($found) {
            Log::debug("Data found");
            return;
        }
        Log::debug("No data found");

        if ($args['day'] == 'yesterday') {
            $this->getHistoryData($args);
            return;
        }

        switch($api) {
            case env('API_NAME_ACCUWEATHER'):
                $this->accuWeather->getAccuWeatherData($args);
                break;
            case env('API_NAME_APIXU'):
                $this->apixu->getApixuForecastData($args);
                break;
            case env('API_NAME_DARKSKY'):
                $this->darkSky->getDarkSkyForecastData($args);
                break;
            case env('API_NAME_OPENWEATHERMAP'):
                $this->openWeatherMap->getOpenWeatherMapData($args);
                break;
        }
    }


    /**
     * Retrieves history data from given APIs
     *
     * @param  array $args
     * @return void
     */
    public function getHistoryData($args) {
        switch($args['provider']) {
            case env('API_NAME_APIXU'):
                $this->apixu->getApixuHistoryData($args);
                break;
            case env('API_NAME_DARKSKY'):
                $this->darkSky->getDarkSkyHistoryData($args);
                break;
        }
    }

}