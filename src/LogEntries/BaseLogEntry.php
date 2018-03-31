<?php

namespace GTCrais\ApplicationLogParser\LogEntries;


class BaseLogEntry
{
	public $header;
	public $level;
	public $original_date;
	public $date;
	public $body;
	public $body_parse_level;
	public $body_parse_level_set = false;
	public $exception;
	public $message;
	public $in;
	public $line;
	public $stack_trace_entries;
	public $ord_num;

	public function __construct(array $data)
	{
		$this->setAttributes($data);
	}

	public function __get($property)
	{
		if (property_exists($this, $property)) {
			return $this->$property;
		}

		return null;
	}

	public function setAttributes(array $data)
	{
		foreach ($data as $property => $value) {
			$this->$property = $value;
		}
	}
}