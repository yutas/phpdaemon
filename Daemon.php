<?php

namespace Daemon;
use \Daemon\Utils\Helper as Helper;

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
	//runmodes
	const RUNMODE_HELP = 'help';
	const RUNMODE_START = 'start';
	const RUNMODE_STOP = 'stop';
	const RUNMODE_RESTART = 'restart';
	const RUNMODE_CHECK = 'check';
	const RUNMODE_API = 'api';

	//log levels
	const LL_TRACE = 4;
	const LL_DEBUG = 3;
	const LL_INFO = 2;
	const LL_ERROR = 1;
	const LL_MIN = 0;

    public static $pid;
    public static $pidfile;
    protected static $daemon_name = FALSE;              //имя демона, которое определяет имя лог-файла: "<имя демона>.log"
    protected static $config = array(					//настройки демона
        'alive' => false,								//запустить в терминале (не демонизировать)
        'verbose' => Daemon::LL_ERROR,             //степерь подробности логирования
        'logs_to_stderr' => false,                      //выводить сообщения в STDERR
        'sigwait' => 10,								//задержка выполнения runtime для ожидания управляющих сигналов операционной системы (миллисекунды)
        'pid_dir' => '/var/run',                        //папка для хранения pid-файла
        'log_dir' => '/var/tmp',                        //папка для хранения log-файла
    );
	protected static $config_aliases = array(
		'a' => array('alive', 'bool'),
		'v' => array('verbose', 'int'),
		'o' => array('logs_to_stderr', 'bool'),
		's' => array('sigwait', 'int'),
		'p' => array('pid_dir', 'string'),
		'l' => array('log_dir', 'string'),
	);
    protected static $help_message;

    protected static $runmode = FALSE;
    protected static $args = array('daemon' => array(),'appl' => array());  //параметры, передаваемые демону в командной строке или в методе Daemon::init()
    protected static $appl = FALSE;                     //выполняемое приложение
	protected static $args_string_pattern = "#^\b(?<runmode>start|stop|restart|check|api)\b\s*(?<args_string>.*)?$#";

    protected static $logpointer;                       //указатель на файл логов
    protected static $logs_to_stderr;                   //указатель на файл логов

	protected static $allowed_runmodes = array(
		Daemon::RUNMODE_HELP,
		Daemon::RUNMODE_START,
		Daemon::RUNMODE_STOP,
		Daemon::RUNMODE_RESTART,
		Daemon::RUNMODE_CHECK,
		Daemon::RUNMODE_API,
	);

    /**
     * инициализация демона и его входных параметров
     */
    protected static function init($_config, Application\Base $_appl = null)
    {
        //объединяем параметры, переданные через командную строку и через $_config
        //порядок переопределения параметров при совпадении ключей по приоритету:
        //  1. Атрибуты класса
        //  2. $_config переопределяет атрибуты класса
        //  3. Командная строка переопределяет $_config
        static::$args = static::getArgs( static::parseArgsString( implode(' ', array_slice($_SERVER['argv'],1) ) ) );
        static::$args['daemon'] = array_merge(isset($_config['daemon']) && is_array($_config['daemon'])?$_config['daemon']:array(),static::$args['daemon']);
        static::$args['appl'] = array_merge(isset($_config['appl']) && is_array($_config['appl'])?$_config['appl']:array(),static::$args['appl']);

        //инициализируем входные параметры демона
        static::applyArgs(static::$args['daemon']);

		static::generateHelpMessage();
		if(empty(static::$appl) && ! empty($_appl))
		{
			static::setApplication($_appl);
		}
    }


	public static function getConfig($param = null)
	{
		if( ! empty($param)) {
			if(isset(static::$config[$param])) {
				return static::$config[$param];
			} else {
				static::logError("Undefined config parameter \"".$param."\"");
			}
		}
		return static::$config;
	}


	public static function getName()
	{
		return static::$daemon_name;
	}

    /**
     * инициализируем имя демона
     */
    public static function setName()
	{
		if(isset(static::$args['daemon']['name']))
		{
			static::$daemon_name = static::$args['daemon']['name'];
		}
		elseif( ! empty(static::$appl))
		{
			static::$daemon_name = static::$appl->getName();
		}
		else
		{
			static::$daemon_name = basename($_SERVER['argv'][0]);
		}
		static::$daemon_name = strtolower(static::$daemon_name);
    }

    /**
     * запускаем, останавливаем или перезапускаем демон в зависимости от $runmode
     */
    public static function run(array $_config = array(), Application\Base $_appl = null)
    {
		static::init($_config, $_appl);
		//static::logMemory(__FILE__.":".__LINE__);
		//static::logCpu(__FILE__.":".__LINE__);

		if(static::$runmode == Daemon::RUNMODE_START) {
			static::start();
		} elseif(static::$runmode == static::RUNMODE_STOP) {
			$stop_mode = 1;
			if(isset(static::$config['f']) && static::$config['f'] == TRUE)
			{
				$stop_mode = 2;
			}
			static::stop($stop_mode);
		} elseif(static::$runmode == Daemon::RUNMODE_RESTART) {
			static::restart();
		} elseif(static::$runmode == Daemon::RUNMODE_CHECK) {
			if(static::check()) {
				exit(0);
			} else {
				exit(1);
			}
		} elseif(static::$runmode == Daemon::RUNMODE_HELP) {
			static::showHelp();
			exit;
		} elseif(static::$runmode == Daemon::RUNMODE_API) {
			if( ! static::$appl->hasApiSupport())
			{
				Daemon::logError("Application does not support Api", true);
			}
			//выделим нужные параметры для передачи в апи (action + params)
			$api_params = array();
			if( ! empty(static::$args['appl']['action']))
			{
				$api_params['action'] = static::$args['appl']['action'];
				unset(static::$args['appl']['action']);
			}
			if( ! empty(static::$args['appl']['params']))
			{
				$api_params['params'] = json_decode(static::$args['appl']['params'], true);
				unset(static::$args['appl']['params']);
			}
			static::$appl->applyArgs(static::$args['appl']);
			echo static::$appl->sendToApi($api_params).PHP_EOL;
			exit;
		}
    }

    /**
     * собсна, запускаем демон
     */
    public static function start()
    {
		//задаем имя демону
		static::setName();

		//открываем лог файл
		static::openLogs();

		//создаем pid-файл или берем из него значение pid процесса, если он уже существует
		static::getPid();

		if(empty(static::$appl))
		{
			static::logError("Can't start daemon without application");
		}

		static::log('starting '.static::getName().'...', TRUE);

        if (static::check()) {
            static::logError('[START] phpd with pid-file \'' . static::$pidfile . '\' is running already (PID ' . static::$pid . ')', TRUE);
            exit;
        }

        //инициализируем параметры приложения
        static::$appl->applyArgs(static::$args['appl']);

        //передаем приложению ссылку на мастерский процесс
		$master = new Thread\Master();
        static::$appl->setMasterThread($master);

        //... а мастерскому процессу ссылку на приложение
        $master->setApplication(static::$appl);

        //запускаем мастерский процесс
        static::$pid = $master->start();
        if(-1 === static::$pid)
        {
            static::logError('Could not start master', TRUE);
            exit(1);
        }
    }


	public static function restart()
	{
		static::stop();
		sleep(1);
		static::start();
	}


	public static function check()
	{
		return static::$pid && posix_kill(static::$pid, 0);
	}


    /**
     * останавливаем демон
     */
    public static function stop($mode = 1)
    {
		//задаем имя демону
		static::setName();

		//открываем лог файл
		static::openLogs();

		//создаем pid-файл или берем из него значение pid процесса, если он уже существует
		static::getPid();

        static::log('Stoping '.static::getName().' (PID ' . static::$pid . ') ...', Daemon::LL_MIN, TRUE);
        $ok = static::$pid && posix_kill(static::$pid, $mode === 2 ? SIGINT : SIGTERM);
        if (!$ok) {
            static::logError('it seems that daemon is not running' . (static::$pid ? ' (PID ' . static::$pid . ')' : ''), TRUE);
			file_put_contents(static::$pidfile, '');
        }
        static::$pid = 0;
    }

    /**
     * разбираемся с pid-файлом
     */
    public static function getPid()
    {
        static::$pidfile = rtrim(static::$config['pid_dir'],'/').'/'.static::getName().'.pid';

        if (!file_exists(static::$pidfile))   //если pid-файла нет
        {
            if (!touch(static::$pidfile))     //и его нельзя создать
            {
                static::logError('Couldn\'t create or find pid-file \'' . static::$pidfile . '\'', TRUE);       //пишем ошибку в лог
                static::$pid = FALSE;
            }
            else
            {
                static::$pid = 0;                 //если можно создать - все в порядке
            }
        }
        elseif (!is_file(static::$pidfile))   //если это не файл вообще, а папка, к примеру
        {
            static::logError('Pid-file \'' . static::$pidfile . '\' must be a regular file', TRUE); //пишем ошибку в лог
            static::$pid = FALSE;
        }
        elseif (!is_writable(static::$pidfile))   //если файл недоступен для записи
        {
            static::logError('Pid-file \'' . static::$pidfile . '\' must be writable', TRUE);           //пишем ошибку в лог
            static::$pid = FALSE;
        }
        elseif (!is_readable(static::$pidfile))   //если файл недоступен для чтения
        {
            static::logError('Pid-file \'' . static::$pidfile . '\' must be readable', TRUE);           //пишем ошибку в лог
            static::$pid = FALSE;
        }
        else
        {
            static::$pid = (int)file_get_contents(static::$pidfile);    //если файл есть, то берем оттуда pid работающего процесса
        }

        if(static::$pid === FALSE)        //прерываем выполнение, если возникала ошибка
        {
            static::log('Exits', Daemon::LL_MIN, TRUE);
            exit();
        }

    }

    /**
     * открываем лог-файл
     */
    public static function openLogs()
    {
        //имя файла логов
        static::$config['logstorage'] = static::$config['log_dir'].'/'.static::getName().'.log';
        if (static::$logpointer) {            //если он был ранее открыт, сперва его закроем
            fclose(static::$logpointer);
            static::$logpointer = FALSE;
        }
		static::$logpointer = fopen(static::$config['logstorage'], 'a+');
    }

    /**
     * добавляем запись в лог от имени демона
     */
    public static function log($_msg,$_verbose = Daemon::LL_MIN, $_to_stderr = FALSE)
    {
        if($_verbose <= static::$config['verbose'])        //если уровень подробности записи не выше ограничения в настройках
        {
            static::logWithSender($_msg,'DAEMON',$_to_stderr);
        }
    }

	public static function logError($_msg, $_to_stderr = FALSE)
	{
		$_msg = '[ERROR] '.$_msg;
		static::log($_msg, Daemon::LL_ERROR, $_to_stderr);
	}

    /**
     * добавляем запись в лог от имени $_sender
     */
    public static function logWithSender($_msg,$_sender = 'nobody',$_to_stderr = FALSE)
    {
        $mt = explode(' ', microtime());
        if ( ($_to_stderr || static::$logs_to_stderr) && defined('STDERR'))   //если в настройках определен вывод в STDERR
        {
            //выводим логи еще и в управляющий терминал
            fwrite(STDERR, '['.strtoupper($_sender).'] ' . $_msg . "\n");
        }
        if (static::$logpointer)                          //если файл логов был открыт без ошибок
        {
            fwrite(static::$logpointer, '[' . date('D, j M Y H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ' ' . date('O') . '] ['.strtoupper($_sender).'] ' . $_msg . "\n");
        }
    }




    /**
     * парсит строку параметров, передаваемых демону.
     * первым параметром должен быть runmode = {start|stop|restart}
     * аргументы для приложения передаются таким образом: --argument=value
     * аргументы для демона: -a value
     */
    public static function getArgs($args)
    {
        $out = array('daemon' => array(),'appl' => array());
        $last_arg = NULL;

		foreach($args as $arg) {
            if (preg_match('~^--(.+)~', $arg, $match)) {
				//обрабатывает параметры вида "--param=1000"
                $parts = explode('=', $match[1]);
                $key = preg_replace('~[^a-z0-9_]+~', '', $parts[0]);
                if (isset($parts[1])) {
                    $out['appl'][$key] = $parts[1];
                } else {
                    $out['appl'][$key] = TRUE;
                }
                $last_arg = $key;
            } elseif (preg_match('~^-([a-zA-Z0-9_]+)~', $arg, $match)) {
				//обрабатывает параметры вида "-vvd" (только true/false)
                for ($j = 0, $jl = strlen($match[1]); $j < $jl; ++$j) {
                    $key = $match[1] {
                        $j
                    };
					if(empty($out['daemon'][$key])) {
						$out['daemon'][$key] = true;
					} else {
						$out['daemon'][$key] = intval($out['daemon'][$key]);
						$out['daemon'][$key]++;
					}
                }
                $last_arg = $key;
			} elseif ($last_arg !== NULL) {
				//обрабатывает параметры вида "-s 1000"
                $out['daemon'][$last_arg] = $arg;
				$last_arg = NULL;
            }
        }
        return $out;
    }

	protected static function parseArgsString($args_string = '')
	{
		$matches = array();
		$args = array();
        //инициализируем runmode
		if(preg_match(static::$args_string_pattern,$args_string,$matches)) {
			static::setRunmode($matches['runmode']);
			$args = explode(' ',$matches['args_string']);
		} else {
			static::$runmode = Daemon::RUNMODE_HELP;
		}

		return $args;
	}



    /**
     * инициализируем параметры демона
     */
    public static function applyArgs($_args)
    {
        //мерджим настройки из файла запуска
        static::$config = array_merge(static::$config,$_args);

        //show help
        if(isset($_args['h']) && $_args['h'] === TRUE)
        {
            static::$runmode = Daemon::RUNMODE_HELP;
        }

		foreach(static::$config_aliases as $alias => $alias_config)
		{
			list($config_name,$type) = $alias_config;
			if( ! empty($_args[$alias]) && settype($_args[$alias], $type))
			{
				static::$config[$config_name] = $_args[$alias];
				if($alias == 'v') static::$config[$config_name]++;
				unset(static::$config[$alias]);
			}
		}

		static::$logs_to_stderr = static::$config['logs_to_stderr'];
    }


    //выводит справку
	public static function showHelp()
	{
		if(empty(static::$appl)) {
			printf(static::$help_message,basename($_SERVER['argv'][0]));
		} else {
			printf(static::$help_message,basename($_SERVER['argv'][0]));
			echo "\n".static::$appl->getHelp();
		}
		echo PHP_EOL;
	}

	public static function setHelpMessage($str)
	{
		static::$help_message = $str;
	}

	public static function generateHelpMessage()
	{
		static::$help_message .= "usage: ./%s   {start|stop|restart|check|api}   <config>".PHP_EOL.PHP_EOL.
								"\tDaemon config:".PHP_EOL.
								"\t-a  -  keep daemon alive (don't daemonize)".PHP_EOL.
								"\t-v  -  verbose daemon logs".PHP_EOL.
								"\t-o  -  output logs to STDERR".PHP_EOL.
								"\t-s  -  sigwait time (in microseconds)".PHP_EOL.
								"\t-p  -  directory for pid file".PHP_EOL.
								"\t-l  -  directory for log file".PHP_EOL.
								"\t-h  -  print this help information and exit".PHP_EOL;
	}

	public static function setApplication(Application\Base $_appl)
	{
		static::$appl = $_appl;
	}

	protected static function setRunmode($runmode)
	{
		if( ! empty($runmode) && in_array($runmode, static::$allowed_runmodes))
		{
			static::$runmode = $runmode;
		}
		else
		{
			static::$runmode = Daemon::RUNMODE_HELP;
		}
	}

	public static function logMemory($prefix = '')
	{
		static::log('[MEMORY] '.$prefix." Memory usage: ".round(memory_get_usage()/1024)."K", Daemon::LL_DEBUG);
	}

	public static function logCpu($prefix = '')
	{
		static::log('[CPU] '.$prefix." CPU usage: ".Helper::getCpuUsage()."%", Daemon::LL_DEBUG);
	}
}
