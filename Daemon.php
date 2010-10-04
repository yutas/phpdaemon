<?php

include_once "Thread.php";
include_once "Thread_Master.php";
include_once "Thread_Child.php";
include_once "Thread_Collection.php";
include_once "Application_Base.php";
include_once "Application_Base_DB.php";

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
	public static $runmode = FALSE;						//{start|stop|restart}
	public static $daemon_name = FALSE;					//имя демона, которое определяет имя лог-файла: "<имя демона>.log"
	public static $settings = array(					//настройки демона
		'max_child_count' => 20,						//максимальное количество тредов
		'daemonize' => true,							//демонизировать или нет
		'logs_verbose' => 1,							//степерь подробности логирования
		'logs_to_strerr' => false,						//выводить сообщения в STDERR
		'sigwait_nano' => 1000000,						//задержка выполнения runtime для ожидания управляющих сигналов операционной системы (наносекунды)
		'sigwait_sec' => 0,								//задержка выполнения runtime для ожидания управляющих сигналов операционной системы (секунды)
		'pid_dir' => '/var/run',						//папка для хранения pid-файла
		'log_dir' => '/var/tmp',						//папка для хранения log-файла
	);
	
	protected static $args = array('daemon' => array(),'appl' => array());	//параметры, передаваемые демону в командной строке или в методе Daemon::init()
	protected static $master;							//класс главного процесса
	protected static $appl = FALSE;						//выполняемое приложение
	protected static $logpointer;						//указатель на файл логов


	/**
	 * инициализация демона и его входных параметров
	 */
	public static function init($_settings)
	{
		//объединяем параметры, переданные через командную строку и через $_settings
		//порядок переопределения параметров при совпадении ключей по приоритету:
		//	1. Атрибуты класса
		//	2. $_settings переопределяет атрибуты класса
		//	3. Командная строка переопределяет $_settings
		self::$args = self::get_args($_SERVER['argv']);
		self::$args['daemon'] = array_merge(is_array($_settings['daemon'])?$_settings['daemon']:array(),self::$args['daemon']);
		self::$args['appl'] = array_merge(is_array($_settings['appl'])?$_settings['appl']:array(),self::$args['appl']);

		//инициализируем входные параметры демона
		self::apply_args(self::$args['daemon']);

		//задаем имя демону
		self::set_name(basename($_SERVER['argv'][0]));

		//открываем лог файл
		self::open_logs();

		//создаем pid-файл или берем из него значение pid процесса, если он уже существует
		self::get_pid();
	}

	/**
	 * задает исполняемое приложение
	 * приложение должно наследовать Application_Base
	 */
	public static function set_application(Application_Base $_appl)
	{
		self::$appl = $_appl;
		
		//инициализируем параметры приложения
		self::$appl->apply_settings(self::$args['appl']);
	}

	/**
	 * инициализируем имя демона
	 */
	public static function set_name($_pname = null)
	{
		self::$daemon_name = strtolower($_pname ? $_pname : 'phpd');
	}

	/**
	 * запускаем, останавливаем или перезапускаем демон в зависимости от $runmode
	 */
	public static function run(Application_Base $_appl)
	{
		if(self::$runmode == 'start')
		{
			self::start($_appl);
		}
		elseif(self::$runmode == 'stop')
		{
			self::stop();
		}
		elseif(self::$runmode == 'restart')
		{
			self::stop();
			sleep(1);
			self::start($_appl);
		}
	}

	/**
	 * собсна, запускаем демон
	 */
	public static function start(Application_Base $_appl)
	{
		self::log('starting '.self::$daemon_name.'...',1,TRUE);
		//инициализируем исполняемое приложение
		self::set_application($_appl);
		if (self::$pid && posix_kill(self::$pid, SIGTTIN)) {
            self::log('[START] phpd with pid-file \'' . self::$pidfile . '\' is running already (PID ' . self::$pid . ')',1,TRUE);
            exit;
        }

		//создаем главный процесс
		self::$master = new Thread_Master();

		//передаем приложению ссылку на мастерский процесс
		self::$appl->set_master_thread(self::$master);

		//... а мастерскому процессу ссылку на приложение
		self::$master->set_application(self::$appl);

		//запускаем мастерский процесс
		self::$pid = self::$master->start();
		if(-1 === self::$pid)
		{
			self::log('could not start master');
            exit(0);
		}

		//записываем pid процесса в pid-файл
		file_put_contents(self::$pidfile, self::$pid);
	}


	/**
	 * останавливаем демон
	 */
	public static function stop($mode = 1)
	{
		self::log('Stoping '.self::$daemon_name.' (PID ' . self::$pid . ') ...',1,TRUE);
		$ok = self::$pid && posix_kill(self::$pid, $mode === 3 ? SIGINT : SIGTERM);
        if (!$ok) {
			self::log('Error: it seems that daemon is not running' . (self::$pid ? ' (PID ' . self::$pid . ')' : ''),1,TRUE);
        }
		self::$pid = 0;
		file_put_contents(self::$pidfile, '');
	}

	/**
	 * разбираемся с pid-файлом
	 */
	public static function get_pid()
	{
		self::$pidfile = self::$settings['pid_dir'].'/'.self::$daemon_name.'.pid';
		
		if (!file_exists(self::$pidfile))	//если pid-файла нет
		{	
            if (!touch(self::$pidfile))		//и его нельзя создать
			{
                self::log('Couldn\'t create or find pid-file \'' . self::$pidfile . '\'',1,TRUE);		//пишем ошибку в лог
				self::$pid = FALSE;
            }
			else
            {
				self::$pid = 0;					//если можно создать - все в порядке
			}
        }
		elseif (!is_file(self::$pidfile))	//если это не файл вообще, а папка, к примеру
		{
            self::log('Pid-file \'' . self::$pidfile . '\' must be a regular file',1,TRUE);	//пишем ошибку в лог
            self::$pid = FALSE;
        }
		elseif (!is_writable(self::$pidfile))	//если файл недоступен для записи
		{
            self::log('Pid-file \'' . self::$pidfile . '\' must be writable',1,TRUE);			//пишем ошибку в лог
			self::$pid = FALSE;
		}
		elseif (!is_readable(self::$pidfile))	//если файл недоступен для чтения
		{
            self::log('Pid-file \'' . self::$pidfile . '\' must be readable',1,TRUE);			//пишем ошибку в лог
            self::$pid = FALSE;
        }
		else
		{
            self::$pid = (int)file_get_contents(self::$pidfile);	//если файл есть, то берем оттуда pid работающего процесса
        }

		if(self::$pid === FALSE)		//прерываем выполнение, если возникала ошибка
		{
			self::log('Exits',1,TRUE);
			exit();
		}

	}

	/**
	 * открываем лог-файл
	 */
	public static function open_logs()
    {
		//имя файла логов
		self::$settings['logstorage'] = self::$settings['log_dir'].'/'.self::$daemon_name.'.log';
		if (self::$logpointer) {			//если он был ранее открыт, сперва его закроем
			fclose(self::$logpointer);
			self::$logpointer = FALSE;
		}
		self::$logpointer = fopen(self::$settings['logstorage'], 'a+');
    }

	/**
	 * добавляем запись в лог от имени демона
	 */
	public static function log($_msg,$_verbose = 1,$_to_stderr = FALSE)
	{
		if($_verbose <= self::$settings['logs_verbose'])		//если уровень подробности записи не выше ограничения в настройках
		{
			self::log_with_sender($_msg,'DAEMON',$_to_stderr);
		}
	}

	/**
	 * добавляем запись в лог от имени $_sender
	 */
	public static function log_with_sender($_msg,$_sender = 'nobody',$_to_stderr = FALSE)
    {
		$mt = explode(' ', microtime());
		if ( ($_to_stderr || self::$settings['logs_to_stderr']) && defined('STDERR'))	//если в настройках определен вывод в STDERR
		{
			//выводим логи еще и в управляющий терминал
			fwrite(STDERR, '['.strtoupper($_sender).'] ' . $_msg . "\n");
		}
		if (self::$logpointer)							//если файл логов был открыт без ошибок
		{
			fwrite(self::$logpointer, '[' . date('D, j M Y H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ' ' . date('O') . '] ['.strtoupper($_sender).'] ' . $_msg . "\n");
		}
    }




	/**
	 * парсит строку параметров, передаваемых демону.
	 * первым параметром должен быть runmode = {start|stop|restart}
	 * аргументы для приложения передаются таким образом: --argument=value
	 * аргументы для демона: -a value
	 */
	public static function get_args($args)
	{
		$out = array('daemon' => array(),'appl' => array());
		$last_arg = NULL;

		//инициализируем runmode
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



	/**
	 * инициализируем параметры демона
	 */
	public static function apply_args($_args)
	{
		//мерджим настройки из файла запуска
		self::$settings = array_merge(self::$settings,$_args);
		
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
		if(isset($_args['v']) && intval($_args['v']) )
		{
			self::$settings['logs_verbose'] = 2;
		}

		//don't daemonize
		if(isset($_args['a']) && $_args['a'] === TRUE)
		{
			self::$settings['daemonize'] = FALSE;
		}

		//outputs all logs to STDERR
		if(isset($_args['o']) && $_args['o'] === TRUE)
		{
			self::$settings['logs_to_stderr'] = TRUE;
		}
	}


	//выводит справку, если демону передали параметр -h
	public static function show_help()
	{
		echo "usage: " . self::$daemon_name . " {start|stop|restart} ...\n
\t-a  -  keep daemon alive (don't daemonize)
\t-v  -  verbose
\t-o  -  output logs to STDERR
\t-h  -  print this help information and exit
  \n";
	}


}
