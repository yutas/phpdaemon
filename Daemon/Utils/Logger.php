<?php

namespace Daemon\Utils;
use \Daemon\Daemon;

class Logger
{
	//verbose levels
	const L_TRACE	= 4;
	const L_DEBUG	= 3;
	const L_INFO	= 2;
	const L_ERROR	= 1;
    const L_FATAL   = 0;
	const L_QUIET	= -1;

    protected static $labels = [
        self::L_TRACE => 'TRACE',
        self::L_DEBUG => 'DEBUG',
        self::L_INFO  => 'INFO',
        self::L_ERROR => 'ERROR',
        self::L_FATAL => 'FATAL',
    ];

	protected static $filename;		//имя файла логов
    protected static $resource;		//указатель на файл логов
	protected static $class_name_cache = array();

	public static function init($name)
	{
        if (empty($name)) {
            throw new Exception('Log name must be defined');
        }

        static::$filename = static::getLogFileName($name);

		self::openLogs();

		if(is_string(Config::get('Logger.verbose'))) {    // если уровень подробности указан только в файле конфига
			$constant = __NAMESPACE__.'\\'.Config::get('Logger.verbose');
			Config::set('Logger.verbose', defined($constant) ? constant($constant) : static::L_FATAL);
		} elseif(is_int(Config::get('Logger.verbose'))) {     // если уровень подробности передается дополнительно в командной строке, увеличиваем базовый из файла
            $constant = __NAMESPACE__.'\\'.Config::getBase('Logger.verbose', static::L_FATAL);
            $base = defined($constant) ? constant($constant) : static::L_FATAL;
            Config::set('Logger.verbose', $base + (int) Config::get('Logger.verbose'));
        }
	}

    /**
     * открываем лог-файл
     */
    public static function openLogs()
    {
        if (self::$resource) {            //если он был ранее открыт, сперва его закроем
            fclose(self::$resource);
            self::$resource = FALSE;
        }
        //имя файла логов
        self::$resource = fopen(self::$filename, 'a+');
    }


    public static function log($msg, $level = Logger::L_ERROR, $sender = '', $to_stderr = false)
    {
        if($level <= Config::get('Logger.verbose'))
        {
            if ($level == static::L_FATAL) {
                $to_stderr = true;
            }
            Logger::logWithSender(static::addLabel($msg, $level), $sender, $to_stderr);
        }
    }

    /**
     * добавляем запись в лог от имени $sender
     */
    public static function logWithSender($msg, $sender = 'nobody', $to_stderr = FALSE)
    {
        $mt = explode(' ', microtime());
        if ( ($to_stderr || Config::get('Logger.to_stderr')) && defined('STDERR'))   //если в настройках определен вывод в STDERR
        {
            //выводим логи еще и в управляющий терминал
            fwrite(STDERR, '['.strtoupper($sender).'] ' . $msg . PHP_EOL);
        }
        if (is_resource(self::$resource))                          //если файл логов был открыт без ошибок
        {
            fwrite(self::$resource, '[' . date('D, j M Y H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ' ' . date('O') . '] ['.strtoupper($sender).'] ' . $msg . PHP_EOL);
        }
    }


	public static function getSenderName($class)
	{
		$md5 = md5($class);
		if(empty(self::$class_name_cache[$md5]))
		{
			if(defined($class.'::LOG_NAME'))
			{
				self::$class_name_cache[$md5] = constant($class.'::LOG_NAME');
			}
			else
			{
				$a = explode('\\', $class);
				self::$class_name_cache[$md5] = strtoupper(end($a));
			}
		}
		return self::$class_name_cache[$md5];
	}

    protected static function getLogFileName($name)
    {
        $logDir = Config::get('Logger.log_dir');
        if ( ! preg_match("^\/", $logDir)) {
            $logDir = Config::get('project_root') . "/" . rtrim($logDir, '/');
        }
        return $logDir . '/' . strtolower($name) . '.log';
    }

    protected static function addLabel($msg, $level)
    {
        $label = empty(static::$labels[$level]) ? '' : '['.strtoupper(static::$labels[$level]).'] ';
        return  $label . $msg;
    }

    public static function logMemory($prefix = '')
    {
        self::log('[MEMORY] '.$prefix." Memory usage: ".round(memory_get_usage()/1024)."K", Logger::L_DEBUG);
    }

    public static function logCpu($prefix = '')
    {
        self::log('[CPU] '.$prefix." CPU usage: ".Helper::getCpuUsage()."%", Logger::L_DEBUG);
    }
}
