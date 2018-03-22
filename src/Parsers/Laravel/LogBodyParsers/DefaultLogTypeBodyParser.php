<?php

/*
|--------------------------------------------------------------------------
| This Log Type appears in Laravel 4.2 through the latest version
|
| Format example:
| [2018-03-20 00:40:50] production.ERROR: InvalidArgumentException: There ... namespace. in C:\...\Application.php:475
| Stack trace:
| #0 C:\...\Application.php(509): Symfony\Component\Console\Application->findNamespace('make')
| #4 {main} [] []
|--------------------------------------------------------------------------
*/

namespace GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers;

use GTCrais\ApplicationLogParser\LogEntries\LaravelLogEntry;

class DefaultLogTypeBodyParser extends BaseBodyParser
{
	public function parseBody(LaravelLogEntry $laravelLogEntry)
	{
		$this->logEntry = $laravelLogEntry;
		$this->bodyData = [
			'message' => $this->logEntry->body
		];
	}
}