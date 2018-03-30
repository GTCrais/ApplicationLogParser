<?php

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