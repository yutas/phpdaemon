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
	const L_QUIET	= 0;

	protected static $filename;		//имя файла логов
    protected static $resource;		//указатель на файл логов
	protected static $class_name_cache = array();

	public static function init($name)
	{
        if (empty($name)) {
            throw new Exception('Log name must be defined');
        }

        static::$filename = rtrim(Config::get('Logger.log_dir'), '/') . '/' . strtolower($name) . '.log';

		self::openLogs();

		if(is_string(Config::get('Logger.verbose'))) {
			$constant = __NAMESPACE__.'\\'.Config::get('Logger.verbose');
			Config::set('Logger.verbose', defined($constant) ? constant($constant) : intval(Config::get('Logger.verbose')));
		}
	}


	public static function logError($msg, $to_stderr = FALSE)
	{
		self::logWithSender($msg, 'general', $to_stderr);
	}

    /**
     * добавляем запись в лог от имени $_sender
     */
    public static function logWithSender($_msg,$_sender = 'nobody', $_to_stderr = FALSE)
    {
        $mt = explode(' ', microtime());
        if ( ($_to_stderr || Config::get('Logger.to_stderr')) && defined('STDERR'))   //если в настройках определен вывод в STDERR
        {
            //выводим логи еще и в управляющий терминал
            fwrite(STDERR, '['.strtoupper($_sender).'] ' . $_msg . PHP_EOL);
        }
        if (is_resource(self::$resource))                          //если файл логов был открыт без ошибок
        {
            fwrite(self::$resource, '[' . date('D, j M Y H:i:s', $mt[1]) . '.' . sprintf('%06d', $mt[0] * 1000000) . ' ' . date('O') . '] ['.strtoupper($_sender).'] ' . $_msg . PHP_EOL);
        }
    }

	public static function logMemory($prefix = '')
	{
		self::log('[MEMORY] '.$prefix." Memory usage: ".round(memory_get_usage()/1024)."K", Logger::L_DEBUG);
	}

	public static function logCpu($prefix = '')
	{
		self::log('[CPU] '.$prefix." CPU usage: ".Helper::getCpuUsage()."%", Logger::L_DEBUG);
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


	public static function getLogClassName($class)
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
}
