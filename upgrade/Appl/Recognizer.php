<?php


class Common_Daemon_Appl_Recognizer extends Common_Daemon_Appl
{

	const DAEMON_NAME = 'recognizer';
	const MANUAL_COLLECTION_NAME = 'manual';

	/**
	 * @var array - конфигурация приложения
	 */
	protected static $settings = array(
		'test' => false,
		'manual_child_limit' => 10,
	);
	protected static $settings_desc = array(
		'test' => ' - тестовый запуск: один прогон одного чайлда и завершение работы демона',
		'manual_child_limit' => '=10 - максимальное количество одновременных ручных распознаваний',
	);

	protected $manual;
	protected $source_id;
	protected $id_importer_config;

	/**
	 *
	 */
	public function __construct()
	{
		//инициализируем расписание
		self::$settings = self::get_settings();
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
		//Common_Db_Table_Factory::setTable(Common_Importer_ImporterConfig::TABLE,null);
		//Common_Importer_ImporterConfig::getInstance(true);
	}

	public function before_runtime()
	{
		$this->getMaster()->addChildCollection(self::MANUAL_COLLECTION_NAME, self::$settings['manual_child_limit']);
	}

	/**
	 * @return void
	 */
	public function master_runtime()
	{
		try {

			if($this->getMaster()->can_spawn_child(self::MANUAL_COLLECTION_NAME) && $this->fetchReadySource(1)) {
				if($this->getMaster()->can_spawn_child(self::MANUAL_COLLECTION_NAME)) {
					$this->hideFetchedSource($this->source_id);
					if( ! self::$settings['test']) {
						$this->spawn_child(false, 'recognizeSource', false, self::MANUAL_COLLECTION_NAME);
					} else {
						$this->recognizeSource();
						return true;
					}
				}
			}

			if($this->getMaster()->can_spawn_child(Thread_Master::MAIN_COLLECTION_NAME) && $this->fetchReadySource(0)) {
				if($this->getMaster()->can_spawn_child(Thread_Master::MAIN_COLLECTION_NAME)) {
					$this->hideFetchedSource($this->source_id);
					if( ! self::$settings['test']) {
						$this->spawn_child(false, 'recognizeSource', false, Thread_Master::MAIN_COLLECTION_NAME);
					} else {
						$this->recognizeSource();
						return true;
					}
				}
			}
		} catch(Exception $e) {
			self::log($e->getMessage());
		}
	}




	private function fetchReadySource($manual = 0)
	{
		//self::log('Trying to fetch '.($manual ? 'manual' : 'auto').' source');
		$sql = "SELECT id AS source_id, manual, id_importer_config FROM log_sources
				WHERE deleted = 0 AND manual = ".intval($manual)." AND status = ".Parser_Log_Constant::SOURCE_STATUS_READY_FOR_RECOGNITION." ORDER BY id limit 1";
		$res = Common_Db_Table::getReadableConnection()->query($sql)->fetch(0);
		if( ! empty($res))
		{
			$this->manual = $res['manual'];
			$this->source_id = $res['source_id'];
			$this->id_importer_config = $res['id_importer_config'];
			return true;
		}
		return false;
	}


	private function hideFetchedSource($source_id)
	{
		return Parser_Log_Source::setStatus($source_id, Parser_Log_Constant::SOURCE_STATUS_RECOGNITION_IN_PROGRESS);
	}

	public function recognizeSource()
	{
		$ready_advert_count = 0;
		$complete_advert_count = 0;
		$incomplete_advert_count = 0;
		$untrusted_advert_count = 0;
		$count = 0;
		$step = 0;
		$page = 50;
		$while = true;
		$toDeleteArray = array();
		$complete_advert_queue = new Scanner_Queue(array('queue_type' => 'complete_advert'));

		$totalCount = -1;
		while ($while) {
			$raw_adverts = Common_Db_Table_Factory::create(Scanner_Queue_Message_RawAdvert::TABLE)->findAll(
				array('source_id' => $this->source_id),
				array('offset' => ($step * $page), 'limit' => $page),
				array('field' => 'id')
			);
			//$sql = "SELECT id FROM ".Scanner_Queue_Message_RawAdvert::TABLE." WHERE source_id = ".intval($this->source_id)." LIMIT ".$page." OFFSET ".($step * $page);
			//$raw_adverts = Common_Db_Table::getReadableConnection()->query($sql)->fetchAll();

			if ($totalCount < 0) {
				$totalCount = $raw_adverts['totalCount'];
				self::log("Fetched ".$totalCount." adverts for ".($this->manual ? 'manual' : 'auto')." processing of source #".$this->source_id);
			}

			if ($totalCount > $step * $page) {
				foreach ($raw_adverts['items'] as $value) {
					$count++;

					$advert_queue_element = new Scanner_Queue_Message_RawAdvert($value['id'], true);
					$advert_queue_element->init($value);
					$raw_advert = $advert_queue_element->getMessage();

					/** @var $raw_advert Parser_Advert_Raw_Abstract */
					if (!($raw_advert instanceof Parser_Advert_Raw_Abstract)) {
						throw new Parser_Exception(Parser_Error::CORRUPT_RAW_ADVERT);
					}

					try {
						$raw_advert->setAdvertTypeId(null);
						$complete_advert = $raw_advert->convertToCompleteAdvert();
						if ( ! $this->manual) {
							$complete_advert_queue->addElement($complete_advert, $this->source_id, $this->id_importer_config);
						}

						Parser_Log_Helper::logSource($this->source_id, Parser_Log_Constant::ADVERT_STATUS_READY);

						++$ready_advert_count;
						++$complete_advert_count;

					} catch (Exception $e) {
						//self::log('Error code: '.$e->getCode().'. Error message: '.$e->getMessage());
						switch($e->getCode()) {
							case Parser_Error::UNTRUSTED_ADVERT :
								++$untrusted_advert_count;
								Parser_Log_Helper::logSource($this->source_id, Parser_Log_Constant::ADVERT_STATUS_UNTRUSTED);
								Parser_Log_Helper::logIncompleteAdvert($raw_advert, $e, Parser_Log_Constant::INCOMPLETE_LEVEL_UNTRUSTED);
								break;
							case Parser_Error::UNCONFIRMED_MAPPING :
								++$complete_advert_count;
								Parser_Log_Helper::logSource($this->source_id, Parser_Log_Constant::ADVERT_STATUS_COMPLETE);
								Parser_Log_Helper::logIncompleteAdvert($raw_advert, $e, Parser_Log_Constant::INCOMPLETE_LEVEL_NOT_READY);
								break;
							case Parser_Error::UNDEFINED_ADVERT_TYPE :
							default:
								++$incomplete_advert_count;
								Parser_Log_Helper::logSource($this->source_id, Parser_Log_Constant::ADVERT_STATUS_INCOMPLETE);
								Parser_Log_Helper::logIncompleteAdvert($raw_advert, $e, Parser_Log_Constant::INCOMPLETE_LEVEL_INCOMPLETE);
								break;
						}
					}

				}
			} else {
				$while = false;
			}
			$step++;
		}
		//обновляем статус импорта
		$this->updateSourceStatus($this->source_id);

		//чистим таблицу сырых объявлений
		if ( ! $this->manual) {
			Common_Db_Table_Factory::create(Scanner_Queue_Message_RawAdvert::TABLE)->delete(array('source_id' => $this->source_id));
		}

		self::log('Importer #'.$this->id_importer_config.' Source #'.$this->source_id.': '.$totalCount.' adverts where processed.'.
			' ready: '.$ready_advert_count.', complete:'.$complete_advert_count.', untrusted: '.$untrusted_advert_count.', incomplete: '.$incomplete_advert_count);

		return true;
	}

	function updateSourceStatus ($source_id) {
		$log_source = new Parser_Log_Source($source_id);
		if(($log_source->getField('complete_ads') + $log_source->getField('incomplete_ads') + $log_source->getField('untrusted_ads')) == $log_source->getField('total_ads')){
			$status = $log_source->getField('manual') ?
				Parser_Log_Constant::SOURCE_STATUS_RECOGNITION_COMPLETED :
				Parser_Log_Constant::SOURCE_STATUS_POOH_IN_PROGRESS;
			$log_source->setField('status', $status)->save();
		} elseif ($log_source->getField('status') == Parser_Log_Constant::SOURCE_STATUS_NEW) {
			$log_source->setField('status', Parser_Log_Constant::SOURCE_STATUS_RECOGNITION_IN_PROGRESS)->save();
		}
		Common_Importer_ImporterConfig_Element::getInstance($log_source->getField('id_importer_config'))->changeStatus();
	}

}
