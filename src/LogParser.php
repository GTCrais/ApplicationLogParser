<?php

namespace GTCrais\ApplicationLogParser;


use GTCrais\ApplicationLogParser\Exceptions\LogParserException;
use GTCrais\ApplicationLogParser\Contracts\LogParserContract;

class LogParser
{
	protected $logPath;
	protected $sortDirection = 'desc';
	protected $sortProperty = 'date';
	protected $platform;

	public function __construct()
	{
		$this->setLogPath(storage_path('logs/laravel.log'));
		$this->setDefaultPlatform();
	}

	public function get()
	{
		try {
			if (!file_exists($this->logPath)) {
				throw new LogParserException("Log Parser: file '" . $this->logPath . "' does not exist.");
			}

			/** @var LogParserContract $platformLogParser */
			if (!($platformLogParser = $this->getPlatformLogParser())) {
				return false;
			}

			$entries = $platformLogParser->parse($this->logPath);

			if ($this->sortProperty) {
				if ($this->sortDirection == 'desc') {
					$entries = $entries->sortByDesc($this->sortProperty);
				} else if ($this->sortDirection == 'asc') {
					$entries = $entries->sortBy($this->sortProperty);
				}
			}

			return $entries;
		} catch (\Exception $e) {
			\Log::error($e);
		}

		return false;
	}

	protected function getPlatformLogParser()
	{
		$platforms = $this->getAvailablePlatforms();

		if (!array_key_exists($this->platform, $platforms)) {
			return false;
		}

		$logParserClass = $platforms[$this->platform];

		try {
		    $platformLogParser = app($logParserClass);

			if (!($platformLogParser instanceof LogParserContract)) {
				throw new LogParserException("Class '" . $logParserClass ."' must implement '" . LogParserContract::class . "'.");
			}

			return $platformLogParser;
		} catch (\Exception $e) {
			\Log::error("Log Parser: could not instantiate class '" . $logParserClass . "'.");
		}

		return false;
	}

	public function setPlatform($platform)
	{
		$this->platform = $platform;

		return $this;
	}

	public function setSortDirection($direction)
	{
		$direction = strtolower($direction);

		if (!in_array($direction, ['asc', 'desc'])) {
			$direction = 'desc';
		}

		$this->sortDirection = $direction;

		return $this;
	}

	public function setSortProperty($property)
	{
		$this->sortProperty = $property;

		return $this;
	}

	public function disableDefaultSorting()
	{
		$this->sortProperty = null;

		return $this;
	}

	public function setLogPath($path)
	{
		$this->logPath = $path;

		return $this;
	}

	protected function setDefaultPlatform()
	{
		$platforms = $this->getAvailablePlatforms();

		if (!$platforms) {
			throw new LogParserException("Log Parser: at least one platform must be defined in config.");
		}

		if (array_key_exists('laravel', $platforms)) {
			$this->setPlatform('laravel');
		} else {
			$platforms = array_flip($platforms);
			$firstPlatform = array_shift($platforms);

			$this->setPlatform($firstPlatform);
		}
	}

	protected function getAvailablePlatforms()
	{
		return config('applicationLogParser.platforms');
	}
}