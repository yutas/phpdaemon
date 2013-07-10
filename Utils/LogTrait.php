<?php

namespace Daemon\Utils;

trait LogTrait
{
	public static function log($msg, $level = Logger::L_QUIET, $to_stderr = false, $thrower = null)
	{
        Logger::log($msg, $level, empty($thrower) ? Logger::getSenderName(get_called_class()) : $thrower, $to_stderr);
	}
}