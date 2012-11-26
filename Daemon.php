<?php

namespace Daemon;
use \Daemon\Utils\Helper;
use \Daemon\Utils\Logger;
use \Daemon\Utils\Config;

/**
 * Демон состоит из двух частей:
 * 1. Сам класс демона, который обеспечивает висящие в памяти процессы и логирование всего с этим связанного
 * 2. Класс приложения, который будет наследоваться от абстрактного класса и реализовывать уже конкретные задачи
 */

/**
 * Класс отвечает за все операции с демоном
 */
//TODO: нужна функция дебага, которая будет выводить файл и строку, где была вызвана
//TODO: возможно логгер нужно реализовать отдельным классом
class Daemon
{
	const DEFAULT_CONFIG_FILE = 'config.yml';
	//runmodes
	const RUNMODE_START = 'start';
	const RUNMODE_STOP = 'stop';
	const RUNMODE_RESTART = 'restart';
	const RUNMODE_CHECK = 'check';
	const RUNMODE_API = 'api';

    public static $pid;
    public static $pidfile;
    protected static $daemon_name = FALSE;              //имя демона, которое определяет имя лог-файла: "<имя демона>.log"
    protected static $help_message;

    protected static $runmode = FALSE;
    protected static $args = array('Daemon' => array(),'Application' => array());  //параметры, передаваемые демону в командной строке или в методе Daemon::init()
    protected static $appl = FALSE;                     //выполняемое приложение
	protected static $args_string_pattern = "#^\b(?<runmode>start|stop|restart|check|api)\b\s*(?<args_string>.*)?$#";

	//TODO: а точно ли это нужно?
	protected static $allowed_runmodes = array(
		Daemon::RUNMODE_START,
		Daemon::RUNMODE_STOP,
		Daemon::RUNMODE_RESTART,
		Daemon::RUNMODE_CHECK,
		Daemon::RUNMODE_API,
	);

    /**
     * инициализация демона и его входных параметров
     */
    protected static function init(Application\IApplication $_appl = null)
    {
		try {
			//разберем аргументы, переданные через командную строку
			static::$args = static::parseArgsString(implode(' ', array_slice($_SERVER['argv'],1)));
			//загрузим конфиг из файла
			Config::load(empty(static::$args['c']) ? getcwd().'/'.self::DEFAULT_CONFIG_FILE : static::$args['c']);
			//show help
			if(isset(static::$args['h']) && static::$args['h'] === TRUE)
			{
				static::showHelp();
				exit;
			}

			//объединяем параметры, переданные через командную строку и из файла конфигурации
			Config::mergeArgs(static::$args);

			if(empty(static::$appl) && ! empty($_appl))
			{
				static::setApplication($_appl);
			}
		} catch(\Exception $e) {
			echo $e->getMessage().PHP_EOL;
			exit(1);
		}
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
    public static function run(Application\IApplication $_appl = null)
    {
		static::init($_appl);

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
		}elseif(static::$runmode == Daemon::RUNMODE_API) {
			//TODO: убрать этот RUNMODE из класса демона
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
			exit(0);
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
		Logger::init();

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

        static::log('Stoping '.static::getName().' (PID ' . static::$pid . ') ...', Logger::L_MIN, TRUE);
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
        static::$pidfile = rtrim(Config::get('Daemon.pid_dir'),'/').'/'.static::getName().'.pid';

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
            static::log('Exits', Logger::L_MIN, TRUE);
            exit();
        }

    }

    /**
     * парсит строку параметров, передаваемых демону.
     * первым параметром должен быть runmode = {start|stop|restart}
     */
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

        $out = array();
        $last_arg = NULL;

		foreach($args as $arg) {
            if (preg_match('~^--(.+)~', $arg, $match)) {
				//обрабатывает параметры вида "--param=1000"
                $parts = explode('=', $match[1]);
                $key = preg_replace('~[^a-z0-9_]+~', '', $parts[0]);
                if (isset($parts[1])) {
                    $out[$key] = $parts[1];
                } else {
                    $out[$key] = TRUE;
                }
                $last_arg = $key;
            } elseif (preg_match('~^-([a-zA-Z0-9_]+)~', $arg, $match)) {
				//обрабатывает параметры вида "-vvd" (только true/false)
                for ($j = 0, $jl = strlen($match[1]); $j < $jl; ++$j) {
                    $key = $match[1] {
                        $j
                    };
					if(empty($out[$key])) {
						$out[$key] = true;
					} else {
						$out[$key] = intval($out[$key]);
						$out[$key]++;
					}
                }
                $last_arg = $key;
			} elseif ($last_arg !== NULL) {
				//обрабатывает параметры вида "-s 1000"
                $out[$last_arg] = $arg;
				$last_arg = NULL;
            }
        }
        return $out;
	}

    //выводит справку
	public static function showHelp()
	{
		$help_message .= "Usage: ./%s   {start|stop|restart|check|api}   <args>".PHP_EOL.PHP_EOL;
		$help_message .= Config::getHelp();
		printf($help_message,basename($_SERVER['argv'][0]));
		echo PHP_EOL;
	}

	public static function setApplication(Application\IApplication $_appl)
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
}
