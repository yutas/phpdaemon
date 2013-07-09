<?php

namespace Daemon\Utils;

trait LogTrait
{
	public static function log($msg, $level = Logger::L_QUIET, $to_stderr = false)
	{
        Logger::log($msg, $level, Logger::getSenderName(get_called_class()), $to_stderr);
	}
}