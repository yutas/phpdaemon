<?php

class Thread_Child extends Thread
{
	protected $appl = FALSE;							//выполняемое приложение
	protected $priority = 4;
	protected $runtime_function = FALSE;
	protected $before_function = FALSE;
	protected $after_function = FALSE;
	
    /**
	 * @method run
	 * @desc Runtime of Child process.
	 * @return void
     */
    public function run()
    {
		self::log('[START] starting child (PID ' . posix_getpid() . ')....');
        proc_nice($this->priority);
//        register_shutdown_function(array(
//            $this,
//            'shutdown'
//        ));
		gc_enable();

		if( !is_null($this->before_function))
		{
			call_user_func($this->before_function);
		}

        while (TRUE) {
			
			if($this->runtime_function)
			{
				$break = call_user_func($this->runtime_function);
			}

			pcntl_signal_dispatch();
			$this->sigwait($this->sigwait_sec,$this->sigwait_nano);
			
			if($break)
			{
				break;
			}
			
        }

		if( !is_null($this->after_function))
		{
			call_user_func($this->after_function);
		}
    }


	/**
	 * передаем ссылку на приложение
	 */
	public function set_application($appl)
	{
		$this->appl = $appl;
	}

	/**
	 * Устанавливаем функцию, которая будет выполнятся в главном цикле
	 */
	public function set_runtime_function($_function)
	{
		$this->runtime_function = $_function;
	}

	/**
	 * ... до главного цикла
	 */
	public function set_before_function($_function)
	{
		$this->before_function = $_function;
	}

	/**
	 * ... после главного цикла
	 */
	public function set_after_function($_function)
	{
		$this->after_function = $_function;
	}





    /* @method shutdown
    @param boolean - Hard? If hard, we shouldn't wait for graceful shutdown of the running applications.
    @description
    @return boolean - Ready?
    */
    public function shutdown($hard = FALSE)
    {
		self::log(getmypid() . ' is getting shutdown',1);
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
		self::log(getmypid() . ' caught SIGINT',2);
        $this->shutdown(TRUE);
    }

	
    /* @method sigterm
    @description Handler of the SIGTERM (graceful shutdown) signal in worker process.
    @return void
    */
    public function sigterm()
    {
		self::log(getmypid() . ' caught SIGTERM',2);
        $this->shutdown();
    }

	
    /* @method sigquit
    @description Handler of the SIGQUIT (graceful shutdown) signal in worker process.
    @return void
    */
    public function sigquit()
    {
		self::log(getmypid() . ' caught SIGQUIT',2);
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
        self::log(getmypid() . ' caught signal #' . $signo . ' (' . $sig . ').',2);
    }


	public static function log($_msg,$_verbose = 1)
	{
		self::log_with_sender($_msg,'child',$_verbose);
	}
}
