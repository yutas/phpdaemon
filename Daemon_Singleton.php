<?php

require_once 'Daemon.php';

class Daemon_Singleton extends Daemon
{

	protected static $args_string_pattern = "#^(\b(?<appl_name>[a-z0-9]+)\b)?\s*(\b(?<runmode>start|stop|restart|check)\b)?(?<args_string>.*)?$#";
	protected static $appl_name = '';
	protected static $allowed_appls = array(
		Common_Daemon_Appl_Import::DAEMON_NAME => 'Common_Daemon_Appl_Import',
		Common_Daemon_Appl_Pooh::DAEMON_NAME => 'Common_Daemon_Appl_Pooh',
	);
	protected static $help_message = '';


	protected static function parse_args_string($args_string = '')
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


	public static function apply_args($args)
	{
        //если непонятно, что делать, показываем хелп и выходим
        if(empty(static::$appl_name))
        {
            static::$show_help();
            exit;
		}
		static::$args['daemon']['name'] = static::$appl_name;

		return parent::apply_args($args);
	}


	public static function init($_settings)
	{
		static::$help_message = "\nUsage: ./".basename($_SERVER['argv'][0])."   {".implode('|',array_keys(static::$allowed_appls))."}   {start|stop|restart|check}   <settings>".PHP_EOL.
						PHP_EOL."Possible applications:".PHP_EOL;
		foreach(static::$allowed_appls as $key => $val) {
			static::$help_message .= "\t".$key.' - '.$val.PHP_EOL;
		}
		static::$help_message .= "\nDaemon settings:".PHP_EOL.
								"\t-a  -  keep daemon alive (don't daemonize)".PHP_EOL.
								"\t-v  -  verbose daemon logs".PHP_EOL.
								"\t-o  -  output logs to STDERR".PHP_EOL.
								"\t-h  -  print this help information and exit".PHP_EOL.
		static::set_help_message(static::$help_message);

		parent::init($_settings);
	}


	public static function show_help()
	{
		if(empty(static::$appl_name)) {
			parent::show_help();
		} else {
			parent::show_help();
			$appl_class = static::$getApplName();
			echo $appl_class::get_help();
		}
		echo PHP_EOL;
	}


}
