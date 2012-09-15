<?php

namespace Daemon;

class DaemonSingleton extends Daemon
{

	protected static $args_string_pattern = "#^(\b(?<appl_name>[a-z0-9]+)\b)?\s*(\b(?<runmode>start|stop|restart|check)\b)?(?<args_string>.*)?$#";
	protected static $appl_name = '';
	protected static $allowed_appls = array();
	protected static $help_message = '';


	protected static function parseArgsString($args_string = '')
	{
		$matches = array();
		$args = array();
        //инициализируем runmode
		if(preg_match(static::$args_string_pattern,$args_string,$matches)) {
			static::$runmode = $matches['runmode'];
			static::$appl_name = isset(static::$allowed_appls[$matches['appl_name']]) ? $matches['appl_name'] : '';
			$args = explode(' ',$matches['args_string']);
		}
		return $args;
	}

	public static function getApplName()
	{
		return static::$allowed_appls[static::$appl_name];
	}


	public static function applyArgs($args)
	{
        //если непонятно, что делать, показываем хелп и выходим
        if(empty(static::$appl_name))
        {
            static::showHelp();
            exit;
		}
		static::$args['daemon']['name'] = static::$appl_name;

		return parent::applyArgs($args);
	}


	public static function init($_config)
	{
		parent::init($_config);
	}

	public static function run(array $_config = array(), Thread\Master $_master = null, Application\Base $_appl = null)
	{
		parent::run($_config, $_master);
	}

	public static function showHelp()
	{
		if(empty(static::$appl_name)) {
			parent::showHelp();
		} else {
			parent::showHelp();
			$appl_class = static::getApplName();
			echo "\n".$appl_class::getHelp();
		}
		echo PHP_EOL;
	}

	public static function generateHelpMessage(Application\Base $_appl = null)
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
