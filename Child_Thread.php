<?php

class Child_Thread extends Thread
{
	protected $appl = FALSE;							//выполняемое приложение
	protected $priority = 4;
	protected $appl_function = FALSE;
	
    /**
	 * @method run
	 * @desc Runtime of Child process.
	 * @return void
     */
    public function run()
    {
		Daemon::log('[CHILD] starting child (PID ' . posix_getpid() . ')....');
        proc_nice($this->priority);
//        register_shutdown_function(array(
//            $this,
//            'shutdown'
//        ));
		gc_enable();
        
        while (TRUE) {
			
			if($this->appl_function)
			{
				$break = call_user_func($this->appl_function);
			}

			pcntl_signal_dispatch();
			$this->sigwait($this->sigwait_sec,$this->sigwait_nano);
			
			if($break)
			{
				break;
			}
			
        }
		$this->shutdown();
    }


	public function set_application(Daemon_Application $appl)
	{
		$this->appl = $appl;
	}

	public function set_appl_function($_function)
	{
		$this->appl_function = $_function;
	}
    
    /* @method shutdown
    @param boolean - Hard? If hard, we shouldn't wait for graceful shutdown of the running applications.
    @description
    @return boolean - Ready?
    */
    public function shutdown($hard = FALSE)
    {
		Daemon::log('Child ' . getmypid() . ' is getting shutdown',1);
        @ob_flush();
        posix_kill(posix_getppid() , SIGCHLD);
        exit(0);
    }

	
    /* @method sigint
    @description Handler of the SIGINT (hard shutdown) signal in worker process.
    @return void
    */
    public function sigint()
    {
		Daemon::log('Child ' . getmypid() . ' caught SIGINT',2);
        $this->shutdown(TRUE);
    }

	
    /* @method sigterm
    @description Handler of the SIGTERM (graceful shutdown) signal in worker process.
    @return void
    */
    public function sigterm()
    {
		Daemon::log('Child ' . getmypid() . ' caught SIGTERM',2);
        $this->shutdown();
    }

	
    /* @method sigquit
    @description Handler of the SIGQUIT (graceful shutdown) signal in worker process.
    @return void
    */
    public function sigquit()
    {
		Daemon::log('Child ' . getmypid() . ' caught SIGQUIT',2);
        $this->shutdown = TRUE;
    }

	
    /* @method sigunknown
    @description Handler of non-known signals.
    @return void
    */
    public function sigunknown($signo)
    {
        if (isset(Thread::$signals[$signo])) {
            $sig = Thread::$signals[$signo];
        } else {
            $sig = 'UNKNOWN';
        }
        Daemon::log('Child ' . getmypid() . ' caught signal #' . $signo . ' (' . $sig . ').',2);
    }
}
