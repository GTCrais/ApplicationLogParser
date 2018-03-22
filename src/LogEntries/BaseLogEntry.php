<?php

namespace GTCrais\ApplicationLogParser\LogEntries;


class BaseLogEntry
{
	public $header;
	public $environment;
	public $level;
	public $date;
	public $is_child_entry;
	public $body;
	public $body_parse_level;
	public $body_parse_level_set = false;
	public $exception;
	public $message;
	public $in;
	public $line;
	public $user_id;
	public $user_email;
	public $stack_traces;
	public $children;

	public function __construct(array $data)
	{
		$this->children = collect([]);
		$this->setAttributes($data);
	}

	public function setAttributes(array $data)
	{
		foreach ($data as $property => $value) {
			$this->$property = $value;
		}
	}
}