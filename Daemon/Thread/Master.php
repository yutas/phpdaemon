<?php
namespace Daemon\Thread;

use Daemon\Daemon;
use Daemon\Thread\Thread;
use Daemon\Thread\Child as Thread_Child;
use Daemon\Thread\Collection as Thread_Collection;
use Daemon\Utils\Config;
use Daemon\Utils\Logger;
use Daemon\Utils\LogTrait;
use Daemon\Component\Application\Application;

/**
 * класс описывает мастерский процесс демона
 */
class Master extends Thread
{
	use LogTrait;

	const MAIN_COLLECTION_NAME = 'main';

    protected $child_collections;            //коллекция дочерних процессов
    protected $priority = 100;              //приоритет процесса
    protected $child_count = 0;             //текущее количество подпроцессов (детей)
	protected $pidfile = '';
	protected $shutdown = false;

    /**
     * запускаем процесс
     */
    public function start()
    {
        if( ! Config::get('Daemon.alive'))          //если стоит флаг демонизации
        {
            $pid = pcntl_fork();                    //форкаем текущий процесс
            if ($pid === - 1) {
                static::log('Could not fork master process', Logger::L_ERROR);
            }
        }
        else
        {
            $pid = 0;
        }
        if ($pid == 0) {                    //это выполняется в дочернем (мастерском процессе)
            $this->pid = posix_getpid();    //инициализируем pid нового процесса
			$this->pidfile = Daemon::$pidfile;

			//записываем pid процесса в pid-файл
			file_put_contents($this->pidfile, $this->pid);

            foreach(Thread::$signals as $no => $name) {                 //задаем обработчики системных сигналов
                if (($name === 'SIGKILL') || ($name == 'SIGSTOP'))
                {
                    continue;
                }
                if (!pcntl_signal($no, array($this,'sighandler') , TRUE))
                {
                    static::log('Cannot assign ' . $name . ' signal', Logger::L_ERROR);
                }
            }

			$this->addChildCollection(self::MAIN_COLLECTION_NAME, Config::get('Application.max_child_count'));		//создаем коллекцию для дочерних процессов
            $this->run();																						//собсна, активные действия процесса
            $this->shutdown();																					//завершаем процесс
        }
        $this->pid = $pid;
        return $pid;
    }


    /**
     * мастерский рабочий цикл
     */
    public function run()
    {
		try {
			static::log('starting master (PID ' . posix_getpid() . ')....');

			//задаем приоритет процесса в ОС
			proc_nice($this->priority);

			//включаем сборщик циклических зависимостей
			gc_enable();

			//выполняем функцию приложения до рабочего цикла
			call_user_func([$this->appl, 'baseOnRun']);

			//самый главный цикл
			while (TRUE) {
				if(TRUE === call_user_func([$this->appl, 'baseRun'])) {
					//прекращаем цикл
					break;
				}

                $this->waitPid();

				//ожидаем заданное время для получения сигнала операционной системы
				$this->sigwait();

				//если сигнал был получен, вызываем связанную с ним функцию
				pcntl_signal_dispatch();
			}

		} catch(\Exception $e) {
            $this->shutdown();
		}
    }





    /**
     * создаем дочерний процесс и определяем выполняемые в нем функции
     * в качестве параметров передаются массивы в виде array(Object,'function_name')
     *
     * @param <user_function> $_before
     * @param <user_function> $_runtime
     * @param <user_function> $_after
     * @return $pid
     */
    public function spawnChild(Application $appl, $collection_name = self::MAIN_COLLECTION_NAME)
    {
        if($this->canSpawnChild($collection_name))     //если еще есть свободные места для дочерних процессов
        {
            //переоткрываем логи (вдруг файл лога удалили)
            Logger::openLogs();

            //увеличиваем счетчик
            ++$this->child_count;
            static::log('Spawning a child', Logger::L_DEBUG);
            $thread = new Thread_Child;

            //инициализируем функции
            $thread->setApplication($appl);

            //запускаем процесс
            $pid = $thread->start();
            if (-1 === $pid) {
				throw new \Exception('Сould not start child');
            }

            //добавляем процесс в коллекцию
            $this->child_collections[$collection_name]->push($thread);

            return $pid;
        }
    }


    /**
     * инициализируем выполняемое приложение
     */
    public function setApplication(Application $appl)
    {
        $this->appl = $appl;
    }

   /**
    * выполняется при завершении работы процесса
    */
    public function onShutdown()
    {
		call_user_func([$this->appl, 'baseOnShutdown']);
    }


    /**
     * завершение работы мастерского процесса
     */
    public function shutdown($kill = FALSE)
    {
        try {
            $this->shutdown = TRUE;
            //останавливаем все дочерние процессы
    		foreach($this->child_collections as $name => $collection) {
    			$collection->stop($kill);
    			if( ! $kill) {
    				//ждем, пока не остановятся все дочерние процессы
                    static::log('Waiting for all children of "'.$name.'" collection to shutdown...', Logger::L_INFO);
    				while($collection->getNumber() > 0)
    				{
                        static::log('"'.$name.'" collection: '.$collection->getNumber().' of child threads remaining...', Logger::L_INFO);
                        $this->waitPid();
    					$this->sigwait();
    					continue;
    				}
    			}
    		}
            file_put_contents($this->pidfile, '');
            static::log('Getting shutdown...');
    		$this->onShutdown();
    		parent::shutdown();
        } catch(\Exception $e) {
            static::log($e->getMessage(), $e->getCode(), $e->getThrower());
            exit(1);
        }
    }

    public function sigwait($millisec = null)
    {
        parent::sigwait(empty($millisec) ? Config::get('Daemon.master_sigwait') : $millisec);
    }


    /**
     * вызывается при получении SIGCHLD (когда завершается дочерний процесс)
     */
    public function waitPid()
    {
        //получаем pid завершившегося дочернего процесса
        $pid = pcntl_waitpid(-1, $status);
        static::log("Child with pid $pid stoped working", Logger::L_TRACE);
        if ($pid > 0) {
            //удаляем этот процесс из коллекции
            foreach($this->child_collections as $collection) {
				if($collection->deleteChild($pid)) {
					break;
				}
            }
            return TRUE;
        }
    }


	public function canSpawnChild($collection_name = self::MAIN_COLLECTION_NAME)
	{
		return $this->child_collections[$collection_name]->canSpawnChild();
	}

	/**
	 * sigusr1
	 *
	 * @access public
	 * @return void
	 */
    public function sigusr1()
    {
		return $this->appl->runSigUsr1();
    }

	/**
	 * sigusr2
	 *
	 * @access public
	 * @return void
	 */
    public function sigusr2()
    {
		return $this->appl->runSigUsr2();
    }


	/**
	 * sighup - при получении сигнала SIGUP обновляем конфигурацию системы из файла, переданного при запуске,
	 *			и ретранслируем этот же сигнал всем дочерним процессам
	 *
	 * @access public
	 * @return void
	 */
	public function sighup()
	{
		parent::sighup();
		foreach($this->child_collections as $collection)
		{
			$collection->signal(SIGHUP);
		}

	}

	public function addChildCollection($name = self::MAIN_COLLECTION_NAME, $limit = 0)
	{
		if(empty($name))
		{
			throw new \Exception("Collection name must be set");
		}
		if(! empty($this->child_collections[$name]))
	   	{
			throw new \Exception("Collection with name {$name} already exists");
		}

		$this->child_collections[$name] = new Thread_Collection($limit);
		return true;
	}

	public function deleteChildCollection($name = self::MAIN_COLLECTION_NAME)
	{
		if( ! empty($name) && ! empty($this->child_collections[$name])) {
			$collection = $this->child_collections[$name];
			$collection->stop();
			//ждем, пока не остановятся все дочерние процессы
			static::log('Waiting for all children of "'.$name.'" collection to shutdown...', Logger::L_INFO);
			static::log('"'.$name.'" collection: '.$collection->getNumber().' of child threads remaining...', Logger::L_INFO);
			while($collection->getNumber() > 0)
			{
				$this->sigwait(Config::get('Daemon.master_sigwait'));
				continue;
			}
			unset($this->child_collections[$name]);
			return true;
		}
	}
}
