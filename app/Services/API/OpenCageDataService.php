<?php

namespace App\Services\API;

use Illuminate\Support\Facades\Log;

use App\Services\ConnectionService;
use App\Services\DatabaseService;

/**
 * Handles scraping of the OpenCageData location API
 */
class OpenCageDataService extends DatabaseService
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
        $this->apiKey  = env('API_KEY_OPENCAGEDATA');
        $this->apiName = env('API_NAME_OPENCAGEDATA');
    }


    /**
     * Builds a URL string for the OpenCageData API
     *
     * @param  array  $args
     * @return string
     */
    private function getUrl($args) {
        $url  = "https://api.opencagedata.com/geocode/v1/json";
        $url .= "?key=" . $this->apiKey . "&q=" . rawurlencode($args['name']);
        $url .= $args['country'] ? rawurlencode(", " . $args['country']): null;
        $url .= "&pretty=1" . "&no_annotations=1";
        
        return $url;
    }


    /**
     * Calls the OpenCageData API to get a location's data
     *
     * @param  array $args
     * @return bool
     */
    public function getCityData ($args) {
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
        Log::info("Calling " . $this->apiName . " API for " . $args['name']);
        $url  = $this->getUrl($args);
        $data = $this->connect->getData($url, $this->apiName);
        sleep(1); // opencagedata allows 1 call/second, so sleep just to be safe

        if (!$data || empty($data)) {
            Log::info($this->apiName . " data not found for " . $args['name']);
            return false;
        }

        $results = $data->results;

        foreach ($results as $data) {
            $this->processCityData($data, $args);
        }

        return true;
    }


    /**
     * Processes data retrieved by the OpenCageData API
     *
     * @param  object $data
     * @param  array  $args
     * @return bool
     */
    private function processCityData($data, $args) {
        $name = explode(", ", $data->formatted);
        $code = $data->components->country_code;

        // ignore results that don't match user input
        if (mb_strtolower($name[0]) != $args['name']) {
            return false;
        }

        // if the user specified a country, ignore results that don't match
        if ($args['country'] && ($args['country'] != $code)) {
            return false;
        }

        $city = [
            'name'    => $name[0],
            'country' => strtoupper($code),
            'lat'     => round($data->geometry->lat, 2),
            'lon'     => round($data->geometry->lng, 2)
            ];

        // if city is in USA or Canada, get state instead of county
        if ($code != 'us' && $code != 'ca' && isset($data->components->county)) {
            $city['region'] = $data->components->county;

        } else if (isset($data->components->state)) {
            $city['region'] = $data->components->state;
        }

        $this->saveCityData($city);
        return true;
    }
}