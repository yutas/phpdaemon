<?php

require_once INCLUDE_DIR_3D.'phpdaemon/Daemon.php';

class Common_Daemon extends Daemon
{

	protected $args_string_pattern = "#^(\b(?<appl_name>[a-z0-9]+)\b)?\s*(\b(?<runmode>start|stop|restart|check)\b)?(?<args_string>.*)?$#";
	protected $appl_name = '';
	protected $allowed_appls = array(
		Common_Daemon_Appl_Import::DAEMON_NAME => 'Common_Daemon_Appl_Import',
		Common_Daemon_Appl_Pooh::DAEMON_NAME => 'Common_Daemon_Appl_Pooh',
	);
	protected static $help_message = '';


	protected function parse_args_string($args_string = '')
	{
		$matches = array();
		$args = array();
        //инициализируем runmode
		if(preg_match($this->args_string_pattern,$args_string,$matches)) {
			$this->runmode = $matches['runmode'];
			$this->appl_name = isset($this->allowed_appls[$matches['appl_name']]) ? $matches['appl_name'] : '';
			$args = explode(' ',$matches['args_string']);
		}
		return $args;
	}

	public function getApplName()
	{
		return $this->allowed_appls[$this->appl_name];
	}


	public function apply_args($args)
	{
        //если непонятно, что делать, показываем хелп и выходим
        if(empty($this->appl_name))
        {
            $this->show_help();
            exit;
		}
		$this->args['daemon']['name'] = $this->appl_name;

		return parent::apply_args($args);
	}


	public function init($_settings)
	{
		static::$help_message = "\nUsage: ./".basename($_SERVER['argv'][0])."   {".implode('|',array_keys($this->allowed_appls))."}   {start|stop|restart|check}   <settings>".PHP_EOL.
						PHP_EOL."Possible applications:".PHP_EOL;
		foreach($this->allowed_appls as $key => $val) {
			static::$help_message .= "\t".$key.' - '.$val.PHP_EOL;
		}
		static::$help_message .= "\nDaemon settings:".PHP_EOL.
								"\t-a  -  keep daemon alive (don't daemonize)".PHP_EOL.
								"\t-v  -  verbose daemon logs".PHP_EOL.
								"\t-o  -  output logs to STDERR".PHP_EOL.
								"\t-m  -  max child count".PHP_EOL.
								"\t-h  -  print this help information and exit".PHP_EOL.
		$this->set_help_message(static::$help_message);

		parent::init($_settings);
	}


	public function show_help()
	{
		if(empty($this->appl_name)) {
			parent::show_help();
		} else {
			parent::show_help();
			$appl_class = $this->getApplName();
			echo $appl_class::get_help();
		}
		echo PHP_EOL;
	}


}
