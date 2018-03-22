<?php

namespace GTCrais\ApplicationLogParser\Facades;

use Illuminate\Support\Facades\Facade;

class LogParser extends Facade
{
	protected static function getFacadeAccessor()
	{
		return 'application-log-parser';
	}
}