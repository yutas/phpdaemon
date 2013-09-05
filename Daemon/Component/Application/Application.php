<?php
namespace Daemon\Component\Application;

use Daemon\Daemon;
use Daemon\Utils\Config;
use Daemon\Thread\Master;
use Daemon\Utils\LogTrait;
use Daemon\Utils\ExceptionTrait;
use Daemon\Utils\Logger;
use Daemon\Component\Exception\Exception;

abstract class Application
{
	use LogTrait, ExceptionTrait;

	const LOG_NAME = 'Application';

    private $master_thread = false;
	protected $api_support = false;

    final public function baseOnRun()
    {
        try {
            static::log("'onRun' method running", Logger::L_TRACE);
            $this->onRun();
        } catch (Exception $e) {
            static::log($e->getMessage(), Logger::L_FATAL, $e->getThrower());
            // ошибки в методе onRun всегда фатальны, поэтому пробрасываем исключение в управляющий процесс для его завершения
            static::throwException($e->getMessage(), Logger::L_FATAL, $e);
        } catch (\Exception $e) {
            static::log($e->getMessage(), Logger::L_FATAL);
            // ошибки в методе onRun всегда фатальны, поэтому пробрасываем исключение в управляющий процесс для его завершения
            static::throwException($e->getMessage(), Logger::L_FATAL, $e);
        }
    }

    final public function baseRun()
    {
        try {
            static::log("'run' method running", Logger::L_TRACE);
            $this->run();
        } catch (Exception $e) {
            static::log($e->getMessage(), $e->getCode(), $e->getThrower());
            if (Logger::L_FATAL === $e->getCode()) {
                // в случае фатальной ошибки пробрасываем исключение в управляющий процесс для его завершения
                static::throwException($e->getMessage(), $e->getCode(), $e);
            }
        } catch (\Exception $e) {
            static::log($e->getMessage(), Logger::L_FATAL);
            // если пропустили обычное исключение, кинем фатальную ошибку
            static::throwException($e->getMessage(), Logger::L_FATAL, $e);
        }
    }

    final public function baseOnShutdown()
    {
        try {
            static::log("'onShutdown' method running", Logger::L_TRACE);
            $this->onShutdown();
        } catch (Exception $e) {
            static::log($e->getMessage(), $e->getCode(), $e->getThrower());
            static::throwException($e->getMessage(), Logger::L_FATAL, $e);
        } catch (\Exception $e) {
            static::log($e->getMessage(), Logger::L_FATAL);
            static::throwException($e->getMessage(), Logger::L_FATAL, $e);
        }
    }

    public function  __clone(){}

	//функция, которая выполняется по сигналу SIGUSR1 мастерскому процессу
	public function runSigUsr1(){}

	//функция, которая выполняется по сигналу SIGUSR2 мастерскому процессу
	public function runSigUsr2(){}

    //функция выполняется перед главным циклом
    abstract public function onRun();

    //описывает действие, которое будет повторятся в главном цикле демона
    //когда функция вернет TRUE, процесс завершится
    abstract public function run();

    //функция выполняется при завершении работы демона
    abstract public function onShutdown();

    //инициализирует ссылку на главный процесс демона
    final public function setMasterThread(Master $master)
    {
        $this->master_thread = $master;
    }

	protected function getMaster()
	{
		return $this->master_thread;
	}

    public function spawnChild(Application $appl, $collection_name = Master::MAIN_COLLECTION_NAME)
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

    public function getLogName()
    {
        return static::LOG_NAME;
    }

}
