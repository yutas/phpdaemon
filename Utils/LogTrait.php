<?php

namespace Daemon\Utils;

trait LogTrait
{
	public static function log($msg, $verbose = Logger::L_QUIET, $to_stderr = false)
	{
        if($verbose <= Config::get('Logger.verbose'))        //если уровень подробности записи не выше ограничения в настройках
		{
			Logger::logWithSender($msg, static::getLogName(), $to_stderr);
		}
	}

	public static function logError($_msg, $_to_stderr = FALSE)
	{
		$_msg = '[ERROR] '.$_msg;
		static::log($_msg, Logger::L_ERROR, $_to_stderr);
	}


	public static function getLogName()
	{
		return Logger::getLogClassName(get_called_class());
	}
}
