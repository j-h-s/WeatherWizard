Weather Wizard
==================

A Laravel back-end application that gathers data from various weather forecast APIs and feeds that information to the user.
Can be operated via the command line or integrated into a separate front-end application.

## Deployment

*[Deployment and installation instructions can be found here](docs/deployment.md)*

## Usage

### Basic usage
* `php artisan getWeather`
  * This will return one weather forecast from each of the integrated APIs using the default values
    * default values are: Ljubljana, today, all available providers

### Optional parameters
* **Place name**
  * e.g. `php artisan getWeather ljubljana`
  * quotation marks or inverted commas must be used if the place name contains more than one word
    * this parameter may include an alpha-2 country code if separated by a comma
    * e.g. `php artisan getWeather 'ljubljana, si'`

* **Day**
  * to be used in conjunction with a specified place name
  * e.g. `php artisan getWeather ljubljana today`
    * valid arguments are: 'today', 'tomorrow' or 'yesterday'
    * note that not every API allows unpaid accounts to access historical data

* **Provider**
  * to be used in conjunction with a specified place and day
  * e.g. `php artisan getWeather ljubljana today openweathermap`
    * valid arguments are: 'openweathermap', 'darksky', 'apixu' or 'accuweather'
