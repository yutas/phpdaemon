<?php

namespace Daemon\Utils;

trait LogTrait
{
	public static function log($msg, $level = Logger::L_QUIET, $thrower = null, $to_stderr = false)
	{
        Logger::log($msg, $level, empty($thrower) ? Logger::getSenderName(get_called_class()) : $thrower, $to_stderr);
	}
}