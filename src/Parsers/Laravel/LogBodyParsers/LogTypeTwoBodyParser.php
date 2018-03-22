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

class LogTypeTwoBodyParser extends BaseBodyParser
{
	const CONTEXT_EXCEPTION_PATTERN = "([a-zA-Z\\\]+)\:{1}";
	const CONTEXT_MESSAGE_PATTERN = "\s(.+){1}";
}