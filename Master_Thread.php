<?php

class Master_Thread extends Thread
{

	protected $child_collection;						//коллекция дочерних процессов
	protected $appl = FALSE;							//выполняемое приложение
	protected $priority = 100;



	/* @method start
    @description Starts the process.
    @return void
    */
    public function start()
    {
		if(Daemon::$daemonize)
		{
			$pid = pcntl_fork();
			if ($pid === - 1) {
				throw new Exception('Could not fork');
			}
		}
		else
		{
			$pid = 0;
		}
        if ($pid == 0) {
            $this->pid = posix_getpid();
            foreach(Thread::$signals as $no => $name) {
                if (($name === 'SIGKILL') || ($name == 'SIGSTOP')) {
                    continue;
                }
                if (!pcntl_signal($no, array(
                    $this,
                    'sighandler'
                ) , TRUE)) {
                    throw new Exception('Cannot assign ' . $name . ' signal');
                }
            }
			$this->child_collection = new Thread_Collection();
            $this->run();
            $this->shutdown();
        }
        $this->pid = $pid;
        return $pid;
    }






    /* @method run
    @description Runtime of Master process.
    @return void
    */
    public function run()
    {
		Daemon::log('[START] starting daemon (PID ' . posix_getpid() . ')....');
        proc_nice($this->priority);
        gc_enable();
        register_shutdown_function(array(
            $this,
            'onShutdown'
        ));

		//функция приложения
		$this->appl->before_runtime();

		//самый главный цикл
        while (TRUE) {
			$break = $this->appl->master_runtime();
            pcntl_signal_dispatch();
			$this->sigwait($this->sigwait_sec,$this->sigwait_nano);
			if($break)
			{
				break;
			}
        }
		
		//функция приложения
		$this->appl->after_runtime();
    }




	public function set_application(Daemon_Application $appl)
	{
		$this->appl = $appl;
	}




    /* @method spawnWorkers
    @param $n - integer - number of workers to spawn
    @description spawn new workers processes.
    @return boolean - success
    */
	public function spawn_child($_runtime_function = FALSE,$_before_function = FALSE,$_after_function = FALSE)
	{
		Daemon::log('Master is spawning a child',2);
		$thread = new Child_Thread;
		$this->child_collection->push($thread);
		$thread->set_application($this->appl);
		$thread->set_runtime_function($_runtime_function);
		$thread->set_before_function($_before_function);
		$thread->set_after_function($_after_function);
		$pid = $thread->start();
		if (-1 === $pid) {
			Daemon::log('could not start child');
		}
		return $pid;
	}
	
    
    /* @method onShutdown
    @description Called when master is going to shutdown.
    @return void
    */
    public function onShutdown()
    {
		Daemon::log('Master function onShutdown: $this->shutdown='.var_export($this->shutdown,true).' $this->pid='.$this->pid,2);
        if ($this->pid != posix_getpid()) {
            return;
        }
        if ($this->shutdown === TRUE) {
            return;
        }
        $this->shutdown(SIGTERM);
    }

	
    /* @method shutdown
    @param integer System singal's number.
    @description Called when master is going to shutdown.
    @return void
    */
    public function shutdown($signo = FALSE)
    {
		Daemon::log('Master is getting shutdown');
        $this->shutdown = TRUE;
		$this->child_collection->stop();
        exit(0);
    }





    /* @method waitPid
    @description Checks for SIGCHLD.
    @return boolean Success.
    */
	public function waitPid()
    {
        $pid = pcntl_waitpid(-1, $status, WNOHANG);
        return TRUE;
    }
	
}
