Deployment
==========

## 1) Pre-requisites
* PHP >=7.1.3
* MySQL
* Laravel
* Composer
* API accounts:
  * [OpenCageData.com](http://www.opencagedata.com)
  * any or all of the follwoing:
    * [OpenWeatherMap.org](http://www.openweathermap.org/api)
    * [DarkSky.net](http://www.darksky.net/dev)
    * [Apixu.com](http://www.apixu.com)
    * [AccuWeather.com](http://developer.accuweather.com)

## 2) Installation
* Clone the repository
  * `git clone https://github.com/j-h-s/WeatherWizard.git`
* Navigate into the app directory
  * `cd WeatherWizard`
* Install dependencies
  * `composer install`
* Create the .env file
  * `cp .env.example .env`

## 3) Setup
* Modify the .env file to update the following:
  * database name
  * db username
  * db password
  * API keys
    * Note: API limits are accurate for free accounts and should be altered for paid accounts
* Setup the database
  * `php artisan migrate:fresh`
