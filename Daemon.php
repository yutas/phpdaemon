<?php
namespace Daemon;

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
    protected static $daemon_name = FALSE;              //имя демона, которое определяет имя лог-файла: "<имя демона>.log"
    protected static $settings = array(					//настройки демона
        'alive' => true,								//демонизировать или нет
        'logs_verbose' => 1,                            //степерь подробности логирования
        'logs_to_stderr' => false,                      //выводить сообщения в STDERR
        'sigwait' => 1000000,							//задержка выполнения runtime для ожидания управляющих сигналов операционной системы (микросекунды)
        'pid_dir' => '/var/run',                        //папка для хранения pid-файла
        'log_dir' => '/var/tmp',                        //папка для хранения log-файла
    );
	protected static $settings_aliases = array(
		'a' => array('alive', 'bool'),
		'v' => array('logs_verbose', 'int'),
		'o' => array('logs_to_stderr', 'bool'),
		's' => array('sigwait', 'int'),
		'p' => array('pid_dir', 'string'),
		'l' => array('log_dir', 'string'),
	);
    protected static $help_message;

    protected static $runmode = FALSE;
	protected static $appl_name = '';
    protected static $args = array('daemon' => array(),'appl' => array());  //параметры, передаваемые демону в командной строке или в методе Daemon::init()
    protected static $appl = FALSE;                     //выполняемое приложение
	protected static $args_string_pattern = "#^\b(?<runmode>start|stop|restart|check)\b\s*(?<args_string>.*)?$#";

    protected static $logpointer;                       //указатель на файл логов
    protected static $logs_to_stderr;                   //указатель на файл логов


    /**
     * инициализация демона и его входных параметров
     */
    protected static function init($_settings, Application\Base $_appl)
    {
        //объединяем параметры, переданные через командную строку и через $_settings
        //порядок переопределения параметров при совпадении ключей по приоритету:
        //  1. Атрибуты класса
        //  2. $_settings переопределяет атрибуты класса
        //  3. Командная строка переопределяет $_settings
        static::$args = static::getArgs( static::parseArgsString( implode(' ', array_slice($_SERVER['argv'],1) ) ) );
        static::$args['daemon'] = array_merge(isset($_settings['daemon']) && is_array($_settings['daemon'])?$_settings['daemon']:array(),static::$args['daemon']);
        static::$args['appl'] = array_merge(isset($_settings['appl']) && is_array($_settings['appl'])?$_settings['appl']:array(),static::$args['appl']);

		static::generateHelpMessage($_appl);

        //инициализируем входные параметры демона
        static::applyArgs(static::$args['daemon']);

        //задаем имя демону
        static::setName(isset(static::$args['daemon']['name']) ? static::$args['daemon']['name'] : basename($_SERVER['argv'][0]));

        //открываем лог файл
        static::openLogs();

        //создаем pid-файл или берем из него значение pid процесса, если он уже существует
        static::getPid();
    }


	public static function getSettings($param = null)
	{
		if( ! empty($param)) {
			if(isset(static::$settings[$param])) {
				return static::$settings[$param];
			} else {
				throw new Exception_Daemon("Undefined settings parameter \"".$param."\"");
			}
		}
		return static::$settings;
	}


	public static function getName()
	{
		return static::$daemon_name;
	}

    /**
     * инициализируем имя демона
     */
    public static function setName($_pname = null)
    {
        static::$daemon_name = strtolower($_pname ? $_pname : 'phpd');
    }

    /**
     * запускаем, останавливаем или перезапускаем демон в зависимости от $runmode
     */
    public static function run(array $_settings = array(), Master\Master $_master = null, Application\Base $_appl = null)
    {
		try {
			static::init($_settings,$_appl);

			if(static::$runmode == 'start') {
				static::start($_master, $_appl);
			} elseif(static::$runmode == 'stop') {
				$stop_mode = 1;
				if(isset(static::$settings['f']) && static::$settings['f'] == TRUE)
				{
					$stop_mode = 2;
				}
				static::stop($stop_mode);
			} elseif(static::$runmode == 'restart') {
				static::restart($_appl);
			} elseif(static::$runmode == 'check') {
				if(static::check()) {
					exit(0);
				} else {
					exit(1);
				}
			}
		} catch(Exception $e) {
			static::log("Caught exception of class ".get_class($e).": ".$e->getMessage(),1,true);
		}
    }

    /**
     * собсна, запускаем демон
     */
    public static function start(Master\Master $_master = null, Application\Base $_appl)
    {
        static::log('starting '.static::getName().'...',1,TRUE);

        if (static::check()) {
            static::log('[START] phpd with pid-file \'' . static::$pidfile . '\' is running already (PID ' . static::$pid . ')',1,TRUE);
            exit;
        }

        //инициализируем параметры приложения
        $_appl->applySettings(static::$args['appl']);

        //передаем приложению ссылку на мастерский процесс
        $_appl->setMasterThread($_master);

        //... а мастерскому процессу ссылку на приложение
        $_master->setApplication($_appl);

        //запускаем мастерский процесс
        static::$pid = $_master->start();
        if(-1 === static::$pid)
        {
            static::log('could not start master');
            exit(1);
        }
    }


	public static function restart(Application\Base $_appl)
	{
		static::stop();
		sleep(1);
		static::start($_appl);
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
        static::log('Stoping '.static::getName().' (PID ' . static::$pid . ') ...',1,TRUE);
        $ok = static::$pid && posix_kill(static::$pid, $mode === 2 ? SIGINT : SIGTERM);
        if (!$ok) {
            static::log('Error: it seems that daemon is not running' . (static::$pid ? ' (PID ' . static::$pid . ')' : ''),1,TRUE);
			file_put_contents(static::$pidfile, '');
        }
        static::$pid = 0;
    }

    /**
     * разбираемся с pid-файлом
     */
    public static function getPid()
    {
        static::$pidfile = rtrim(static::$settings['pid_dir'],'/').'/'.static::getName().'.pid';

        if (!file_exists(static::$pidfile))   //если pid-файла нет
        {
            if (!touch(static::$pidfile))     //и его нельзя создать
            {
                static::log('Couldn\'t create or find pid-file \'' . static::$pidfile . '\'',1,TRUE);       //пишем ошибку в лог
                static::$pid = FALSE;
            }
            else
            {
                static::$pid = 0;                 //если можно создать - все в порядке
            }
        }
        elseif (!is_file(static::$pidfile))   //если это не файл вообще, а папка, к примеру
        {
            static::log('Pid-file \'' . static::$pidfile . '\' must be a regular file',1,TRUE); //пишем ошибку в лог
            static::$pid = FALSE;
        }
        elseif (!is_writable(static::$pidfile))   //если файл недоступен для записи
        {
            static::log('Pid-file \'' . static::$pidfile . '\' must be writable',1,TRUE);           //пишем ошибку в лог
            static::$pid = FALSE;
        }
        elseif (!is_readable(static::$pidfile))   //если файл недоступен для чтения
        {
            static::log('Pid-file \'' . static::$pidfile . '\' must be readable',1,TRUE);           //пишем ошибку в лог
            static::$pid = FALSE;
        }
        else
        {
            static::$pid = (int)file_get_contents(static::$pidfile);    //если файл есть, то берем оттуда pid работающего процесса
        }

        if(static::$pid === FALSE)        //прерываем выполнение, если возникала ошибка
        {
            static::log('Exits',1,TRUE);
            exit();
        }

    }

    /**
     * открываем лог-файл
     */
    public static function openLogs()
    {
        //имя файла логов
        static::$settings['logstorage'] = static::$settings['log_dir'].'/'.static::getName().'.log';
        if (static::$logpointer) {            //если он был ранее открыт, сперва его закроем
            fclose(static::$logpointer);
            static::$logpointer = FALSE;
        }
		static::$logpointer = fopen(static::$settings['logstorage'], 'a+');
    }

    /**
     * добавляем запись в лог от имени демона
     */
    public static function log($_msg,$_verbose = 1,$_to_stderr = FALSE)
    {
        if($_verbose <= static::$settings['logs_verbose'])        //если уровень подробности записи не выше ограничения в настройках
        {
            static::logWithSender($_msg,'DAEMON',$_to_stderr);
        }
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
			static::$runmode = $matches['runmode'];
			$args = explode(' ',$matches['args_string']);
		} else {
			static::$runmode = false;
		}

		return $args;
	}



    /**
     * инициализируем параметры демона
     */
    public static function applyArgs($_args)
    {
        //мерджим настройки из файла запуска
        static::$settings = array_merge(static::$settings,$_args);

        //если непонятно, что делать, показываем хелп и выходим
        if(empty(static::$runmode))
        {
            static::showHelp();
            exit;
        }

        //show help
        if(isset($_args['h']) && $_args['h'] === TRUE)
        {
            static::showHelp();
            exit;
        }

		foreach(static::$settings_aliases as $alias => $alias_config)
		{
			list($settings_name,$type) = $alias_config;
			if( ! empty($_args[$alias]) && settype($_args[$alias], $type))
			{
				static::$settings[$settings_name] = $_args[$alias];
				unset(static::$settings[$alias]);
			}
		}

		static::$logs_to_stderr = static::$settings['logs_to_stderr'];
    }


    //выводит справку, если демону передали параметр -h
    public static function showHelp()
    {
		printf(static::$help_message,basename($_SERVER['argv'][0]));
    }


	public static function setHelpMessage($str)
	{
		static::$help_message = $str;
	}

	public static function generateHelpMessage(Application\Base $_appl = null)
	{
		static::$help_message .= "usage: ./%s   {start|stop|restart|check}   <settings>".PHP_EOL.PHP_EOL.
								"\tDaemon settings:".PHP_EOL.
								"\t-a  -  keep daemon alive (don't daemonize)".PHP_EOL.
								"\t-v  -  verbose daemon logs".PHP_EOL.
								"\t-o  -  output logs to STDERR".PHP_EOL.
								"\t-h  -  print this help information and exit".PHP_EOL;
		if($_appl)
		{
			static::$help_message .= "\n\tApplication settings:\n";
			$appl_class = get_class($_appl);
			static::$help_message .= $appl_class::getHelpMessage();
		}
	}

}
