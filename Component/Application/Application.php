<?php
namespace Daemon\Component\Application;

use Daemon\Daemon;
use Daemon\Utils\Config;
use Daemon\Thread\Master;
use Daemon\Utils\LogTrait;

abstract class Application implements IApplication
{
	use LogTrait;

	const LOG_NAME = 'Application';

    private $master_thread = false;
	protected $api_support = false;


    public function  __clone(){}

	//функция, которая выполняется по сигналу SIGUSR1 мастерскому процессу
	public function runSigUsr1(){}

	//функция, которая выполняется по сигналу SIGUSR2 мастерскому процессу
	public function runSigUsr2(){}

    //инициализирует ссылку на главный процесс демона
    final public function setMasterThread(Master $master)
    {
        $this->master_thread = $master;
    }

	protected function getMaster()
	{
		return $this->master_thread;
	}

    public function spawnChild(IApplication $appl, $collection_name = Master::MAIN_COLLECTION_NAME)
    {
        return $this->getMaster()->spawnChild($appl, $collection_name);
    }

    protected function shutdown()
    {
        posix_kill(posix_getpid(),SIGTERM);
    }

	public function hasApiSupport()
	{
		return $this->api_support;
	}

	public function sendToApi()
	{
	}

}
