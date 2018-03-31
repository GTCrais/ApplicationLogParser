<?php

return [

	'platforms' => [

		'laravel' => GTCrais\ApplicationLogParser\Parsers\Laravel\LaravelLogParser::class,
		'lumen' => GTCrais\ApplicationLogParser\Parsers\Laravel\LaravelLogParser::class,
		'wordpress' => GTCrais\ApplicationLogParser\Parsers\WordPress\WordPressLogParser::class,

	]

];


