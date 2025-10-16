<?php

declare(strict_types=1);

namespace Pair\Helpers;

use Pair\Core\Logger;

/**
 * Static facade for the Logger class, implementing all PSR-3 log levels.
 */
final class Log {

    public static function emergency(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->emergency($message, $context);

	}

	public static function alert(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->alert($message, $context);

	}

	public static function critical(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->critical($message, $context);

	}

	public static function error(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->error($message, $context);

	}

	public static function warning(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->warning($message, $context);

	}

	public static function notice(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->notice($message, $context);

	}

	public static function info(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->info($message, $context);

	}

	public static function debug(string|\Stringable $message, array $context = []): void {

		Logger::getInstance()->debug($message, $context);

	}

}