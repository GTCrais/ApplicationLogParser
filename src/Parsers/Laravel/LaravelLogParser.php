<?php

namespace GTCrais\ApplicationLogParser\Parsers\Laravel;

use GTCrais\ApplicationLogParser\Contracts\LogParserContract;
use GTCrais\ApplicationLogParser\LogEntries\LaravelLogEntry;
use GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers\DefaultLogTypeBodyParser;
use GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers\LogTypeOneBodyParser;
use GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers\LogTypeThreeBodyParser;
use GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers\LogTypeTwoBodyParser;
use GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers\BaseBodyParser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LaravelLogParser implements LogParserContract
{
	public $bodyParsers = [];
	public $defaultBodyParser;

	const LOG_DATE_PATTERN = "\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]";
	const LOG_ENVIRONMENT_PATTERN = "(\w+)";
	const LOG_LEVEL_PATTERN = "([A-Z]+)";

	public function __construct(
		LogTypeOneBodyParser $logTypeOneBodyParser,
		LogTypeTwoBodyParser $logTypeTwoBodyParser,
		LogTypeThreeBodyParser $logTypeThreeBodyParser,
		DefaultLogTypeBodyParser $defaultLogTypeBodyParser
	) {
		$this->bodyParsers = [
			$logTypeOneBodyParser,
			$logTypeTwoBodyParser,
			$logTypeThreeBodyParser
		];

		$this->defaultBodyParser = $defaultLogTypeBodyParser;
	}

	public function parse($logPath): Collection
	{
		/** @var Collection $logEntries */
		$logEntries = $this->getLogEntries(file_get_contents($logPath));
		$logEntries = $this->parseLogEntryBodies($logEntries);
		$logEntries = $this->setLogEntryChildren($logEntries);

		return $logEntries;
	}

	protected function parseLogEntryBodies(Collection $logEntries)
	{
		foreach ($logEntries as $logEntry) {
			$partialBodyParser = null;
			$basicBodyParser = null;
			$contentParsed = false;

			$this->resetBodyParsers();

			/** @var BaseBodyParser $bodyParser */
			foreach ($this->bodyParsers as $bodyParser) {
				$bodyParser->parseBody($logEntry);

				if ($bodyParser->contentParsed == BaseBodyParser::PARSED_FULLY) {
					$bodyParser->setLogEntryAttributes();
					$contentParsed = true;

					break;
				}

				if ($bodyParser->contentParsed == BaseBodyParser::PARSED_PARTIALLY) {
					$partialBodyParser = $bodyParser;
				} else if ($bodyParser->contentParsed == BaseBodyParser::PARSED_BASIC) {
					$basicBodyParser = $bodyParser;
				}
			}

			if ($contentParsed) {
				continue;
			}

			$bodyParser = $partialBodyParser ?: $basicBodyParser;

			if ($bodyParser) {
				$bodyParser->setLogEntryAttributes();

				continue;
			}

			$this->defaultBodyParser->parseBody($logEntry);
			$this->defaultBodyParser->setLogEntryAttributes();
		}

		return $logEntries;
	}

	protected function resetBodyParsers()
	{
		/** @var BaseBodyParser $bodyParser */
		foreach ($this->bodyParsers as $bodyParser) {
			$bodyParser->reset();
		}

		$this->defaultBodyParser->reset();
	}

	protected function setLogEntryChildren(Collection $logEntries)
	{
		$parentKey = 0;

		return $logEntries->each(function($entry, $key) use ($logEntries, &$parentKey) {
			if (!$entry->is_child_entry) {
				$parentKey = $key;
			} else {
				$logEntries[$parentKey]->children->push($entry);
			}
		})->filter(function($entry) {
			return !$entry->is_child_entry;
		});
	}

	protected function getLogEntries($logContent)
	{
		$headerSet = $dateSet = $envSet = $levelSet = $bodySet = [];
		$logEntries = collect([]);

		$pattern = "/^" .self::LOG_DATE_PATTERN. "\s" .self::LOG_ENVIRONMENT_PATTERN. "\." .self::LOG_LEVEL_PATTERN. "\:|Next/m";

		preg_match_all($pattern, $logContent, $matches);

		if (is_array($matches)) {
			$bodySet = array_map('ltrim', preg_split($pattern, $logContent));

			if (empty($bodySet[0]) && count($bodySet) > count($matches[0])) {
				array_shift($bodySet);
			}

			$headerSet = $matches[0];
			$dateSet = $matches[1];
			$envSet = $matches[2];
			$levelSet = $matches[3];
		}

		foreach ($headerSet as $key => $header) {
			$isChildEntry = false;

			if (empty($dateSet[$key])) {
				$isChildEntry = Str::startsWith($header, "Next");

				$dateSet[$key] = $dateSet[$key-1];
				$envSet[$key] = $envSet[$key-1];
				$levelSet[$key] = $levelSet[$key-1];
				$header = str_replace("Next", $headerSet[$key-1], $header);
			}

			$logEntry = new LaravelLogEntry([
				'environment' => $envSet[$key],
				'level' => $levelSet[$key],
				'date' => $dateSet[$key],
				'header' => $header,
				'is_child_entry' => $isChildEntry,
				'body' => $bodySet[$key],
				'children' => collect([])
			]);

			$logEntries->push($logEntry);
		}

		return $logEntries;
	}
}