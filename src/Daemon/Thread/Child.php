<?php
namespace Daemon\Thread;
use Daemon\Daemon as Daemon;
use Daemon\Utils\Logger;
use Daemon\Utils\Config;
use Daemon\Component\Application\IApplication;

class Child extends Thread
{
    protected $priority = 4;
    protected $runtime_function = FALSE;
    protected $before_function = FALSE;
    protected $after_function = FALSE;
    protected $onshutdown_function = FALSE;

    /**
     * @method run
     * @desc Runtime of Child process.
     * @return void
     */
    public function run()
    {
        try {
            static::log('starting child (PID ' . posix_getpid() . ')....', Logger::L_TRACE);
            proc_nice($this->priority);
            gc_enable();

            call_user_func([$this->appl, IApplication::BASE_ON_RUN_METHOD]);

            while (TRUE) {

                if(TRUE === call_user_func([$this->appl, IApplication::BASE_RUN_METHOD]))
                {
                    break;
                }
                //ожидаем заданное время для получения сигнала операционной системы
                $this->sigwait(Config::get('Daemon.child_sigwait'));

                //если сигнал был получен, вызываем связанную с ним функцию
                pcntl_signal_dispatch();
            }
        } catch(\Exception $e) {
            $this->shutdown();
        }
    }


    /**
     * передаем ссылку на приложение
     */
    public function setApplication(IApplication $appl)
    {
        $this->appl = $appl;
    }


    /**
     * завершение работы
     */
    public function shutdown()
    {
        try {
    		static::log(getmypid() . ' is getting shutdown', Logger::L_DEBUG);
            static::log('Parent PID - '.posix_getppid(), Logger::L_TRACE);
    		$this->onShutdown();
    		parent::shutdown();
        } catch(\Exception $e) {
            exit(1);
        }
    }

   /**
    * выполняется при завершении работы процесса
    */
    public function onShutdown()
    {
		call_user_func([$this->appl, IApplication::BASE_ON_SHUTDOWN_METHOD]);
    }
}
