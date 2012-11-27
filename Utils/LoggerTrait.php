<?php

namespace Daemon\Utils;
use Daemon\Utils\Logger;

trait LoggerTrait
{
	public static function log($msg, $to_stderr = false)
	{
        Logger::logWithSender($msg, strtoupper(get_called_class()), $to_stderr);
	}
}
