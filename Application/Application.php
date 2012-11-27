<?php
namespace Daemon\Application;
use \Daemon\Daemon;
use \Daemon\Utils\Config;

abstract class Application implements IApplication
{
	use \Daemon\Utils\LogTrait;

	const LOG_NAME = 'Application';

    private $master_thread = false;
	protected $api_support = false;


	//TODO: при хранении конфигов в файлах убрать дурацкий параметр $only_help
    public function  __clone(){}

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

    public function spawnChild($_before_function = FALSE, $_runtime_function = FALSE, $_after_function = FALSE, $collection_name = \Daemon\Thread\Master::MAIN_COLLECTION_NAME)
    {
        $appl = clone $this;
        $_before_function = $_before_function ? array($appl,$_before_function) : FALSE;
        $_runtime_function = $_runtime_function ? array($appl,$_runtime_function) : FALSE;
        $_after_function = $_after_function ? array($appl,$_after_function) : FALSE;

        return $this->getMaster()->spawnChild($_before_function, $_runtime_function, $_after_function, $collection_name);
    }

    protected function shutdown()
    {
        posix_kill(posix_getpid(),SIGTERM);
    }

	/**
	 * public function onShutdown() - is called in Master process on shutdown
	 *
	 * @access public
	 * @return void
	 */
	//TODO: проверить все подобные функции
	public function onShutdown() {}

	public function hasApiSupport()
	{
		return $this->api_support;
	}

	public function sendToApi()
	{
	}

}
