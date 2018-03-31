<?php

namespace GTCrais\ApplicationLogParser\LogEntries;


class LaravelLogEntry extends BaseLogEntry
{
	public $environment;
	public $is_child_entry;
	public $user_id;
	public $user_email;
	public $children;

	public function __construct(array $data)
	{
		$this->children = collect([]);

		parent::__construct($data);
	}
}