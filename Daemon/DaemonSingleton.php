<?php

namespace Daemon;

class DaemonSingleton extends Daemon
{

	protected static $args_string_pattern = "#^(\b(?<appl_name>[a-z0-9]+)\b)?\s*(\b(?<runmode>start|stop|restart|check)\b)?(?<args_string>.*)?$#";
	protected static $allowed_appls = array();
	protected static $help_message = '';


	protected static function parseArgsString($args_string = '')
	{
		$matches = array();
		$args = array();
		if(preg_match(static::$args_string_pattern,$args_string,$matches)) {
			//инициализируем runmode
			static::setRunmode($matches['runmode']);
			//инициализируем приложение
			static::setApplication($matches['appl_name']);
			$args = explode(' ',$matches['args_string']);
		} else {
			static::$runmode = static::RUNMODE_HELP;
		}
		return $args;
	}

	public static function setApplication($appl_name)
	{
		$class_name = static::$allowed_appls[$appl_name];
		if( ! empty($class_name) && class_exists($class_name))
		{
			static::$appl = new $class_name(static::$runmode == static::RUNMODE_HELP);
		}
	}

	public static function generateHelpMessage()
	{
		static::$help_message = "Usage: ./".basename($_SERVER['argv'][0])."   {".implode('|',array_keys(static::$allowed_appls))."}   {start|stop|restart|check}   <config>".PHP_EOL.
						PHP_EOL."\tPossible applications:".PHP_EOL;
		foreach(static::$allowed_appls as $key => $val) {
			static::$help_message .= "\t".$key.' - '.$val.PHP_EOL;
		}
		static::$help_message .= PHP_EOL."\tDaemon config:".PHP_EOL.
								"\t-a  -  keep daemon alive (don't daemonize)".PHP_EOL.
								"\t-v  -  verbose daemon logs".PHP_EOL.
								"\t-o  -  output logs to STDERR".PHP_EOL.
								"\t-s  -  sigwait time (in microseconds)".PHP_EOL.
								"\t-p  -  directory for pid file".PHP_EOL.
								"\t-l  -  directory for log file".PHP_EOL.
								"\t-h  -  print this help information and exit".PHP_EOL;
	}


}
