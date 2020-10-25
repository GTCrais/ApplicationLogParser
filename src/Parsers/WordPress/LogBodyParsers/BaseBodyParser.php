<?php

namespace GTCrais\ApplicationLogParser\Parsers\WordPress\LogBodyParsers;

use GTCrais\ApplicationLogParser\LogEntries\WordPressLogEntry;

abstract class BaseBodyParser
{
	public $contentParsed = false;
	public $logEntry = null;
	public $bodyData = [];

	const CONTEXT_MESSAGE_PATTERN = "(\swith\smessage\s\'{1}(.*)\'{1})?";
	const CONTEXT_EXCEPTION_PATTERN = "exception\s\'{1}([^\']+)\'{1}";
	const STACK_TRACE_DIVIDER_PATTERN = "Stack trace\:";
	const CONTEXT_IN_PATTERN = "(.+)(\:| on line )(\d+)";
	const STACK_TRACE_INDEX_PATTERN = "\#\d+\s";
	const TRACE_IN_DIVIDER_PATTERN = "\:\s";
	const TRACE_FILE_PATTERN = "(.*)\((\d+)\)";
	const FAULTY_TRACE_IDENTIFIER = "(thrown in .+ on line \d+)";

	const PARSED_BASIC = 'basic';
	const PARSED_PARTIALLY = 'partially_parsed';
	const PARSED_FULLY = 'fully_parsed';

	public function parseBody(WordPressLogEntry $wordPressLogEntry)
	{
		$this->logEntry = $wordPressLogEntry;

		$stackTraceAndExceptionData = $this->getStackTraceAndExceptionData();
		$stackTrace = $stackTraceAndExceptionData['stackTrace'];
		$exceptionAndMessage = $stackTraceAndExceptionData['exceptionData'];

		$delimiter = ' in ';
		$pattern = '/^([a-zA-Z]+\s?)?' . static::CONTEXT_EXCEPTION_PATTERN . static::CONTEXT_MESSAGE_PATTERN . '$/';
		$inAndLinePattern = '/^' . static::CONTEXT_IN_PATTERN . '$/';

		$this->bodyData = $this->parseExceptionAndMessage($exceptionAndMessage, $delimiter, $pattern, $inAndLinePattern);
		$this->bodyData['stack_trace_entries'] = $this->parseStackTrace($stackTrace);
		$this->setContentParsed();
	}

	protected function getStackTraceAndExceptionData()
	{
		$pattern = "/^" . static::STACK_TRACE_DIVIDER_PATTERN . "/m";
		$parts = array_map('ltrim', preg_split($pattern, $this->logEntry->body));
		$exceptionData = $parts[0];
		$stackTrace = (isset($parts[1])) ? $parts[1] : null;

		return compact('stackTrace', 'exceptionData');
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

		if (isset($matches[2])) {
			$exception = $matches[2];
			$message = $matches[4] ?? null;
		} else {
			$message = $exceptionAndMessage;
		}

		if ($inAndLine) {
			preg_match($inAndLinePattern, trim($inAndLine), $matches);

			$in = $matches[1] ?? null;
			$line = $matches[3] ?? null;
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
		} elseif (
			$this->bodyData['exception'] ||
			$this->bodyData['in'] ||
			$this->bodyData['line']
		) {
			$this->contentParsed = BaseBodyParser::PARSED_PARTIALLY;
		} elseif (
		$this->bodyData['message']
		) {
			$this->contentParsed = BaseBodyParser::PARSED_BASIC;
		}
	}

	protected function parseStackTrace($stackTrace)
	{
		$parsedStackTraceEntries = collect([]);

		if (trim($stackTrace)) {
			$stackTrace = trim($stackTrace);
			$pattern = '/^' . static::STACK_TRACE_INDEX_PATTERN . '/m';

			$stackTraceEntries = preg_split($pattern, $stackTrace);

			if (empty($stackTraceEntries[0])) {
				array_shift($stackTraceEntries);
			}

			$parsedStackTraceEntries = collect($stackTraceEntries)->map(function ($stackTraceEntry) {
				return trim($stackTraceEntry);
			})->filter(function ($stackTraceEntry) {
				return !preg_match('/' . static::FAULTY_TRACE_IDENTIFIER . '$/', $stackTraceEntry);
			});
		}

		return $parsedStackTraceEntries->toJson();
	}

	public function setLogEntryAttributes()
	{
		if ($this->logEntry) {
			$this->logEntry->setAttributes($this->bodyData);
			$this->logEntry->body_parse_level = $this->contentParsed;
			$this->logEntry->body_parse_level_set = true;
		}
	}

	public function reset()
	{
		$this->logEntry = null;
		$this->contentParsed = false;
		$this->bodyData = [];
	}
}
