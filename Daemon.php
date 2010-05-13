<?php

include_once "Thread.php";
include_once "Master_Thread.php";
include_once "Child_Thread.php";
include_once "Thread_Collection.php";
include_once "Daemon_Application.php";


/**
 * Демон состоит из двух частей:
 * 1. Сам класс демона, который обеспечивает висящие в памяти процессы и логирование всего с этим связанного
 * 2. Класс приложения, который будет наследоваться от абстрактного класса и реализовывать уже конкретные задачи
 */

/**
 * Класс отвечает за все операции с демоном
 */
class Daemon
{
	
	public static $pid;
	public static $pidfile;
	public static $runmode = FALSE;
	public static $daemon_name = FALSE;				//имя демона
	public static $daemonize = TRUE;

	protected static $master;							//класс главного процесса
	protected static $settings = array();				//настройки демона
	protected static $appl = FALSE;						//выполняемое приложение
	protected static $logpointer;						//указатель на файл логов
	protected static $args = array();
	protected static $logs_verbose = 1;
	protected static $logs_to_stderr = FALSE;

	
	public static function init()
	{
		self::$args = Daemon::get_args($_SERVER['argv']);

		if(self::$daemon_name === FALSE)
		{
			self::set_name(basename($_SERVER['argv'][0]));
		}

		self::apply_args(self::$args['daemon']);

		self::get_pid();

		self::open_logs();
	}

	//задает исполняемое приложение
	public static function set_application(Daemon_Application $appl)
	{
		self::$appl = $appl;
	}


	public static function set_name($_pname = null)
	{
		self::$daemon_name = $_pname ? $_pname : 'phpd';
	}


	public static function run()
	{
		if(self::$runmode == 'start')
		{
			self::start();
		}
		elseif(self::$runmode == 'stop')
		{
			self::stop();
		}
		elseif(self::$runmode == 'restart')
		{
			self::stop();
			self::start();
		}
	}
	
	public static function start()
	{

		if (Daemon::$pid && posix_kill(Daemon::$pid, SIGTTIN)) {
            Daemon::log('[START] phpDaemon with pid-file \'' . Daemon::$pidfile . '\' is running already (PID ' . Daemon::$pid . ')');
            exit;
        }

		//создаем главный процесс
		self::$master = new Master_Thread();

		//передаем мастерскому процессу ссылку на приложение
		self::$master->set_application(self::$appl);
		//а приложению ссылку на мастерский процесс
		self::$appl->set_master_thread(self::$master);

		self::$pid = self::$master->start();
		if(-1 === self::$pid)
		{
			Daemon::log('could not start master');
            exit(0);
		}
		file_put_contents(self::$pidfile, self::$pid);
	}


	public static function stop($mode = 1)
	{
		Daemon::log('[STOP] Stoping daemon (PID ' . Daemon::$pid . ') ...');
		$ok = Daemon::$pid && posix_kill(Daemon::$pid, $mode === 3 ? SIGINT : SIGTERM);
        if (!$ok) {
            echo '[STOP] ERROR. It seems that phpDaemon is not running' . (Daemon::$pid ? ' (PID ' . Daemon::$pid . ')' : '') . ".\n";
        }
        
		Daemon::$pid = 0;
		file_put_contents(self::$pidfile, '');
	}

	
	public static function get_pid()
	{
		self::$pidfile = '/var/tmp/'.self::$daemon_name.'.pid';
		
		if (!file_exists(Daemon::$pidfile))	//если pid-файла нет
		{	
            if (!touch(Daemon::$pidfile))
			{
                Daemon::log('Couldn\'t create pid-file \'' . Daemon::$pidfile . '\'.');
            }
            Daemon::$pid = 0;
        }
		elseif (!is_file(Daemon::$pidfile))
		{
            Daemon::log('Pid-file \'' . Daemon::$pidfile . '\' must be a regular file.');
            Daemon::$pid = FALSE;
        }
		elseif (!is_writable(Daemon::$pidfile))
		{
            Daemon::log('Pid-file \'' . Daemon::$pidfile . '\' must be writable.');
		}
		elseif (!is_readable(Daemon::$pidfile))
		{
            Daemon::log('Pid-file \'' . Daemon::$pidfile . '\' must be readable.');
            Daemon::$pid = FALSE;
        }
		else
		{
            Daemon::$pid = (int)file_get_contents(Daemon::$pidfile);
        }

		if(Daemon::$pid === FALSE)
		{
			Daemon::log('Program exits');
			exit();
		}

	}


	public static function open_logs()
    {
		self::$settings['logstorage'] = '/var/tmp/'.self::$daemon_name.'.log';
		if (Daemon::$logpointer) {
			fclose(Daemon::$logpointer);
			Daemon::$logpointer = FALSE;
		}
		Daemon::$logpointer = fopen(Daemon::$settings['logstorage'], 'a+');
    }


	public static function log($msg,$_verbose = 1)
    {
		if($_verbose > self::$logs_verbose)
		{
			return;
		}
		
        $mt = explode(' ', microtime());
        if (self::$logs_to_stderr && defined('STDERR')) {
            fwrite(STDERR, '[PHPD] ' . $msg . "\n");
        }
        if (Daemon::$logpointer) {
            fwrite(Daemon::$logpointer, '[' . date('D, j M Y H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ' ' . date('O') . '] [PHPD] ' . $msg . "\n");
        }
    }




	//парсит строку параметров, передаваемых демону.
	//первым параметром должен быть runmode = {start|stop|restart}
	//аргументы для приложения передаются таким образом: --argument=value
	//аргументы для демона: -a value
	public static function get_args($args)
	{
		$out = array('daemon' => array(),'appl' => array());
		$last_arg = NULL;

		self::$runmode = isset($args[1]) ? str_replace('-', '', $args[1]) : FALSE;
		if(in_array(self::$runmode,array('start','stop','restart')))
		{
			unset($args[1]);
			$i = 2;
			$il = sizeof($args)+1;
		}
		else
		{
			self::$runmode = FALSE;
			$i = 1;
			$il = sizeof($args);
		}
		
		for (; $i < $il; ++$i) {
			if (preg_match('~^--(.+)~', $args[$i], $match)) {
				$parts = explode('=', $match[1]);
                $key = preg_replace('~[^a-z0-9]+~', '', $parts[0]);
                if (isset($parts[1])) {
                    $out['appl'][$key] = $parts[1];
                } else {
                    $out['appl'][$key] = TRUE;
                }
                $last_arg = $key;
            } elseif (preg_match('~^-([a-zA-Z0-9]+)~', $args[$i], $match)) {
                for ($j = 0, $jl = strlen($match[1]); $j < $jl; ++$j) {
                    $key = $match[1] {
                        $j
                    };
                    $out['daemon'][$key] = true;
                }
                $last_arg = $key;
            } elseif ($last_arg !== NULL) {
                $out['daemon'][$last_arg] = $args[$i];
            }
        }
        return $out;
	}




	public static function apply_args($_args)
	{

		//если непонятно, что делать, показываем хелп и выходим
		if(self::$runmode === FALSE)
		{
			self::show_help();
			exit;
		}

		//show help
		if(isset($_args['h']) && $_args['h'] === TRUE)
		{
			self::show_help();
			exit;
		}

		//verbose
		if(isset($_args['v']) && $_args['v'] === TRUE)
		{
			self::$logs_verbose = 2;
		}

		//don't daemonize
		if(isset($_args['a']) && $_args['a'] === TRUE)
		{
			self::$daemonize = FALSE;
		}

		//outputs all logs to STDERR
		if(isset($_args['o']) && $_args['o'] === TRUE)
		{
			self::$logs_to_stderr = TRUE;
		}

	}


	//выводит справку, если демону передали параметр -h
	public static function show_help()
	{
		echo "phpdaemon
usage: " . Daemon::$daemon_name . " (start|stop|restart) ...\n
\t-a  -  keep daemon alive (don't daemonize)
\t-v  -  verbose
\t-o  -  output logs to STDERR
\t-h  -  print this help information and exit
  \n";
	}


}
