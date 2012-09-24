<?php
namespace Daemon\Application;

use \Daemon\Daemon as Daemon;

abstract class Base extends Application implements IApplication
{

    private $config = array(
        'verbose' => 1,
        'max_child_count' => 10,
    );

	private $config_desc = array(
        'verbose' => " - verbose application logs",
        'max_child_count' => " - maximum amount of threads",
	);

    private $master_thread = FALSE;

	public function  __construct($only_help = false)
	{
		parent::__construct($only_help);
		Config::create(__CLASS__, $this->config, $this->config_desc);
		list($this->config, $this->config_desc) = Config::get(__CLASS__);
		if($only_help)
		{
			return;
		}
	}

    public function  __clone(){}

    //инициализируем параметры, переданные через командную строку и через Daemon::init()
    public function applyConfig($_conf)
    {
		if( ! empty($_conf['verbose'])) {
			$_conf['verbose'] = 2;
		}
        $this->config = array_merge($this->config,$_conf);
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
    public function log($_msg,$_verbose = 1)
    {
        if($_verbose <= ($this->config['verbose']))
        {
            Daemon::logWithSender($_msg,'appl');
        }
    }

	public function getConfig($param = null)
	{
		$app_class = get_called_class();
		var_dump($app_class);
		$config = Config::get($app_class)[Config::PARAMS_KEY];
		if( ! empty($param)) {
			if(isset($config[$param])) {
				return $config[$param];
			} else {
				Daemon::log("[ERROR] Undefined config parameter \"".$param."\"");
			}
		}
		return $config;
	}

	public function getConfigDesc($param = null)
	{
		$app_class = get_called_class();
		$config_desc = Config::get($app_class)[Config::DESC_KEY];
		if( ! empty($param)) {
			if(isset($config_desc[$param])) {
				return $config_desc[$param];
			} else {
				Daemon::log("[ERROR] Undefined config parameter \"".$param."\"");
			}
		}
		return $config_desc;
	}

	public static function getHelp()
	{
		$app = get_called_class();
		$object = new $app(true);
		return Config::getHelpMessage($app);
	}

    protected function shutdown()
    {
        posix_kill(posix_getpid(),SIGTERM);
    }
}
