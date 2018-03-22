<?php

namespace GTCrais\ApplicationLogParser;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class LogParserServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(Router $router)
    {
		$this->publishes([
			__DIR__.'/config/applicationLogParser.php' => config_path('applicationLogParser.php'),
		]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
		$this->mergeConfigFrom(
			__DIR__.'/config/applicationLogParser.php', 'applicationLogParser'
		);

		$this->app->bind('application-log-parser', LogParser::class);
    }
}
