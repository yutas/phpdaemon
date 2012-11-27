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

    public function spawnChild($_before = FALSE, $_runtime = FALSE, $_after = FALSE, $_onshutdown = FALSE, $collection_name = \Daemon\Thread\Master::MAIN_COLLECTION_NAME)
    {
        $appl = clone $this;
        $_before = $_before ? array($appl,$_before) : FALSE;
        $_runtime = $_runtime ? array($appl,$_runtime) : FALSE;
        $_after = $_after ? array($appl,$_after) : FALSE;
        $_onshutdown = $_onshutdown ? array($appl,$_onshutdown) : FALSE;

        return $this->getMaster()->spawnChild($_before, $_runtime, $_after, $_onshutdown, $collection_name);
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
	public function onShutdown()
	{
	}

	public function hasApiSupport()
	{
		return $this->api_support;
	}

	public function sendToApi()
	{
	}

}
