<?php

namespace Daemon\Utils;

class Logger
{
	//verbose levels
	const L_TRACE = 4;
	const L_DEBUG = 3;
	const L_INFO = 2;
	const L_ERROR = 1;
	const L_MIN = 0;

	protected static $filename;
    protected static $pointer;                       //указатель на файл логов
    protected static $to_stderr;                   //выводить ли логи в STDERR


	public static function init()
	{
		self::openLogs();
		self::$to_stderr = Config::get('Logger.to_stderr');
	}

    /**
     * добавляем запись в лог от имени демона
     */
	//TODO может быть использовать трейты для того, чтобы подмешать функцию log в нужный класс
    public static function log($_msg,$_verbose = self::L_MIN, $_config_verbose = self::L_MIN, $_to_stderr = FALSE)
    {
        if($_verbose <= $_config_verbose)        //если уровень подробности записи не выше ограничения в настройках
        {
            self::logWithSender($_msg,'DAEMON',$_to_stderr);
        }
    }

	public static function logError($_msg, $_to_stderr = FALSE)
	{
		$_msg = '[ERROR] '.$_msg;
		self::log($_msg, self::L_ERROR, $_to_stderr);
	}

    /**
     * добавляем запись в лог от имени $_sender
     */
    public static function logWithSender($_msg,$_sender = 'nobody',$_to_stderr = FALSE)
    {
        $mt = explode(' ', microtime());
        if ( ($_to_stderr || self::$to_stderr) && defined('STDERR'))   //если в настройках определен вывод в STDERR
        {
            //выводим логи еще и в управляющий терминал
            fwrite(STDERR, '['.strtoupper($_sender).'] ' . $_msg . PHP_EOL);
        }
        if (self::$resource)                          //если файл логов был открыт без ошибок
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
        self::$filename = Config::get('Logger.dir').'/'.Daemon::getName().'.log';
		self::$resource = fopen(self::$filename, 'a+');
    }
}
