# Application Log Parser

Application Log Parser is a Laravel package for parsing various application logs. By default it ships with parser for Laravel 4.2+

## Requirements

- PHP 7.0+
- Laravel 5.5+ (it may work on lower versions of Laravel, but it hasn't been tested)

## Installation

- add `"gtcrais/application-log-parser": "1.0.*"` to your `composer.json` and run `composer update`
- for Laravel `<=5.4` add `GTCrais\ApplicationLogParser\LogParserServiceProvider::class,` to providers array in `/config/app.php` and run `composer dump-autoload`  
**Note:** this package has not been tested with Laravel versions prior to `5.5`
- optionally, run `php artisan vendor:publish --provider=GTCrais\ApplicationLogParser\LogParserServiceProvider`

## API

| Method | Default | Description |
| --- | --- | --- |
| `get()` | - | Gets a collection of application logs after parsing the log file. |
| `setPlatform($platform)` | `laravel` | Sets the platform whose logs you're parsing. At the moment only Laravel platform is supported, but more platforms are in the works. You can also write and register your own platform and its log parser in the config file, described below. |
| `setLogPath($logPath)` | `/storage/logs/laravel.log` | Sets path to the log file. |
| `setSortDirection($sortDirection)` | `desc` | Accepts `desc` or `asc`. |
| `setSortProperty($sortProperty)` | `date` | Sets the property by which the application logs should be sorted. |
| `disableDefaultSorting()` | - | Disables the built-in sorting, in case you wish to manually sort the returned collection. |

## Writing a custom platform log parser

1. publish the Application Log Parser config file by running  
`php artisan vendor:publish --provider=GTCrais\ApplicationLogParser\LogParserServiceProvider`
2. write your log parser class. It must implement `GTCrais\ApplicationLogParser\Contracts\LogParserContract` and return a Collection of Log Entries
3. finally, register your platform and its log parser in the config file:  
```php
'platforms' => [

    'laravel' => GTCrais\ApplicationLogParser\Parsers\Laravel\LaravelLogParser::class,
    'customPlatform' => CustomVendor\CustomNamespace\CustomLogParser::class,

]
```

For more details check out `GTCrais\ApplicationLogParser\Parsers\LaravelLogParser` and `GTCrais\ApplicationLogParser\LogEntries\BaseLogEntry` classes.

## Usage examples

```php
use GTCrais\ApplicationLogParser\Facades\LogParser;

$logEntriesCollection = LogParser::get(); 

$logEntriesCollection = LogParser::setPlatform('customPlatform')->setLogPath('path/to/log/file/logfile.log')->get();
 
$logEntriesCollection = LogParser::setSortProperty('customProperty')->setSortDirection('asc')->get();

$logEntriesCollection = LogParser::disableDefaultSorting()->sortBy('propertyOrCallback')->get();
```

## Note

This package uses some parts of code for parsing Laravel logs from [`JackieDo/Laravel-Log-Reader`](https://github.com/JackieDo/Laravel-Log-Reader).

## License

Application Log Parser is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
