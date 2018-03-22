<?php

namespace GTCrais\ApplicationLogParser\Contracts;

use Illuminate\Support\Collection;

interface LogParserContract
{
	public function parse($logPath) : Collection;
}