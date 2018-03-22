<?php

/*
|--------------------------------------------------------------------------
| This Log Type appears in Laravel 5.5 through the latest version
|
| Format examples:
|[2018-03-21 12:45:08] local.ERROR: Class 'App\...\TestModel' not found {"userId":1,"email":"gtcrais@gmail.com","exception":"[object] (Symfony\\...\\FatalThrowableError(code: 0): Class 'App\\...\\TestModel' not found at C:\\...\\TestController.php:17)
|[stacktrace]
|#0 [internal function]: App\\Http\\Controllers\\TestController->makeException()
|#51 {main}
|"}
|
|[2018-03-21 12:49:14] local.ERROR: Class 'App\...\TestModel' not found {"exception":"[object] (Symfony\\...\\FatalThrowableError(code: 0): Class 'App\\...\\TestModel' not found at C:\\...\\TestController.php:17)
|[stacktrace]
|#0 [internal function]: App\\Http\\Controllers\\TestController->makeException()
|#51 {main}
|"}
|
|[2017-11-10 20:14:13] localdev.ERROR: SQLSTATE[HY000]: General error: 1096 No tables used (SQL: select *) {"exception":"[object] (Illuminate\\...\\QueryException(code: HY000): SQLSTATE[HY000]: General error: 1096 No tables used (SQL: select *) at C:\\...\\Connection.php:664, Doctrine\\...\\PDOException(code: HY000): SQLSTATE[HY000]: General error: 1096 No tables used at C:\\...\\PDOConnection.php:79, PDOException(code: HY000): SQLSTATE[HY000]: General error: 1096 No tables used at C:\\...\\PDOConnection.php:77)
|[stacktrace]
|#0 C:\\Program Files\\wamp\\www\\Test7\\vendor\\laravel\\framework\\src\\Illuminate\\Database\\Connection.php(624): Illuminate\\Database\\Connection->runQueryCallback('select *', Array, Object(Closure))
|#5 {main}
|"}
|--------------------------------------------------------------------------
*/

namespace GTCrais\ApplicationLogParser\Parsers\Laravel\LogBodyParsers;

use GTCrais\ApplicationLogParser\LogEntries\LaravelLogEntry;
use Illuminate\Support\Str;

class LogTypeThreeBodyParser extends BaseBodyParser
{
	const CONTEXT_EXCEPTION_PATTERN = "([a-zA-Z\\\]+\(code\:\s.+\))\:{1}";
	const CONTEXT_MESSAGE_PATTERN = "\s(.+){1}";
	const STACK_TRACE_DIVIDER_PATTERN = "\[stacktrace\]";

	public function parseBody(LaravelLogEntry $laravelLogEntry)
	{
		$this->logEntry = $laravelLogEntry;

		if ($this->bodyCanBeParsed()) {
			$stackTracesAndExceptionData = $this->getStackTracesAndExceptionData();
			$stackTraces = Str::replaceLast('"}', '', rtrim($stackTracesAndExceptionData['stackTraces']));
			$exceptionData = $stackTracesAndExceptionData['exceptionData'];

			$bodyAndUserData = $this->parseExceptionData($exceptionData);
			$this->bodyData = $bodyAndUserData['bodyData'];
			$this->bodyData['stack_traces'] = $this->parseStackTraces($stackTraces);
			$this->userData = $bodyAndUserData['userData'];
			$this->setContentParsed();
		}
	}

	protected function parseExceptionData($exceptionData)
	{
		$exceptionData = preg_replace('/[\r\n]+/', ' ', trim($exceptionData));
		$exceptionData = preg_replace('/\s+/', ' ', $exceptionData);

		$inAndLinePattern = '/^' . self::CONTEXT_IN_PATTERN . '$/';

		$exceptionAndMessagePattern = '/^' . self::CONTEXT_EXCEPTION_PATTERN . self::CONTEXT_MESSAGE_PATTERN . '$/';

		$bodyData = [
			'exception' => null,
			'message' => null,
			'in' => null,
			'line' => null
		];

		$splitExceptionData = $this->getUserDataAndExceptionJson($exceptionData);
		$userData = $splitExceptionData['userData'];
		$exceptionJson = $splitExceptionData['exceptionJson'];
		$logEntryChildren = collect([]);

		if ($exceptionJson) {
			try {
				$exceptionsData = json_decode($exceptionJson, true);
				$exceptionsData = $exceptionsData['exception'];

				if (Str::startsWith($exceptionsData, '[object]')) {
					$exceptionsData = Str::replaceFirst('[object]', '', $exceptionsData);
				}

				$exceptionsData = trim($exceptionsData);
				$exceptionsData = trim($exceptionsData, '()');

				$exceptionsAndMessagesPregSplitData = preg_split('/(\:\d+\,\s)/', $exceptionsData, -1, PREG_SPLIT_DELIM_CAPTURE);
				$exceptionsAndMessages = [];

				if (count($exceptionsAndMessagesPregSplitData) > 1) {
					for ($i = 0; $i < count($exceptionsAndMessagesPregSplitData); $i += 2) {
						$exceptionAndMessage = $exceptionsAndMessagesPregSplitData[$i];

						if (isset($exceptionsAndMessagesPregSplitData[$i + 1])) {
							$exceptionAndMessage .= $exceptionsAndMessagesPregSplitData[$i + 1];
							$exceptionAndMessage = trim(trim($exceptionAndMessage), ',');
						}

						$exceptionsAndMessages[] = $exceptionAndMessage;
					}
				} else {
					$exceptionsAndMessages = $exceptionsAndMessagesPregSplitData;
				}

				$exceptionsAndMessages = array_reverse($exceptionsAndMessages);
				$exceptionsAndMessagesArray = [];

				foreach ($exceptionsAndMessages as $exceptionAndMessage) {
					$exceptionsAndMessagesArray[] = $this->parseExceptionAndMessage($exceptionAndMessage, ' at ', $exceptionAndMessagePattern, $inAndLinePattern);
				}

				$bodyData = array_shift($exceptionsAndMessagesArray);

				if ($exceptionsAndMessagesArray) {
					$logEntryChildren = $this->buildChildren($exceptionsAndMessagesArray, $userData);
				}

				$this->logEntry->children = $logEntryChildren;

			} catch (\Exception $e) {
				// could not decode json
				$bodyData['message'] = $splitExceptionData['message'];
			}
		}

		return compact('bodyData', 'userData');
	}

	protected function buildChildren($exceptionsAndMessagesArray, $userData)
	{
		$children = collect([]);

		foreach ($exceptionsAndMessagesArray as $exceptionAndMessage) {
			$logEntry = new LaravelLogEntry([
				'environment' => $this->logEntry->environment,
				'level' => $this->logEntry->level,
				'date' => $this->logEntry->date,
				'header' => $this->logEntry->header,
				'is_child_entry' => true,
				'body' => null,
				'children' => collect([])
			]);

			$logEntry->setAttributes($exceptionAndMessage);
			$logEntry->setAttributes($userData);

			$children->push($logEntry);
		}

		return $children;
	}

	protected function getUserDataAndExceptionJson($exceptionData)
	{
		$data = [
			'message' => null,
			'userData' => [
				'user_id' => null,
				'user_email' => null,
			],
			'exceptionJson' => null
		];

		if (preg_match('/"userId"\:.+"exception"/', $exceptionData)) {
			try {
				$parts = preg_split('/("userId"\:.+"exception")/', $exceptionData, -1, PREG_SPLIT_DELIM_CAPTURE);

				$userData = explode('"exception"', $parts[1]);
				$userData = '{' . trim($userData[0], ',') . '}';
				$userData = json_decode($userData, true);

				// set data message in case everything else goes wrong
				$data['message'] = trim($parts[0]);

				$data['userData']['user_id'] = $userData['userId'] ?? null;
				$data['userData']['user_email'] = $userData['email'] ?? null;
				$data['exceptionJson'] = '{"exception"' . $parts[2] . '"}';
			} catch (\Exception $e) {

			}
		} else {
			$parts = explode('{"exception"', $exceptionData);

			// set data message in case everything else goes wrong
			$data['message'] = trim($parts[0]);

			if (isset($parts[1])) {
				$data['exceptionJson'] = '{"exception"' . $parts[1] . '"}';
			}
		}

		return $data;
	}

	protected function bodyCanBeParsed()
	{
		$pattern      = "/^(" . static::STACK_TRACE_DIVIDER_PATTERN . ")/m";
		$parts = preg_split($pattern, $this->logEntry->body, -1, PREG_SPLIT_DELIM_CAPTURE);

		if (is_array($parts) && isset($parts[1]) && $parts[1] == "[stacktrace]") {
			return true;
		}

		return false;
	}
}