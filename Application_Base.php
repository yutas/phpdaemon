<?php


abstract class Application_Base
{

	protected static $settings = array(
		'verbose' => 1,
	);
	private $master_thread = FALSE;


	public function  __construct(){}

	public function  __clone(){}

	//инициализируем параметры, переданные через командную строку и через Daemon::init()
	public function apply_settings($_settings)
	{
		self::$settings = array_merge(self::$settings,$_settings);
	}

	//функция, которая выполняется перед главным циклом
	public function before_runtime(){}

	//описывает действие, которое будет повторятся в главном цикле демона
	//когда функция вернет TRUE, процесс завершится
	public function master_runtime(){}

	//функция, которая выполняется после главного цикла
	public function after_runtime(){}

	//инициализирует ссылку на главный процесс демона
	public function set_master_thread(Thread_Master $master)
	{
		$this->master_thread = $master;
	}

	//И создал Бог Адама по образу и подобию своему...
	public function spawn_child($_before_function = FALSE,$_runtime_function = FALSE,$_after_function = FALSE)
	{
		$appl = clone $this;
		$_before_function = $_before_function ? array($appl,$_before_function) : FALSE;
		$_runtime_function = $_runtime_function ? array($appl,$_runtime_function) : FALSE;
		$_after_function = $_after_function ? array($appl,$_after_function) : FALSE;
		
		$this->master_thread->spawn_child($_before_function,$_runtime_function,$_after_function);
	}

	/**
	 * запись в лог от имени приложения
	 */
	public static function log($_msg,$_verbose = 1)
	{
		if($_verbose <= self::$settings['verbose'])
		{
			Daemon::log_with_sender($_msg,'appl');
		}
	}



	protected function shutdown()
	{
		posix_kill(posix_getpid(),SIGTERM);
	}

}