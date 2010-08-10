<?php

/**
 * класс описывает мастерский процесс демона
 */
class Thread_Master extends Thread
{

	protected $child_collection;			//коллекция дочерних процессов
	protected $priority = 100;				//приоритет процесса
	protected $child_count = 0;				//текущее количество подпроцессов (детей)
	protected $thread_name = 'master';		//имя процесса (используется для логирования)


	/**
	 * запускаем процесс
	 */
    public function start()
    {
		if(Daemon::$settings['daemonize'])			//если стоит флаг демонизации
		{
			$pid = pcntl_fork();					//форкаем текущий процесс
			if ($pid === - 1) {
				$this->log('Could not fork master process');
			}
		}
		else
		{
			$pid = 0;
		}
        if ($pid == 0) {					//это выполняется в дочернем (мастерском процессе)
            $this->pid = posix_getpid();	//инициализируем pid нового процесса
            foreach(Thread::$signals as $no => $name) {					//задаем обработчики системных сигналов
                if (($name === 'SIGKILL') || ($name == 'SIGSTOP'))
				{
                    continue;
                }
                if (!pcntl_signal($no, array($this,'sighandler') , TRUE))
				{
                    $this->log('Cannot assign ' . $name . ' signal');
                }
            }
			$this->child_collection = new Thread_Collection();			//создаем коллекцию для дочерних процессов
            $this->run();												//собсна, активные действия процесса
            $this->shutdown();											//завершаем процесс
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
		$this->appl->before_runtime();

		//самый главный цикл
        while (TRUE) {
			if(TRUE === $this->appl->master_runtime())		//если функция вернула TRUE
			{
				//прекращаем цикл
				break;
			}
			//ожидаем заданное время для получения сигнала операционной системы
			$this->sigwait(Daemon::$settings['sigwait_sec'],Daemon::$settings['sigwait_nano']);
			
			//если сигнал был получен, вызываем связанную с ним функцию
			pcntl_signal_dispatch();
        }
		
		//выполняем функцию приложения после рабочего цикла
		$this->appl->after_runtime();
    }




	/**
	 * инициализируем выполняемое приложение
	 */
	public function set_application(Application_Base $_appl)
	{
		$this->appl = clone $_appl;
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
	public function spawn_child($_before_function = FALSE,$_runtime_function = FALSE,$_after_function = FALSE)
	{
		if($this->child_collection->getNumber() < Daemon::$settings['max_child_count'])		//если еще есть свободные места для дочерних процессов
		{
			//увеличиваем счетчик
			++$this->child_count;
			$this->log('Spawning a child',2);
			$thread = new Thread_Child;
			//добавляем процесс в коллекцию
			$this->child_collection->push($thread);

			//инициализируем функции
			$thread->set_runtime_function($_runtime_function);
			$thread->set_before_function($_before_function);
			$thread->set_after_function($_after_function);

			//запускаем процесс
			$pid = $thread->start();
			if (-1 === $pid) {
				$this->log('Сould not start child');
			}
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
		$this->child_collection->stop($kill);
		//ждем, пока не остановятся все дочерние процессы
		$this->log('Waiting for all children to shutdown...');
		while($this->child_collection->getNumber() > 0)
		{
			$this->sigwait(Daemon::$settings['sigwait_sec'],Daemon::$settings['sigwait_nano']);
			continue;
		}
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
            foreach($this->child_collection->threads as $k => & $t) {
				if ($t->pid === $pid) {
					$this->child_collection->delete_spawn($t->pid);
				}
            }
			return TRUE;
        }
    }


}
