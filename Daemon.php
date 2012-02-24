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
    protected $runmode = FALSE;
    public static $daemon_name = FALSE;                 //имя демона, которое определяет имя лог-файла: "<имя демона>.log"
	protected $appl_name = '';
    public static $settings = array(                    //настройки демона
        'max_child_count' => 20,                        //максимальное количество тредов
        'daemonize' => true,                            //демонизировать или нет
        'logs_verbose' => 1,                            //степерь подробности логирования
        'logs_to_stderr' => false,                      //выводить сообщения в STDERR
        'sigwait_nano' => 1000000,                      //задержка выполнения runtime для ожидания управляющих сигналов операционной системы (наносекунды)
        'sigwait_sec' => 0,                             //задержка выполнения runtime для ожидания управляющих сигналов операционной системы (секунды)
        'pid_dir' => '/var/run',                        //папка для хранения pid-файла
        'log_dir' => '/var/tmp',                        //папка для хранения log-файла
    );
    protected static $help_message =	"usage: %s {start|stop|restart|check} ...\n
\t-a  -  keep daemon alive (don't daemonize)
\t-v  -  verbose
\t-o  -  output logs to STDERR
\t-m  -  max child count
\t-h  -  print this help information and exit
\n";

    protected $args = array('daemon' => array(),'appl' => array());  //параметры, передаваемые демону в командной строке или в методе Daemon::init()
    protected $master;                           //класс главного процесса
    protected $appl = FALSE;                     //выполняемое приложение
	protected $args_string_pattern = "#^\b(?<runmode>start|stop|restart|check)\b\s+(?<args_string>.*)$#";

    protected static $logpointer;                       //указатель на файл логов
    protected static $logs_to_stderr;                   //указатель на файл логов

    /**
     * инициализация демона и его входных параметров
     */
    public function init($_settings)
    {
        //объединяем параметры, переданные через командную строку и через $_settings
        //порядок переопределения параметров при совпадении ключей по приоритету:
        //  1. Атрибуты класса
        //  2. $_settings переопределяет атрибуты класса
        //  3. Командная строка переопределяет $_settings
        $this->args = $this->get_args( $this->parse_args_string( implode(' ', array_slice($_SERVER['argv'],1) ) ) );
        $this->args['daemon'] = array_merge(isset($_settings['daemon']) && is_array($_settings['daemon'])?$_settings['daemon']:array(),$this->args['daemon']);
        $this->args['appl'] = array_merge(isset($_settings['appl']) && is_array($_settings['appl'])?$_settings['appl']:array(),$this->args['appl']);

        //инициализируем входные параметры демона
        $this->apply_args($this->args['daemon']);

        //задаем имя демону
        $this->set_name(isset($this->args['daemon']['name']) ? $this->args['daemon']['name'] : basename($_SERVER['argv'][0]));

        //открываем лог файл
        $this->open_logs();

        //создаем pid-файл или берем из него значение pid процесса, если он уже существует
        $this->get_pid();
    }

    /**
     * задает исполняемое приложение
     * приложение должно наследовать Application_Base
     */
    public function set_application(Application_Base $_appl)
    {
        $this->appl = $_appl;

        //инициализируем параметры приложения
        $this->appl->apply_settings($this->args['appl']);
    }

    /**
     * инициализируем имя демона
     */
    public function set_name($_pname = null)
    {
        static::$daemon_name = strtolower($_pname ? $_pname : 'phpd');
    }

    /**
     * запускаем, останавливаем или перезапускаем демон в зависимости от $runmode
     */
    public function run(Application_Base $_appl)
    {
        if($this->runmode == 'start') {
            $this->start($_appl);
        } elseif($this->runmode == 'stop') {
			$stop_mode = 1;
			if(isset(self::$settings['f']) && self::$settings['f'] == TRUE)
			{
				$stop_mode = 2;
			}
            $this->stop($stop_mode);
        } elseif($this->runmode == 'restart') {
            $this->restart($_appl);
		} elseif($this->runmode == 'check') {
			if($this->check()) {
				exit(0);
			} else {
				exit(1);
			}
		}
    }

    /**
     * собсна, запускаем демон
     */
    public function start(Application_Base $_appl)
    {
        $this->log('starting '.static::$daemon_name.'...',1,TRUE);
        //инициализируем исполняемое приложение
        $this->set_application($_appl);
        if ($this->check()) {
            $this->log('[START] phpd with pid-file \'' . self::$pidfile . '\' is running already (PID ' . self::$pid . ')',1,TRUE);
            exit;
        }

        //создаем главный процесс
        $this->master = new Thread_Master();

        //передаем приложению ссылку на мастерский процесс
        $this->appl->set_master_thread($this->master);

        //... а мастерскому процессу ссылку на приложение
        $this->master->set_application($this->appl);

        //запускаем мастерский процесс
        self::$pid = $this->master->start();
        if(-1 === self::$pid)
        {
            $this->log('could not start master');
            exit(1);
        }
    }


	public function restart(Application_Base $_appl)
	{
		$this->stop();
		sleep(1);
		$this->start($_appl);
	}


	public function check()
	{
		return self::$pid && posix_kill(self::$pid, 0);
	}


    /**
     * останавливаем демон
     */
    public function stop($mode = 1)
    {
        $this->log('Stoping '.static::$daemon_name.' (PID ' . self::$pid . ') ...',1,TRUE);
        $ok = self::$pid && posix_kill(self::$pid, $mode === 2 ? SIGINT : SIGTERM);
        if (!$ok) {
            $this->log('Error: it seems that daemon is not running' . (self::$pid ? ' (PID ' . self::$pid . ')' : ''),1,TRUE);
			file_put_contents(self::$pidfile, '');
        }
        self::$pid = 0;
    }

    /**
     * разбираемся с pid-файлом
     */
    public function get_pid()
    {
        self::$pidfile = rtrim(self::$settings['pid_dir'],'/').'/'.static::$daemon_name.'.pid';

        if (!file_exists(self::$pidfile))   //если pid-файла нет
        {
            if (!touch(self::$pidfile))     //и его нельзя создать
            {
                $this->log('Couldn\'t create or find pid-file \'' . self::$pidfile . '\'',1,TRUE);       //пишем ошибку в лог
                self::$pid = FALSE;
            }
            else
            {
                self::$pid = 0;                 //если можно создать - все в порядке
            }
        }
        elseif (!is_file(self::$pidfile))   //если это не файл вообще, а папка, к примеру
        {
            $this->log('Pid-file \'' . self::$pidfile . '\' must be a regular file',1,TRUE); //пишем ошибку в лог
            self::$pid = FALSE;
        }
        elseif (!is_writable(self::$pidfile))   //если файл недоступен для записи
        {
            $this->log('Pid-file \'' . self::$pidfile . '\' must be writable',1,TRUE);           //пишем ошибку в лог
            self::$pid = FALSE;
        }
        elseif (!is_readable(self::$pidfile))   //если файл недоступен для чтения
        {
            $this->log('Pid-file \'' . self::$pidfile . '\' must be readable',1,TRUE);           //пишем ошибку в лог
            self::$pid = FALSE;
        }
        else
        {
            self::$pid = (int)file_get_contents(self::$pidfile);    //если файл есть, то берем оттуда pid работающего процесса
        }

        if(self::$pid === FALSE)        //прерываем выполнение, если возникала ошибка
        {
            $this->log('Exits',1,TRUE);
            exit();
        }

    }

    /**
     * открываем лог-файл
     */
    public static function open_logs()
    {
        //имя файла логов
        self::$settings['logstorage'] = self::$settings['log_dir'].'/'.static::$daemon_name.'.log';
        if (self::$logpointer) {            //если он был ранее открыт, сперва его закроем
            fclose(self::$logpointer);
            self::$logpointer = FALSE;
        }
		self::$logpointer = fopen(self::$settings['logstorage'], 'a+');
    }

    /**
     * добавляем запись в лог от имени демона
     */
    public function log($_msg,$_verbose = 1,$_to_stderr = FALSE)
    {
        if($_verbose <= self::$settings['logs_verbose'])        //если уровень подробности записи не выше ограничения в настройках
        {
            $this->log_with_sender($_msg,'DAEMON',$_to_stderr);
        }
    }

    /**
     * добавляем запись в лог от имени $_sender
     */
    public function log_with_sender($_msg,$_sender = 'nobody',$_to_stderr = FALSE)
    {
        $mt = explode(' ', microtime());
        if ( ($_to_stderr || self::$logs_to_stderr) && defined('STDERR'))   //если в настройках определен вывод в STDERR
        {
            //выводим логи еще и в управляющий терминал
            fwrite(STDERR, '['.strtoupper($_sender).'] ' . $_msg . "\n");
        }
        if (self::$logpointer)                          //если файл логов был открыт без ошибок
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
    public function get_args($args)
    {
        $out = array('daemon' => array(),'appl' => array());
        $last_arg = NULL;

		foreach($args as $arg) {
            if (preg_match('~^--(.+)~', $arg, $match)) {
                $parts = explode('=', $match[1]);
                $key = preg_replace('~[^a-z0-9_]+~', '', $parts[0]);
                if (isset($parts[1])) {
                    $out['appl'][$key] = $parts[1];
                } else {
                    $out['appl'][$key] = TRUE;
                }
                $last_arg = $key;
            } elseif (preg_match('~^-([a-zA-Z0-9_]+)~', $arg, $match)) {
                for ($j = 0, $jl = strlen($match[1]); $j < $jl; ++$j) {
                    $key = $match[1] {
                        $j
                    };
                    $out['daemon'][$key] = true;
                }
                $last_arg = $key;
            } elseif ($last_arg !== NULL) {
                $out['daemon'][$last_arg] = $arg;
            }
        }
        return $out;
    }

	protected function parse_args_string($args_string = '')
	{
		$matches = array();
		$args = array();
        //инициализируем runmode
		if(preg_match($this->args_string_pattern,$args_string,$matches)) {
			$this->runmode = $matches['runmode'];
			$args = explode(' ',$matches['args_string']);
		} else {
			$this->runmode = false;
		}

		return $args;
	}



    /**
     * инициализируем параметры демона
     */
    public function apply_args($_args)
    {
        //мерджим настройки из файла запуска
        self::$settings = array_merge(self::$settings,$_args);

        //если непонятно, что делать, показываем хелп и выходим
        if(empty($this->runmode))
        {
            $this->show_help();
            exit;
        }

        //show help
        if(isset($_args['h']) && $_args['h'] === TRUE)
        {
            $this->show_help();
            exit;
        }

        //verbose
        if(isset($_args['v']) && intval($_args['v']) )
        {
            self::$settings['logs_verbose'] = 2;
			unset(self::$settings['v']);
        }

        //don't daemonize
        if(isset($_args['a']) && $_args['a'] === TRUE)
        {
            self::$settings['daemonize'] = FALSE;
			unset(self::$settings['a']);
        }

        //outputs all logs to STDERR
        if(isset($_args['o']) && $_args['o'] === TRUE)
        {
            self::$settings['logs_to_stderr'] = TRUE;
			unset(self::$settings['o']);
        }

        //max child count
        if(isset($_args['m']) && intval($_args['m']))
        {
            self::$settings['max_child_count'] = intval($_args['m']);
			unset(self::$settings['m']);
        }
		self::$logs_to_stderr = self::$settings['logs_to_stderr'];
    }


    //выводит справку, если демону передали параметр -h
    public function show_help()
    {
		printf(static::$help_message,static::$daemon_name);
    }


	public function set_help_message($str)
	{
		$this->help_message = $str;
	}


}
