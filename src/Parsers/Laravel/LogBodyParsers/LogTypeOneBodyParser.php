<?php

/*
|--------------------------------------------------------------------------
| This Log Type appears in Laravel 4.2 - 5.2
|
| Format example:
| [2018-03-21 11:16:48] local.ERROR: exception 'PDOException' with message 'SQLSTATE[HY000] ...' in C:\...\Connector.php:55
| Stack trace:
| #0 C:\Program Files\wamp\www\TestL50\...\Connector.php(55): PDO->__construct('mysql:host=127....', 'homestead', 'secret', Array)
| #65 {main}
|--------------------------------------------------------------------------
*/

namespace GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers;

class LogTypeOneBodyParser extends BaseBodyParser
{

}