Deployment
==========

## 1) Prerequisites
* PHP >=7.1.3
* MySQL
* Laravel ~5.8
* Composer
* API accounts (unpaid or otherwise):
  * [OpenCageData.com](http://www.opencagedata.com)
  * any or all of the following:
    * [OpenWeatherMap.org](http://www.openweathermap.org/api)
    * [DarkSky.net](http://www.darksky.net/dev)
    * [Apixu.com](http://www.apixu.com)
    * [AccuWeather.com](http://developer.accuweather.com)

## 2) Installation
* Clone the repository
  * `git clone https://github.com/j-h-s/weather-wizard.git`
* Navigate into the app directory
  * `cd weather-wizard`
* Install dependencies
  * `composer install`

## 3) Setup
* Create the database
  * `mysql -u <username> -p <password>`
  * `create database <database>;`
  * `exit;`
* Create the .env file
  * `cp .env.example .env`
* Modify the .env file to update the following:
  * database name
  * db username
  * db password
  * API keys
    * note: API limits are accurate for free accounts and should be altered for paid accounts
* Setup the database
  * `php artisan migrate:fresh`
