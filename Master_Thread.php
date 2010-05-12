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

		//самый главный цикл
        while (TRUE) {
			$this->appl->master_action();
//            pcntl_signal_dispatch();
            $this->sigwait(1,0);
        }
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
	public function spawn_child($_function = FALSE)
	{
		$thread = new Child_Thread;
		$this->child_collection->push($thread);
		$thread->set_application($this->appl);
		$thread->set_appl_function($_function);
		if (-1 === $thread->start()) {
			Daemon::log('could not start worker');
		}
		return TRUE;
	}
    /* @method stopWorkers
    @param $n - integer - number of workers to stop
    @description stop the workers.
    @return boolean - success
    */
    public function stopWorkers($n = 1)
    {
        $n = (int)$n;
        $i = 0;
        foreach($this->collections['workers']
                ->threads as & $w) {
            if ($i >= $n) {
                break;
            }
            if ($w->shutdown) {
                continue;
            }
            $w->stop();
            ++$i;
        }
        return TRUE;
    }
    /* @method onShutdown
    @description Called when master is going to shutdown.
    @return void
    */
    public function onShutdown()
    {
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
        $this->shutdown = TRUE;
        $this->waitAll($signo);
		
        exit(0);
    }
	
}
