<?php

namespace GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers;

use GTCrais\ApplicationLogParser\LogEntries\LaravelLogEntry;

abstract class BaseBodyParser
{
	public $contentParsed = false;
	public $logEntry = null;
	public $bodyData = [];
	public $userData = [
		'user_id' => null,
		'user_email' => null,
	];

	const CONTEXT_MESSAGE_PATTERN = "(\swith\smessage\s\'{1}(.*)\'{1})?";
	const CONTEXT_EXCEPTION_PATTERN = "exception\s\'{1}([^\']+)\'{1}";
	const STACK_TRACE_DIVIDER_PATTERN = "Stack trace\:";
	const CONTEXT_IN_PATTERN = "(.+)\:(\d+)";
	const STACK_TRACE_INDEX_PATTERN = "\#\d+\s";
	const TRACE_IN_DIVIDER_PATTERN = "\:\s";
	const TRACE_FILE_PATTERN = "(.*)\((\d+)\)";

	const PARSED_BASIC = 'basic';
	const PARSED_PARTIALLY = 'partially_parsed';
	const PARSED_FULLY = 'fully_parsed';

	public function parseBody(LaravelLogEntry $laravelLogEntry)
	{
		$this->logEntry = $laravelLogEntry;

		$stackTracesAndExceptionData = $this->getStackTracesAndExceptionData();
		$stackTraces = $stackTracesAndExceptionData['stackTraces'];
		$exceptionAndMessage = $stackTracesAndExceptionData['exceptionData'];

		$delimiter = ' in ';
		$pattern = '/^' . static::CONTEXT_EXCEPTION_PATTERN . static::CONTEXT_MESSAGE_PATTERN . '$/';
		$inAndLinePattern = '/^' . static::CONTEXT_IN_PATTERN . '$/';

		$this->bodyData = $this->parseExceptionAndMessage($exceptionAndMessage, $delimiter, $pattern, $inAndLinePattern);
		$this->bodyData['stackTraces'] = $this->parseStackTraces($stackTraces);
		$this->setContentParsed();
	}

	protected function getStackTracesAndExceptionData()
	{
		$pattern = "/^" . static::STACK_TRACE_DIVIDER_PATTERN . "/m";
		$parts = array_map('ltrim', preg_split($pattern, $this->logEntry->body));
		$exceptionData = $parts[0];
		$stackTraces = (isset($parts[1])) ? $parts[1] : null;

		return compact('stackTraces', 'exceptionData');
	}

	protected function parseExceptionAndMessage($exceptionAndMessage, $delimiter, $pattern, $inAndLinePattern)
	{
		$exceptionAndMessage = preg_replace('/[\r\n]+/', ' ', trim($exceptionAndMessage));
		$exceptionAndMessage = preg_replace('/\s+/', ' ', $exceptionAndMessage);

		$exception = null;
		$message = null;
		$in = null;
		$line = null;

		$parts = explode($delimiter, $exceptionAndMessage);
		$inAndLine = null;

		if (count($parts) > 2) {
			$inAndLine = array_pop($parts);
			$exceptionAndMessage = implode($delimiter, $parts);
		} else {
			$exceptionAndMessage = $parts[0];
			$inAndLine = $parts[1] ?? null;
		}

		preg_match($pattern, trim($exceptionAndMessage), $matches);

		if (isset($matches[1])) {
			$exception = $matches[1];
			$message   = $matches[2] ?? null;

			if ($inAndLine) {
				preg_match($inAndLinePattern, trim($inAndLine), $matches);

				$in = $matches[1] ?? null;
				$line = $matches[2] ?? null;
			}
		} else {
			$message = $exceptionAndMessage;
		}

		return compact('message', 'exception', 'in', 'line');
	}

	protected function setContentParsed()
	{
		if (
			$this->bodyData['message'] &&
			$this->bodyData['exception'] &&
			$this->bodyData['in'] &&
			$this->bodyData['line']
		) {

			$this->contentParsed = BaseBodyParser::PARSED_FULLY;

		} else if (
			$this->bodyData['exception'] ||
			$this->bodyData['in'] ||
			$this->bodyData['line']
		) {

			$this->contentParsed = BaseBodyParser::PARSED_PARTIALLY;

		} else if (
		$this->bodyData['message']
		) {

			$this->contentParsed = BaseBodyParser::PARSED_BASIC;

		}
	}

	protected function parseStackTraces($stackTraces)
	{
		$parsedStackTraces = collect([]);

		if (trim($stackTraces)) {
			$stackTraces = trim($stackTraces);
			$pattern = '/^' . static::STACK_TRACE_INDEX_PATTERN . '/m';

			$stackTraces = preg_split($pattern, $stackTraces);

			if (empty($stackTraces[0])) {
				array_shift($stackTraces);
			}

			$parsedStackTraces = collect($stackTraces)->map(function($stackTrace) {
				return trim($stackTrace);
			});
		}

		return $parsedStackTraces->toJson();
	}

	public function setLogEntryAttributes()
	{
		if ($this->logEntry) {
			$this->logEntry->setAttributes($this->bodyData);
			$this->logEntry->setAttributes($this->userData);
			$this->logEntry->body_parse_level = $this->contentParsed;
			$this->logEntry->body_parse_level_set = true;

			if ($this->logEntry->children->count()) {
				$this->logEntry->children->each(function($child) {
					if (!$child->body_parse_level_set) {
						$child->body_parse_level = $this->logEntry->body_parse_level;
						$child->body_parse_level_set = true;
					}
				});
			}
		}
	}

	public function reset()
	{
		$this->logEntry = null;
		$this->contentParsed = false;
		$this->bodyData = [];
		$this->userData = [
			'user_id' => null,
			'user_email' => null,
		];
	}
}