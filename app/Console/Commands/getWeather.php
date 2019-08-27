<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Services\WeatherService;

class getWeather extends Command
{
    protected $weather;

    protected $signature   = "getWeather {city_name=ljubljana} {day=today} {provider?}";
    protected $description = "Returns the weather forecast for a specified city on a specified day";


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(WeatherService $weatherService)
    {
        parent::__construct();

        $this->weather = $weatherService;
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $args = $this->parseInput($this->arguments());

        if (isset($args['error'])) {
            $this->parseError($args);
            return;
        }

        $args['city'] = $this->confirmCity($args);
        if (!$args['city']) {
            return;
        }

        Log::info("Fetching data for " . $args['city']->name . " " . $args['country'] . " " . $args['day'] . " " . $args['provider']);
        $this->info("# Fetching " . $args['day'] . "'s weather for " . $args['city']->name);

        $forecast = $this->weather->getForecastData($args);
        $this->outputForecast($forecast, $args);
    }


    /**
     * Validates input arguments
     *
     * @param  array $args
     * @return array
     */
    private function parseInput($args) {
        unset($args['command']);

        foreach ($args as $key => $value) {
            $args[$key] = mb_strtolower($value);
        }

        // if user specified a country (i.e. 'ljubljana, si')
        $cityName = explode(',', $args['city_name']);
        unset($args['city_name']);

        $args['name']    = trim($cityName[0]);
        $args['country'] = isset($cityName[1]) ? trim($cityName[1]) : null;

        if ($args['country'] == 'uk') {
            $args['country'] = 'gb';
        }

        // validate 'day' field
        switch ($args['day']) {
            case 'today':
            case 'tomorrow':
            case 'yesterday':
                break;

            default:
                $args['error'] = 'day';
                $args['valid'] = "'today', 'tomorrow' or 'yesterday'";
                return $args;
        }

        // validate 'provider' field
        switch ($args['provider']) {
            case '':
                break;

            case 'accuweather':
            case 'accuweather.com':
                $args['provider'] = env('API_NAME_ACCUWEATHER');
                break;

            case 'apixu':
            case 'apixu.com':
                $args['provider'] = env('API_NAME_APIXU');
                break;

            case 'darksky':
            case 'darksky.net':
                $args['provider'] = env('API_NAME_DARKSKY');
                break;

            case 'owm';
            case 'openweather':
            case 'openweathermap':
            case 'openweathermap.org':
                $args['provider'] = env('API_NAME_OPENWEATHERMAP');
                break;

            default:
                $args['error'] = 'provider';
                $args['valid'] = "'apixu', 'openweathermap', 'darksky' or 'accuweather'";
                return $args;
        }

        return $args;
    }


    /**
     * Builds an error message from user input
     *
     * @param  array $args
     * @return void
     */
    private function parseError($args) {
        $field = $args['error'];

        $message = "Sorry, '" . $args[$field] . "' is not a valid option for the '" . $field . "' field.";
        $extra   = "Valid options are: " . $args['valid'] . ".";

        $this->outputError($message, $extra);
    }


    /**
     * Asks user to confirm which city they are requesting
     *
     * @param  array  $args
     * @return object
     */
    private function confirmCity($args) {
        // check db for all matching cities
        $cities = $this->weather->getCityList($args);

        // if no results
        if ($cities->isEmpty()) {
            $this->outputError("Sorry, there is no data for any cities named '" . ucfirst($args['name']) . "'.");
            return null;
        }

        // if only one result, automatically use that one
        // if multiple results, present the first option to user
        $first    = $cities[0];
        $confirm  = "Do you mean " . $first->name . ", ";
        $confirm .= $first->region ? $first->region . ", " : null;
        $confirm .= $first->country . "?";

        if (count($cities) == 1 || $this->confirm($confirm)) {
            $first->increment('chosen');
            return $first;
        }

        // if user rejects first option, create a list of all matching cities
        $i = 0;
        foreach ($cities as $c) {
            $region = $c->region ? $c->region . ", " : null;
            $this->line(" " . $i . ": " . $c->name . ", " . $region . $c->country);
            $i ++;
        }
        $this->line(" " . $i . ": None of the above");
        $index = $this->ask("Please choose a number from the above list");

        // if user selects 'none of the above' or an invalid option
        if ($index >= $i || !is_numeric($index)) {
            $this->outputError("Sorry, there is no data for any other cities named '" . ucfirst($args['name']) . "'.");
            return null;
        }

        $city = $cities[$index];
        $city->increment('chosen'); 
        return $city;
    }


    /**
     * Builds and outputs a readable string from a Forecast object
     *
     * @param  array $forecasts
     * @param  array $args
     * @return void
     */
    private function outputForecast($forecasts, $args) {
        // if no results
        if ($forecasts->isEmpty()) {
            $this->outputError("Sorry, no weather forecast could be found for " . $args['city']->name . ".");
            return;
        }

        // format forecast as a string
        $this->comment("-----");
        foreach ($forecasts as $f) {
            $fString  = "  ";
            $fString .= $f->provider . " describes the weather";
            $fString .= " for " . $f->city_name;
            $fString .= " on " . substr($f->date, 0, 10);
            $fString .= "\n ";
            $fString .= " as \"" . $f->weather . "\"";
            $fString .= " with an average temperature of " . ($f->temp_max + $f->temp_min) / 2 . " °C";
            $fString .= ".";

            $this->line($fString);
            $this->comment("-----");
        }
    }


    /**
     * Outputs error messages to console as a readable string
     *
     * @param  string $message
     * @param  string $extra   <optional>
     * @return void
     */
    private function outputError($message, $extra=null) {
        $this->comment("-----");
        $this->line($message);
        $extra ? $this->line($extra) : null;
        $this->line("Please check your spelling and try again.");
        $this->line("Use quotation marks if any of your arguments contains more than one word.");
        $this->comment("-----");
    }

}
