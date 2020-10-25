<?php

namespace GTCrais\ApplicationLogParser\Parsers\WordPress\LogBodyParsers;

use GTCrais\ApplicationLogParser\LogEntries\WordPressLogEntry;

class DefaultLogTypeBodyParser extends BaseBodyParser
{
    public function parseBody(WordPressLogEntry $wordPressLogEntry)
    {
        $this->logEntry = $wordPressLogEntry;
        $this->bodyData = [
            'message' => $this->logEntry->body
        ];
    }
}
