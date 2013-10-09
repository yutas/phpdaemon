<?php
namespace Daemon\Thread;
use Daemon\Daemon as Daemon;
use Daemon\Utils\Logger;
use Daemon\Utils\Config;
use Daemon\Component\Application\Application;

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

            call_user_func([$this->appl, 'baseOnRun']);

            while (TRUE) {

                if(TRUE === call_user_func([$this->appl, 'baseRun']))
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
    public function setApplication(Application $appl)
    {
        $this->appl = $appl;
    }


    /**
     * завершение работы
     */
    public function shutdown()
    {
        try {
    		$this->onShutdown();
            static::log(getmypid() . ' is getting shutdown', Logger::L_DEBUG);
            static::log('Parent PID - '.posix_getppid(), Logger::L_TRACE);
            $this->signal(posix_getppid(), SIGCHLD);
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
		call_user_func([$this->appl, 'baseOnShutdown']);
    }
}
