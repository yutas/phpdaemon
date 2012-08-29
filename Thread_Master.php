<?php

/**
 * класс описывает мастерский процесс демона
 */
class Thread_Master extends Thread
{
	const MAIN_COLLECTION_NAME = 'main';

    protected $child_collections;            //коллекция дочерних процессов
    protected $priority = 100;              //приоритет процесса
    protected $child_count = 0;             //текущее количество подпроцессов (детей)
    protected $thread_name = 'master';      //имя процесса (используется для логирования)
	protected $pidfile = '';
	protected $shutdown = false;

    /**
     * запускаем процесс
     */
    public function start()
    {
        if( ! Daemon::getSettings('alive'))          //если стоит флаг демонизации
        {
            $pid = pcntl_fork();                    //форкаем текущий процесс
            if ($pid === - 1) {
                $this->log('Could not fork master process');
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
                    $this->log('Cannot assign ' . $name . ' signal');
                }
            }
			$appl_class = get_class($this->appl);
			$this->addChildCollection(self::MAIN_COLLECTION_NAME, $appl_class::getSettings('max_child_count'));		//создаем коллекцию для дочерних процессов
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
        $this->log('starting master (PID ' . posix_getpid() . ')....');

        //задаем приоритет процесса в ОС
        proc_nice($this->priority);

        //включаем сборщик циклических зависимостей
        gc_enable();

        //задаем функцию, которая будет вызываться при завершении процесса
        register_shutdown_function(array(
            $this,
            'onShutdown'
        ));

        //выполняем функцию приложения до рабочего цикла
        $this->appl->runBefore();

        //самый главный цикл
        while (TRUE) {
            if(TRUE === $this->appl->run())      //если функция вернула TRUE
            {
                //прекращаем цикл
                break;
            }

			$this->appl->apiwait(Daemon::getSettings('sigwait'));

            //ожидаем заданное время для получения сигнала операционной системы
            $this->sigwait(Daemon::getSettings('sigwait'));

            //если сигнал был получен, вызываем связанную с ним функцию
            pcntl_signal_dispatch();
        }

        //выполняем функцию приложения после рабочего цикла
        $this->appl->runAfter();
    }





    /**
     * создаем дочерний процесс и определяем выполняемые в нем функции
     * в качестве параметров передаются массивы в виде array(Object,'function_name')
     *
     * @param <user_function> $_before_function
     * @param <user_function> $_runtime_function
     * @param <user_function> $_after_function
     * @return $pid
     */
    public function spawnChild($_before_function = FALSE,$_runtime_function = FALSE,$_after_function = FALSE, $collection_name = self::MAIN_COLLECTION_NAME)
    {
        if($this->canSpawnChild($collection_name))     //если еще есть свободные места для дочерних процессов
        {
            //переоткрываем логи (вдруг файл лога удалили)
            Daemon::openLogs();
            //увеличиваем счетчик
            ++$this->child_count;
            $this->log('Spawning a child',2);
            $thread = new Thread_Child;

            //инициализируем функции
            $thread->setRunFunction($_runtime_function);
            $thread->setRunBeforeFunction($_before_function);
            $thread->setRunAfterFunction($_after_function);

            //запускаем процесс
            $pid = $thread->start();
            if (-1 === $pid) {
                $this->log('Сould not start child');
            }

            //добавляем процесс в коллекцию
            $this->child_collections[$collection_name]->push($thread);

            return $pid;
        }
    }


   /**
    * выполняется при завершении работы процесса
    */
    public function onShutdown()
    {
        if ($this->pid != posix_getpid())
        {
            return;
        }
        if ($this->shutdown === TRUE)
        {
            return;
        }
        $this->shutdown(SIGTERM);
    }


    /**
     * завершение работы мастерского процесса
     */
    public function shutdown($kill = FALSE)
    {
        $this->shutdown = TRUE;
        //останавливаем все дочерние процессы
		foreach($this->child_collections as $name => $collection) {
			$collection->stop($kill);
			if( ! $kill) {
				//ждем, пока не остановятся все дочерние процессы
				$this->log('Waiting for all children of "'.$name.'" collection to shutdown...');
				$this->log('"'.$name.'" collection: '.$collection->getNumber().' of child threads remaining...');
				while($collection->getNumber() > 0)
				{
					$this->sigwait(Daemon::getSettings('sigwait'));
					continue;
				}
			}
		}
        file_put_contents($this->pidfile, '');
        $this->log('Getting shutdown...');
        exit(0);
    }


    /**
     * вызывается при получении SIGCHLD (когда завершается дочерний процесс)
     */
    public function waitPid()
    {
        //получаем pid завершившегося дочернего процесса
        $pid = pcntl_waitpid(-1, $status, WNOHANG);
        if ($pid > 0) {
            //удаляем этот процесс из коллекции
            foreach($this->child_collections as $collection) {
				if($collection->deleteSpawn($pid)) {
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


	public function addChildCollection($name = self::MAIN_COLLECTION_NAME, $limit = 0)
	{
		if( ! empty($name)) {
			$this->child_collections[$name] = new Thread_Collection($limit);
			return true;
		}
	}
}
