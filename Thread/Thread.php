<?php
namespace Daemon\Thread;

use \Daemon\Daemon as Daemon;

abstract class Thread
{
    protected $appl;									//выполняемое приложение
    public $pid;
    protected $priority = 4;                            //приоритет процесса в ОС
    protected $thread_name = 'unnamed_thread';          //имя процесса
    public static $signalsno = array(
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        10,
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18,
        19,
        20,
        21,
        22,
        23,
        24,
        25,
        26,
        27,
        28,
        29,
        30,
        31
    );
    public static $signals = array(
        SIGHUP => 'SIGHUP',
        SIGINT => 'SIGINT',
        SIGQUIT => 'SIGQUIT',
        SIGILL => 'SIGILL',
        SIGTRAP => 'SIGTRAP',
        SIGABRT => 'SIGABRT',
        7 => 'SIGEMT',
        SIGFPE => 'SIGFPE',
        SIGKILL => 'SIGKILL',
        SIGBUS => 'SIGBUS',
        SIGSEGV => 'SIGSEGV',
        SIGSYS => 'SIGSYS',
        SIGPIPE => 'SIGPIPE',
        SIGALRM => 'SIGALRM',
        SIGTERM => 'SIGTERM',
        SIGURG => 'SIGURG',
        SIGSTOP => 'SIGSTOP',
        SIGTSTP => 'SIGTSTP',
        SIGCONT => 'SIGCONT',
        SIGCHLD => 'SIGCHLD',
        SIGTTIN => 'SIGTTIN',
        SIGTTOU => 'SIGTTOU',
        SIGIO => 'SIGIO',
        SIGXCPU => 'SIGXCPU',
        SIGXFSZ => 'SIGXFSZ',
        SIGVTALRM => 'SIGVTALRM',
        SIGPROF => 'SIGPROF',
        SIGWINCH => 'SIGWINCH',
        28 => 'SIGINFO',
        SIGUSR1 => 'SIGUSR1',
        SIGUSR2 => 'SIGUSR2',
    );

    /* @method start
    @description Starts the process.
    @return void
    */
    public function start()
    {
        $pid = pcntl_fork();
        if ($pid === - 1) {
            $this->log('Could not fork');
        }
        if ($pid == 0) {
            $this->pid = posix_getpid();
            foreach(Thread::$signals as $no => $name) {
                if (($name === 'SIGKILL') || ($name == 'SIGSTOP'))
                {
                    continue;
                }
                if (!pcntl_signal($no, array($this,'sighandler') , TRUE))
                {
                    $this->log('Cannot assign ' . $name . ' signal');
                }
            }
            $this->run();
            $this->shutdown();
        }
        $this->pid = $pid;
        return $pid;
    }
    /* @method sighandler
    @description Called when a signal caught.
    @param integer Signal's number.
    @return void
    */
    public function sighandler($signo)
    {
        $this->log('sighandler of process '.getmypid().' caught '.Thread::$signals[$signo],2);
        if( is_callable($c = array($this,strtolower(Thread::$signals[$signo]))) )
        {
            $this->log('sighandler '.getmypid().' calling function '.strtolower(Thread::$signals[$signo]).'()',2);
            call_user_func($c);
        }
        elseif( is_callable($c = array($this,'sigunknown')) )
        {
            $this->log('sighandler '.getmypid().' calling function sigunknown()',2);
            call_user_func($c, $signo);
        }
    }
    /* @method shutdown
    @description Shutdowns the current process properly.
    @return void
    */
    public function shutdown()
    {
        exit(0);
    }
    /* @method backsig
    @description Semds the signal to parent process.
    @param integer Signal's number.
    @return void
    */
    public function backsig($sig)
    {
        return posix_kill(posix_getppid() , $sig);
    }

    /* @method sigchld
    @description Called when the signal SIGCHLD caught.
    @return void
    */
    public function sigchld()
    {
        $this->waitPid();
    }

    /* @method sigterm
    @description Called when the signal SIGTERM caught.
    @return void
    */
    public function sigterm()
    {
        $this->shutdown();
    }
    /* @method sigint
    @description Called when the signal SIGINT caught.
    @return void
    */
    public function sigint()
    {
        $this->shutdown(TRUE);
    }
    /* @method sigquit
    @description Called when the signal SIGQUIT caught.
    @return void
    */
    public function sigquit()
    {
        $this->shutdown();
    }
    /* @method sigkill
    @description Called when the signal SIGKILL caught.
    @return void
    */
    public function sigkill()
    {
        //убиваем все дочерние процессы
        $this->shutdown(TRUE);
    }
    /* @method stop
    @description Terminates the process.
    @param boolean Kill?
    @return void
    */
    public function stop($kill = FALSE)
    {
        return posix_kill($this->pid, $kill ? SIGKILL : SIGTERM);
    }
    /* @method waitPid
    @description Checks for SIGCHLD.
    @return boolean Success.
    */
    public function waitPid()
    {
        return TRUE;
    }

    public function sigusr1()
    {
        return TRUE;
    }

    public function sigusr2()
    {
        return TRUE;
    }

    /* @method setproctitle
    @description Sets a title of the current process.
    @param string Title.
    @return void
    */
    public static function setproctitle($title)
    {
        if (function_exists('setproctitle')) {
            return setproctitle($title);
        }
        return FALSE;
    }


    /* @method sigwait
    @description Waits for signals, with a timeout.
    @param int Seconds.
    @param int Nanoseconds.
    @return void
    */
    public function sigwait($microsec = 1)
    {
        $siginfo = NULL;
        $signo = pcntl_sigtimedwait(Thread::$signalsno, $siginfo, 0, $microsec*1000);
        if (is_bool($signo)) {
            return $signo;
        }
        if ($signo > 0) {
            $this->sighandler($signo);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * запись в лог от имени процесса
     */
    public function log($_msg,$_verbose = 1)
    {
        if($_verbose <= Daemon::getSettings('logs_verbose'))
        {
            Daemon::logWithSender($_msg,$this->thread_name);
        }
    }
}


if (!function_exists('pcntl_sigtimedwait')) {
    function pcntl_sigtimedwait($signals, $siginfo, $sec, $nano)
    {
        pcntl_signal_dispatch();
        if (time_nanosleep($sec, $nano) === TRUE) {
            return FALSE;
        }
        pcntl_signal_dispatch();
        return TRUE;
    }

}
