<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Handles all external url connections
 */
class ConnectionService
{
    /**
     * Fetches json data via cURL and decodes
     *
     * @param  string $url
     * @param  string $provider
     * @return object
     */
    public function getData($url, $provider) {
        Log::debug($url);
        $json = $this->curl($url);

        if (!$json) {
            Log::notice($provider . " returned no data.");
            return false;
        }

        $data = json_decode($json);

        if (isset($data->Code)) {
            Log::notice($data->Message);
            return false;
        }

        return $data;
    }


    /**
     * Sends request via cURL
     *
     * @param  string $url
     * @return string
     */
    public function curl($url) {
        // initialise cURL
        $curl = curl_init();

        // set options
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_URL => $url,
        ));

        // // handle headers
        // $headers = [];
        // curl_setopt($curl, CURLOPT_HEADERFUNCTION,
        //     function ($curl, $header) use (&$headers) {
        //         $length = strlen($header);
        //         $header = explode(':', $header, 2);

        //         if (isset($header[1])) {
        //             $name = strtolower($header[0]);
        //             $headers[$name] = trim($header[1]);
        //         }

        //         return $length;
        //     }
        // );

        // send the request & save response
        try {
            $response = curl_exec($curl);
        } catch (\Exception $e) {
            Log::error($e);
            return false;
        }

        // close request
        curl_close($curl);

        return $response;
    }

}