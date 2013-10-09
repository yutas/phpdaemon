<?php

namespace Daemon;

use Daemon\Utils\Helper;
use Daemon\Utils\Logger;
use Daemon\Utils\LogTrait;
use Daemon\Utils\ExceptionTrait;
use Daemon\Utils\Config;
use Daemon\Component\Exception\Exception;
use Daemon\Component\Application\Application;

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
    use LogTrait, ExceptionTrait;

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
    protected static $args_string_pattern = "#^(\b(?<runmode>start|stop|restart|check)\b)?\s*(?<args_string>.*)?$#";

    protected static $allowed_runmodes = array(
        self::RUNMODE_START,
        self::RUNMODE_STOP,
        self::RUNMODE_RESTART,
        self::RUNMODE_CHECK,
    );

    /**
     * инициализация демона и его входных параметров
     */
    protected static function init(Application $appl = null, $configFile = null)
    {
        register_shutdown_function('Daemon\Daemon::errorHandlerFatal');

        //разберем аргументы, переданные через командную строку
        static::$args = static::parseArgsString(implode(' ', array_slice($_SERVER['argv'],1)));

        //загрузим конфиг из файла
        if(empty(static::$args['c'])) {
            static::$args['c'] = $configFile ? : getcwd().'/'.self::DEFAULT_CONFIG_FILE;
        }
        Config::load(static::$args['c']);

        //объединяем параметры, переданные через командную строку и из файла конфигурации
        Config::mergeArgs(static::$args);

        if (Config::get('Daemon.log_php_errors', true)) {
            set_error_handler('Daemon\Daemon::errorHandler');
            error_reporting(0);
        }

        //show help
        if(Config::get('Flags.help')) {
            static::setRunmode(self::RUNMODE_HELP);
        }

        //открываем лог файл
        Logger::init(static::getName());

        static::$pidfile = static::getPidFileName();

        static::getPid();

        if(empty(static::$appl) && ! empty($appl)) {
            static::setApplication($appl);
        }
    }

    /**
     * запускаем, останавливаем или перезапускаем демон в зависимости от $runmode
     */
    public static function run(Application $appl = null, $configFile = null)
    {
        try {

            static::init($appl, $configFile);

            switch (static::$runmode) {
                case self::RUNMODE_HELP:
                    static::showHelp();
                    break;
                case self::RUNMODE_START:
                    static::start();
                    break;
                case self::RUNMODE_STOP:
                    static::stop();
                    break;
                case self::RUNMODE_RESTART:
                    static::restart();
                    break;
                case self::RUNMODE_CHECK:
                    // проверка того, что процесс запущен
                    exit(static::check());
                    break;
            }

        } catch (Exception $e) {
            static::log($e->getMessage(), Logger::L_FATAL, $e->getThrower());
            exit(1);
        } catch (\Exception $e) {
            static::log($e->getMessage(), Logger::L_FATAL);
            exit(1);
        }

        exit(0);
    }

    /**
     * собсна, запускаем демон
     */
    public static function start()
    {
        if(empty(static::$appl))
        {
            static::throwException("Can't start daemon without application", Logger::L_FATAL);
        }

        static::log('starting '.static::getName().'...', Logger::L_QUIET, null, TRUE);

        if ( ! static::check()) {
            static::throwException(sprintf('Process with PID %s is running already (pid-file %s)', static::$pid, static::$pidfile), Logger::L_FATAL);
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
            static::throwException('Could not start master', Logger::L_FATAL);
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
        return intval( ! (static::$pid && posix_kill(static::$pid, 0)));
    }


    /**
     * останавливаем демон
     */
    public static function stop()
    {
        $force = Config::get('Flags.force', false);

        static::log(sprintf('Stoping %s (PID %s)...', static::getName(), static::$pid), Logger::L_QUIET, null, TRUE);
        if ( ! (static::$pid && posix_kill(static::$pid, $force ? SIGKILL : SIGTERM))) {
            static::log('It seems that daemon is not running' . (static::$pid ? ' (PID ' . static::$pid . ')' : ''), Logger::L_ERROR);
            file_put_contents(static::$pidfile, '');
        }
        static::$pid = 0;
    }


    public static function getName()
    {
        return Config::get('Daemon.name', 'Daemon');
    }

    /**
     * разбираемся с pid-файлом
     */
    public static function getPid()
    {

        try {
            Helper::checkFile(static::$pidfile, true);
            if ( ! file_exists(static::$pidfile) && ! touch(static::$pidfile)) {
                throw new \Exception("Failed to create file '" . static::$pidfile . "'");
            }
        } catch (\Exception $e) {
            static::throwException(sprintf("Failed to create or find pid-file: %s", $e->getMessage()), Logger::L_FATAL);
        }

        static::$pid = (int)file_get_contents(static::$pidfile);
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
        if(preg_match(static::$args_string_pattern, $args_string, $matches)) {
            $args = explode(' ',$matches['args_string']);
            static::setRunmode($matches['runmode']);
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
                        $out[$key] = 1;
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
        $help_message = "Usage: %s   {%s}   <args>".PHP_EOL.PHP_EOL;
        $help_message .= Config::getHelp();
        printf($help_message, $_SERVER['argv'][0], implode('|', static::$allowed_runmodes));
        echo PHP_EOL;
        return 0;
    }

    public static function setApplication(Application $appl)
    {
        static::$appl = $appl;
    }

    protected static function setRunmode($runmode)
    {
        if( ! empty($runmode) && in_array($runmode, static::$allowed_runmodes)) {
            static::$runmode = $runmode;
        } else {
            static::$runmode = self::RUNMODE_HELP;
        }
    }

    public static function setArgsStringPattern($pattern)
    {
        static::$args_string_pattern = $pattern;
    }


    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $msg = sprintf("%s: %s in %s on line %d", static::getPhpErrorLevel($errno), $errstr, $errfile, $errline);
        switch ($errno) {
            case E_RECOVERABLE_ERROR:
                throw new Exception($msg, Logger::L_ERROR);
                break;
            default:
                static::log($msg, Logger::L_INFO);
                break;
        }
    }

    public static function errorHandlerFatal()
    {
        $error = error_get_last();
        if (is_array($error)) {
            $msg = sprintf("%s in %s on line %d", $error['message'], $error['file'], $error['line']);
            static::log($msg, Logger::L_FATAL);
        }
    }

    protected static function getPhpErrorLevel($errno)
    {
        switch ($errno) {
            case E_WARNING;
                return "E_WARNING";
            case E_NOTICE;
                return "E_NOTICE";
            case E_STRICT;
                return "E_STRICT";
            case E_DEPRECATED;
                return "E_DEPRECATED";
            case E_RECOVERABLE_ERROR;
                return "E_RECOVERABLE_ERROR";
            case E_USER_ERROR;
                return "E_USER_ERROR";
            case E_USER_WARNING;
                return "E_USER_WARNING";
            case E_USER_NOTICE;
                return "E_USER_NOTICE";
            case E_USER_DEPRECATED:
                return "E_USER_DEPRECATED";
        }
    }

    protected static function getPidFileName()
    {
        $pidDir = Config::get('Daemon.pid_dir');
        if ( ! preg_match("#^\/#", $pidDir)) {
            $pidDir = Config::get('project_root') . "/" . rtrim($pidDir, '/');
        }
        return $pidDir . '/' . static::getName() . '.pid';
    }
}
