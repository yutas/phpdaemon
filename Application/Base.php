<?php
namespace Daemon\Application;

use \Daemon\Daemon as Daemon;

abstract class Base extends Application implements IApplication
{

    protected static $config = array(
        'verbose' => 1,
        'max_child_count' => 1,
    );

	protected static $config_desc = array(
        'verbose' => " - verbose application logs",
        'max_child_count' => " - maximum amount of threads",
	);

    private $master_thread = FALSE;


	public function  __construct()
	{
		static::mergeConfig();
	}

    public function  __clone(){}

    //инициализируем параметры, переданные через командную строку и через Daemon::init()
    public function applyConfig($_conf)
    {
		if( ! empty($_conf['verbose'])) {
			$_conf['verbose'] = 2;
		}
        static::$config = array_merge(static::$config,$_conf);
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
    public function setMasterThread(\Daemon\Thread\Master $master)
    {
        $this->master_thread = $master;
    }

	protected function getMaster()
	{
		return $this->master_thread;
	}

    //И создал Бог Адама по образу и подобию своему...
    public function spawnChild($_before_function = FALSE, $_runtime_function = FALSE, $_after_function = FALSE, $collection_name = \Daemon\Thread\Master::MAIN_COLLECTION_NAME)
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
        if($_verbose <= (static::$config['verbose']))
        {
            Daemon::logWithSender($_msg,'appl');
        }
    }

	public static function mergeConfig()
	{
		static::$config = array_merge(parent::getConfig(), static::$config);
		static::$config_desc = array_merge(parent::getConfigDesc(), static::$config_desc);
	}

	public static function getConfig($param = null)
	{
		if( ! empty($param)) {
			if(isset(static::$config[$param])) {
				return static::$config[$param];
			} else {
				throw new Exception_Application("Undefined config parameter \"".$param."\"");
			}
		}
		return static::$config;
	}

	public static function getConfigDesc($param = null)
	{
		if( ! empty($param)) {
			if(isset(static::$config_desc[$param])) {
				return static::$config_desc[$param];
			} else {
				throw new Exception_Application("Undefined config parameter \"".$param."\"");
			}
		}
		return static::$config_desc;
	}


    protected function shutdown()
    {
        posix_kill(posix_getpid(),SIGTERM);
    }

	public static function getHelpMessage()
	{
		$config_desc = self::getConfigDesc();
		$help_message = '';
		foreach($config_desc as $name => $desc) {
			$help_message .= "\t--$name$desc\n";
		}
		return $help_message;
	}
}
