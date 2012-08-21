<?php


abstract class Application_Base extends Application implements IApplication
{

    protected static $settings = array(
        'verbose' => 1,
        'max_child_count' => 1,
    );

	protected static $settings_desc = array(
        'verbose' => " - verbose application logs",
        'max_child_count' => " - maximum amount of threads",
	);

    private $master_thread = FALSE;


    public function  __construct(){}

    public function  __clone(){}

    //инициализируем параметры, переданные через командную строку и через Daemon::init()
    public function applySettings($_settings)
    {
		if( ! empty($_settings['verbose'])) {
			$_settings['verbose'] = 2;
		}
        static::$settings = array_merge(static::$settings,$_settings);
    }

    //функция, которая выполняется перед главным циклом
    public function runBefore(){}

    //описывает действие, которое будет повторятся в главном цикле демона
    //когда функция вернет TRUE, процесс завершится
    public function run(){}

    //функция, которая выполняется после главного цикла
    public function runAfter(){}

	//функция, которая выполняется по сигналу SIGUSR1 мастерскому процессу
	public function runSigUsr1(){}

	//функция, которая выполняется по сигналу SIGUSR2 мастерскому процессу
	public function runSigUsr2(){}

    //инициализирует ссылку на главный процесс демона
    public function setMasterThread(Thread_Master $master)
    {
        $this->master_thread = $master;
    }

	protected function getMaster()
	{
		return $this->master_thread;
	}

    //И создал Бог Адама по образу и подобию своему...
    public function spawnChild($_before_function = FALSE, $_runtime_function = FALSE, $_after_function = FALSE, $collection_name = Thread_Master::MAIN_COLLECTION_NAME)
    {
        $appl = clone $this;
        $_before_function = $_before_function ? array($appl,$_before_function) : FALSE;
        $_runtime_function = $_runtime_function ? array($appl,$_runtime_function) : FALSE;
        $_after_function = $_after_function ? array($appl,$_after_function) : FALSE;

        return $this->getMaster()->spawnChild($_before_function, $_runtime_function, $_after_function, $collection_name);
    }

    /**
     * запись в лог от имени приложения
     */
    public static function log($_msg,$_verbose = 1)
    {
        if($_verbose <= (static::$settings['verbose']))
        {
            Daemon::logWithSender($_msg,'appl');
        }
    }

	public static function mergeSettings()
	{
		static::$settings = array_merge(parent::getSettings(), static::$settings);
		static::$settings_desc = array_merge(parent::getSettingsDesc(), static::$settings_desc);
	}

	public static function getSettings($param = null)
	{
		if( ! empty($param)) {
			if(isset(static::$settings[$param])) {
				return static::$settings[$param];
			} else {
				throw new Exception_Application("Undefined settings parameter \"".$param."\"");
			}
		}
		return static::$settings;
	}

	public static function getSettingsDesc($param = null)
	{
		if( ! empty($param)) {
			if(isset(static::$settings_desc[$param])) {
				return static::$settings_desc[$param];
			} else {
				throw new Exception_Application("Undefined settings parameter \"".$param."\"");
			}
		}
		return static::$settings_desc;
	}


    protected function shutdown()
    {
        posix_kill(posix_getpid(),SIGTERM);
    }

	public static function getHelpMessage()
	{
		$settings_desc = self::getSettingsDesc();
		$help_message = '';
		foreach($settings_desc as $name => $desc) {
			$help_message .= "\t--$name$desc\n";
		}
		return $help_message;
	}
}
