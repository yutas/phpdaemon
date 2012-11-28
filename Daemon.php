<?php

namespace Daemon;
use \Daemon\Utils\Helper;
use \Daemon\Utils\Logger;
use \Daemon\Utils\LogTrait;
use \Daemon\Utils\Config;

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
	use LogTrait;

	const DEFAULT_CONFIG_FILE = 'config.yml';
	//runmodes
	const RUNMODE_HELP = 'help';
	const RUNMODE_START = 'start';
	const RUNMODE_STOP = 'stop';
	const RUNMODE_RESTART = 'restart';
	const RUNMODE_CHECK = 'check';

    public static $pid;
    public static $pidfile;
    protected static $daemon_name = FALSE;              //имя демона, которое определяет имя лог-файла: "<имя демона>.log"
    protected static $help_message;

    protected static $runmode = FALSE;
    protected static $args = array('Daemon' => array(),'Application' => array());  //параметры, передаваемые демону в командной строке или в методе Daemon::init()
    protected static $appl = FALSE;                     //выполняемое приложение
	protected static $args_string_pattern = "#^\b(?<runmode>start|stop|restart|check)\b\s*(?<args_string>.*)?$#";

	protected static $allowed_runmodes = array(
		Daemon::RUNMODE_HELP,
		Daemon::RUNMODE_START,
		Daemon::RUNMODE_STOP,
		Daemon::RUNMODE_RESTART,
		Daemon::RUNMODE_CHECK,
	);

    /**
     * инициализация демона и его входных параметров
     */
    protected static function init(Application\IApplication $appl = null)
    {
		try {
			//разберем аргументы, переданные через командную строку
			static::$args = static::parseArgsString(implode(' ', array_slice($_SERVER['argv'],1)));
			//загрузим конфиг из файла
			Config::load(empty(static::$args['c']) ? getcwd().'/'.self::DEFAULT_CONFIG_FILE : static::$args['c']);

			//объединяем параметры, переданные через командную строку и из файла конфигурации
			Config::mergeArgs(static::$args);

			//show help
			if(Config::get('Flags.help'))
			{
				static::$runmode = static::RUNMODE_HELP;
			}

			//открываем лог файл
			Logger::init();

			//создаем pid-файл или берем из него значение pid процесса, если он уже существует
			if(static::getPid())
			{
				return 1;
			}

			if(empty(static::$appl) && ! empty($appl))
			{
				static::setApplication($appl);
			}
		} catch(\Exception $e) {
			echo $e->getMessage().PHP_EOL;

			return 1;
		}

		return 0;
    }

	public static function getName()
	{
		return Config::get('Daemon.name', 'Daemon');
	}

    /**
     * запускаем, останавливаем или перезапускаем демон в зависимости от $runmode
     */
    public static function run(Application\IApplication $appl = null)
    {
		if( ! ($result = static::init($appl)))
		{
			switch (static::$runmode)
			{
				case Daemon::RUNMODE_HELP:
					$result = static::showHelp();
					break;
				case Daemon::RUNMODE_START:
					$result = static::start();
					break;
				case Daemon::RUNMODE_STOP:
					$result = static::stop();
					break;
				case Daemon::RUNMODE_RESTART:
					$result = static::restart();
					break;
				case Daemon::RUNMODE_CHECK:
					$result = static::check();
					break;
			}
		}

		exit($result);
    }

    /**
     * собсна, запускаем демон
     */
    public static function start()
    {

		if(empty(static::$appl))
		{
			static::logError("Can't start daemon without application");
		}

		static::log('starting '.static::getName().'...', Logger::L_QUIET, TRUE);

        if ( ! static::check()) {
            static::logError('[START] phpd with pid-file \'' . static::$pidfile . '\' is running already (PID ' . static::$pid . ')', TRUE);
            return 1;
        }

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
			return 1;
        }
		return 0;
    }


	public static function restart()
	{
		static::stop();
		sleep(1);
		return static::start();
	}


	public static function check()
	{
		return intval( ! (static::$pid && posix_kill(static::$pid, 0)));
	}


    /**
     * останавливаем демон
     */
    public static function stop()
    {
		$mode = Config::get('Flags.force', false);

        static::log('Stoping '.static::getName().' (PID ' . static::$pid . ') ...', Logger::L_QUIET, TRUE);
        $ok = static::$pid && posix_kill(static::$pid, $mode ? SIGINT : SIGTERM);
        if (!$ok) {
            static::logError('it seems that daemon is not running' . (static::$pid ? ' (PID ' . static::$pid . ')' : ''), TRUE);
			file_put_contents(static::$pidfile, '');
			return 1;
        }
        static::$pid = 0;
		return 0;
    }

    /**
     * разбираемся с pid-файлом
     */
    public static function getPid()
    {
        static::$pidfile = Config::get('Daemon.pid_file');

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

        if(FALSE === static::$pid)        //прерываем выполнение, если возникала ошибка
        {
            static::log('Failed to get pid', Logger::L_QUIET, TRUE);
			return 1;
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
        $out = array();
        //инициализируем runmode
		if(preg_match(static::$args_string_pattern,$args_string,$matches)) {
			static::setRunmode($matches['runmode']);
			$args = explode(' ',$matches['args_string']);
		} else {
			static::setRunmode(self::RUNMODE_HELP);
		}

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
		$help_message .= "Usage: ./%s   {%s}   <args>".PHP_EOL.PHP_EOL;
		$help_message .= Config::getHelp();
		printf($help_message, basename($_SERVER['argv'][0], implode('|', static::$allowed_runmodes)));
		echo PHP_EOL;
		return 0;
	}

	public static function setApplication(Application\IApplication $appl)
	{
		static::$appl = $appl;
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
