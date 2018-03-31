<?php

namespace GTCrais\ApplicationLogParser\Parsers\WordPress;

use Carbon\Carbon;
use GTCrais\ApplicationLogParser\Contracts\LogParserContract;
use GTCrais\ApplicationLogParser\LogEntries\WordPressLogEntry;
use GTCrais\ApplicationLogParser\Parsers\WordPress\LogBodyParsers\DefaultLogTypeBodyParser;
use GTCrais\ApplicationLogParser\Parsers\WordPress\LogBodyParsers\LogTypeOneBodyParser;
use GTCrais\ApplicationLogParser\Parsers\WordPress\LogBodyParsers\BaseBodyParser;
use Illuminate\Support\Collection;

class WordPressLogParser implements LogParserContract
{
	public $bodyParsers = [];
	public $defaultBodyParser;

	const LOG_DATE_PATTERN = "\[(\d{2}-[a-zA-Z]{3}-\d{4} \d{2}:\d{2}:\d{2}) (.*)\]";
	const LOG_LEVEL_PATTERN = "(PHP ([a-zA-Z\s]+):)?";

	public function __construct(
		LogTypeOneBodyParser $logTypeOneBodyParser,
		DefaultLogTypeBodyParser $defaultLogTypeBodyParser
	) {
		$this->bodyParsers = [
			$logTypeOneBodyParser
		];

		$this->defaultBodyParser = $defaultLogTypeBodyParser;
	}

	public function parse($logPath): Collection
	{
		/** @var Collection $logEntries */
		$logEntries = $this->getLogEntries(file_get_contents($logPath));
		$logEntries = $this->parseLogEntryBodies($logEntries);

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

	protected function getLogEntries($logContent)
	{
		$headerSet = $dateSet = $timeZone = $levelSet = $bodySet = [];
		$logEntries = collect([]);

		$pattern = "/^" . self::LOG_DATE_PATTERN. "\s" . self::LOG_LEVEL_PATTERN. "/m";

		preg_match_all($pattern, $logContent, $matches);

		if (is_array($matches)) {
			$bodySet = array_map('ltrim', preg_split($pattern, $logContent));

			if (empty($bodySet[0]) && count($bodySet) > count($matches[0])) {
				array_shift($bodySet);
			}

			$headerSet = $matches[0];
			$dateSet = $matches[1];
			$timeZone = $matches[2];
			$levelSet = $matches[4];
		}

		$ordNum = 0;

		foreach ($headerSet as $key => $header) {
			$ordNum++;

			if (empty($dateSet[$key])) {
				$dateSet[$key] = $dateSet[$key-1];
				$levelSet[$key] = $levelSet[$key-1];
				$header = $headerSet[$key-1];
				$timeZone = $timeZone[$key-1];
			}

			$logEntry = new WordPressLogEntry([
				'level' => $levelSet[$key],
				'date' => $this->parseDate(trim($dateSet[$key], '[]'), $timeZone[$key]),
				'original_date' => trim($dateSet[$key], '[]'),
				'header' => $header,
				'body' => $bodySet[$key],
				'ord_num' => $ordNum
			]);

			$logEntries->push($logEntry);
		}

		return $logEntries;
	}

	protected function parseDate($originalDate, $timeZone)
	{
		try {
		    $parsed = Carbon::parse($originalDate, $timeZone);
		} catch (\Exception $e) {
			return null;
		}

		return $parsed->toDateTimeString();
	}
}