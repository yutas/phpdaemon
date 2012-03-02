<?php


abstract class Application_Base
{

    protected static $settings = array(
        'verbose' => 1,
    );

	protected static $settings_desc = array(
        'verbose' => " - verbose application logs",
	);

    private $master_thread = FALSE;


    public function  __construct(){}

    public function  __clone(){}

    //инициализируем параметры, переданные через командную строку и через Daemon::init()
    public function apply_settings($_settings)
    {
		if( ! empty($_settings['verbose'])) {
			$_settings['verbose'] = 2;
		}
        static::$settings = array_merge(static::$settings,$_settings);
    }

    //функция, которая выполняется перед главным циклом
    public function before_runtime(){}

    //описывает действие, которое будет повторятся в главном цикле демона
    //когда функция вернет TRUE, процесс завершится
    public function master_runtime(){}

    //функция, которая выполняется после главного цикла
    public function after_runtime(){}

	//функция, которая выполняется по сигналу SIGUSR1 мастерскому процессу
	public function sigusr1_function(){}

	//функция, которая выполняется по сигналу SIGUSR2 мастерскому процессу
	public function sigusr2_function(){}

    //инициализирует ссылку на главный процесс демона
    public function set_master_thread(Thread_Master $master)
    {
        $this->master_thread = $master;
    }

	protected function getMaster()
	{
		return $this->master_thread;
	}

    //И создал Бог Адама по образу и подобию своему...
    public function spawn_child($_before_function = FALSE, $_runtime_function = FALSE, $_after_function = FALSE, $collection_name = Thread_Master::MAIN_COLLECTION_NAME)
    {
        $appl = clone $this;
        $_before_function = $_before_function ? array($appl,$_before_function) : FALSE;
        $_runtime_function = $_runtime_function ? array($appl,$_runtime_function) : FALSE;
        $_after_function = $_after_function ? array($appl,$_after_function) : FALSE;

        return $this->getMaster()->spawn_child($_before_function, $_runtime_function, $_after_function, $collection_name);
    }

    /**
     * запись в лог от имени приложения
     */
    public static function log($_msg,$_verbose = 1)
    {
        if($_verbose <= (static::$settings['verbose']))
        {
            Daemon::log_with_sender($_msg,'appl');
        }
    }



    protected function shutdown()
    {
        posix_kill(posix_getpid(),SIGTERM);
    }


	public static function get_settings()
	{
		return self::$settings;
	}


	public static function get_settings_desc()
	{
		return self::$settings_desc;
	}
}
