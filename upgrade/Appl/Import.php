<?php


class Common_Daemon_Appl_Import extends Common_Daemon_Appl
{

	const DAEMON_NAME = 'import';

	/**
	 * @var integer - флаг перезагрузки текущего списка задач
	 */
	const RELOAD_FLAG = -1;
	/**
	 * @var array - конфигурация приложения
	 */
	protected static $settings = array(
		'foresight' => 3600,
	);
	protected static $settings_desc = array(
        'foresight' => "=3600 - время цикла (до перезагрузки набора заданий) в секундах",
	);
	/**
	 * @var Common_Importer_ImporterScheduler
	 */
	private $_scheduler;
	/**
	 * @var timestamp - время начала цикла (от которого отсчитывается дельта)
	 */
	private $_start_time_point;
	/**
 	 * @var array - задание, которое выполняется первым по списку
	 */
	private $_current_task = array('time_delta' => 0, 'source_id' => self::RELOAD_FLAG);
	/**
	 * @var array - список задач на текущий цикл
	 */
	private $_task_list = array();
	/**
 	 * @var integer - время до следующего запуска выполнения текущей задачи
	 */
	private $_sleep_time = 60;
	/**
	 * @var Common_Importer_ImporterProcess
	 */
	private $_importer;

	/**
	 *
	 */
	public function __construct()
	{
		//инициализируем расписание
		self::$settings = array_merge(self::$settings,parent::$settings);
		$this->_scheduler = new Common_Importer_ImporterScheduler();
		$this->_start_time_point = time();
		set_error_handler(array($this,'handle_warning'),E_WARNING | E_NOTICE);
		$this->_importer = new Common_Importer_ImporterProcess();
	}

	/**
	 * @return void
	 */
	public function __clone()
	{
		$this->_reloadDbConnection();
	}

	public static function reloadTaskList()
	{
		return self::sigUsr1();
	}

	/**
	 * @return void
	 */
	private function _reloadDbConnection()
	{
		//self::log("Reloading DB connection");
		Common_Registry::get('DBAL_DBManager')->setInstance('writable_instance',null);
		Common_Registry::get('DBAL_DBManager')->setInstance('readable_instance',null);
		Common_Db_Table::setConnection('writable_connection',null);
		Common_Db_Table::setConnection('readable_connection',null);
		Common_Db_Table_Factory::setTable(Common_Importer_ImporterConfig::TABLE,null);
		Common_Importer_ImporterConfig::getInstance(true);
	}


	/**
	 * @return void
	 */
	public function master_runtime()
	{
		try {
			$this->_processCurrentTask();
		} catch(Exception $e) {
			self::log($e->getMessage());
		}
		sleep($this->_sleep_time);
	}

	/**
	 * processCurrentTask - обработка текущего задания
	 *
	 * @access private
	 * @return void
	 */
	private function _processCurrentTask()
	{
		if(empty($this->_current_task)) {
			if(empty($this->_task_list)) {
				$this->_reloadTaskList();
			}
			$this->_current_task = array_shift($this->_task_list);
			if(empty($this->_current_task)) {
				$this->reloadTaskList();
			}
		}
		//если пришло время забирать файл из источника
		$past_delta = time() - $this->_start_time_point;
		if($this->_current_task['time_delta'] <= $past_delta) {
			//self::log("Time delta: ".$this->_current_task['time_delta'].". Source id: ".$this->_current_task['source_id']);
			//если задание - релоад тасклиста, выполняем его в мастере
			if($this->_current_task['source_id'] === self::RELOAD_FLAG) {
				$this->_reloadTaskList();
			} else {
				//если - забрать файл из источника, запускаем дочерний процесс с этой задачей
				$this->spawn_child(false,'importFile');
			}
			//обнуляем текущую задачу
			$this->_current_task = array();
			//ставим время перезапуска
			$this->_sleep_time = 0;
		} else {
			//спим столько, сколько надо до ближайшего задания
			$this->_sleep_time = $this->_current_task['time_delta'] - $past_delta;
			self::log("I'l wake up in ".$this->_sleep_time." sec");
		}
	}




	/**
	 * reloadTaskList - перезагрузка заданий на ближайшее время
	 *
	 * @access private
	 * @return void
	 */
	private function _reloadTaskList()
	{
		self::log('Reloading task list...');
		$this->_reloadDbConnection();
		$this->_start_time_point = time();
		$this->_task_list = $this->_scheduler->getTaskList($this->_start_time_point,$this->_start_time_point+self::$settings['foresight']);
		self::log("Loaded ".count($this->_task_list)." for nearby ".self::$settings['foresight']." sec");
		//добавляем последним флаг перезагрузки листа задач
		$this->_task_list[] = array(
			'time_delta' => self::$settings['foresight'],
			'source_id' => self::RELOAD_FLAG
		);
	}

	/**
	 * importFile - задание для дочернего процесса (скачать файл)
	 *
	 * @access public
	 * @return void
	 */
	public function importFile()
	{
		self::log("Importing file from source ".$this->_current_task['source_id']);
		try {
			$this->_importer->initImporterConfig($this->_current_task['source_id']);
			$this->_importer->downloadFilesProcess();
			return true;
		} catch (Exception $e) {
			self::log("Error importing file from source ".$this->_current_task['source_id'].": ".$e->getMessage());
			die();
		}
	}



	/**
	 * sigusr1_function - функция, вызываемая на SIGUSR1
	 *
	 * @access public
	 * @return void
	 */
	public function sigusr1_function()
	{
		self::log('Setting reload task first in list');
		$this->_current_task = array('time_delta' => 0, 'source_id' => self::RELOAD_FLAG);
	}


	/**
	 * handle_warning
	 *
	 * @param integer $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param integer $errline
	 * @param array $errcontext
	 * @access public
	 * @return void
	 */
	public function handle_warning($errno, $errstr, $errfile, $errline, array $errcontext)
	{
		switch($errno) {
			case E_WARNING:
			$error_type = 'PHP Warning';
			break;
		case E_NOTICE:
			$error_type = 'PHP Notice';
			break;
		}
		self::log($error_type.': '.$errstr.' in '.$errfile.' at line '.$errline.'. Vars used: '.str_replace("\n",' ',var_export($errcontext,true)));
	}
}
