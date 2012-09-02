<?php
namespace Daemon\Thread;

class Child extends Thread
{
    protected $priority = 4;
    protected $runtime_function = FALSE;
    protected $before_function = FALSE;
    protected $after_function = FALSE;
    protected $thread_name = 'child';

    /**
     * @method run
     * @desc Runtime of Child process.
     * @return void
     */
    public function run()
    {
        $this->log('starting child (PID ' . posix_getpid() . ')....',2);
        proc_nice($this->priority);
        gc_enable();

        if( $this->before_function !== FALSE )      //если задана функция до рабочего цикла
        {
            //выполняем ее
            call_user_func($this->before_function);
        }

        if( $this->runtime_function !== FALSE )     //если задана функция рабочего цикла
        {
            while (TRUE) {
                if(TRUE === call_user_func($this->runtime_function))    //если функция вернула TRUE
                {
                    //прекращаем цикл
                    break;
                }
                //ожидаем заданное время для получения сигнала операционной системы
                $this->sigwait(Daemon::getSettings('sigwait'));

                //если сигнал был получен, вызываем связанную с ним функцию
                pcntl_signal_dispatch();
            }
        }

        if( $this->after_function !== FALSE )       //если задана функция после рабочего цикла
        {
            //выполняем и ее
            call_user_func($this->after_function);
        }
    }


    /**
     * передаем ссылку на приложение
     */
    public function setApplication(Application_Base $_appl)
    {
        $this->appl = clone $appl;
    }


    /**
     * Устанавливаем функцию, которая будет выполнятся до главного цикла
     */
    public function setRunBeforeFunction($_function)
    {
        if(is_callable($_function))
        {
            $this->before_function = $_function;
        }
    }


    /**
     * ... в главном цикле
     */
    public function setRunFunction($_function)
    {
        if(is_callable($_function))
        {
            $this->runtime_function = $_function;
        }
    }


    /**
     * ... после главного цикла
     */
    public function setRunAfterFunction($_function)
    {
        if(is_callable($_function))
        {
            $this->after_function = $_function;
        }
    }

    /**
     * завершение работы
     */
    public function shutdown()
    {
        $this->log(getmypid() . ' is getting shutdown',1);
        $this->log('Parent PID - '.posix_getppid(),2);
        exit(0);
    }

}
