<?php


class Common_Daemon_Appl_Pooh extends Common_Daemon_Appl
{

	const DAEMON_NAME = 'pooh';
	const LOG_TABLE = 'log_pooh';
	/**
	 * @var array - конфигурация приложения
	 */
	protected static $settings = array(
		'msg_pack_size' => 50,
		'msg_pack_size_delimiter' => 5,
		'test' => false,
	);
	protected static $settings_desc = array(
        'msg_pack_size' => "=100 - количество объявлений в пакете отправки",
		'msg_pack_size_delimiter' => "=10 - при ошибке отправки всего пакета, рекурсивно пытаемся разбить пакет на 10 маленьких и отправить",
		'test' => ' - тестовый запуск: один прогон одного чайлда и завершение работы демона'
	);

	/**
	 * Recieved messages
	 * @var array
	 */
	private $messages_new = array();
	/**
	 * @var array
	 */
	private $messages_edit = array();
	/**
	 * Messages that are going to be added pooh
	 * @var array
	 */
	private $pooh_adverts_new = array();
	/**
	 * Messages that are going to be edited in pooh
	 * @var array
	 */
	private $pooh_adverts_edit = array();
	/**
	 * @var int
	 */
	private $inserted_adverts_count = 0;
	/**
	 * Current attempts counter
	 * @var int
	 */
	private $attempts_count = 0;
	/**
	 * @var int
	 */
	private $queue_loop_usleep = 1000;
	/**
	 * Sleep if queue is empty, seconds
	 * @var int
	 */
	private $queue_empty_loop_sleep = 10;

	/**
	 * @var $complete_advert_queue Scanner_Queue_Message_CompleteAdvert
	 */
	private $complete_queue;


	private $counter = array();

	/**
	 * This types are sent with Pooh method 'ImportAds'
	 *
	 * @var array
	 * @access private
	 */
	private $importAd_types = array (
		Parser_Advert_Complete_DBF::TYPE,
		Parser_Advert_Complete_PartnersCSV::TYPE,
		Parser_Advert_Complete_MeidgerCSV::TYPE
	);

	/**
	 * @var int
	 */
	private $messages_count = 0;
	/**
	 * @var array
	 */
	private $current_sources = array();


	public function __construct()
	{
		self::$settings = self::get_settings();
		$this->getCompleteQueue();
	}

	/**
	 * @return void
	 */
	public function __clone()
	{
		$this->_reloadDbConnection();
	}


	public function getCompleteQueue()
	{
		$this->complete_queue = new Scanner_Queue(array('queue_type' => 'complete_advert'));
	}

	/**
	 * @return void
	 */
	public function master_runtime()
	{
		usleep($this->queue_loop_usleep);
		if($this->getMaster()->can_spawn_child()) {

			// recieving element from queue
			$message = $this->complete_queue->receiveElement();
			if(!$message->isNew()) {
				$complete_ad = $message->getMessage();
				$source = new Parser_Log_Source($complete_ad->getServiceInformationElements()->getCommonDataElement("source_id"));

				if( ! $source->isNew() && ! $source->getField('deleted')) {
					$complete_type = $complete_ad->getType();

					// unique index that is set to identify result of insert
					$uniqid = in_array($complete_type, $this->importAd_types) ? uniqid() : '';

					$adToPooh = $complete_ad->toPooh();
					$this->addMessage($message, $complete_type, $uniqid, ($complete_type == Parser_Advert_Complete_YML::TYPE && ! empty($adToPooh->GlobalAdId)));
					$this->addAdvert($adToPooh, $complete_type, $uniqid, ($complete_type == Parser_Advert_Complete_YML::TYPE && ! empty($adToPooh->GlobalAdId)));
					++$this->messages_count;
				}

				if ($this->messages_count < self::$settings['msg_pack_size']) {
					return false;
				}
			} elseif($message->isNew() && ($this->messages_count == 0)) {
				sleep($this->queue_empty_loop_sleep);
				return false;
			}
			// inserting to pooh
			if( ! self::$settings['test']) {
				$this->spawn_child(false, 'sendToPooh', 'addPoohStats');
			} else {
				$this->sendToPooh();
				$this->addPoohStats();
				return true;
			}
			$this->_reset();
		}
	}

	/**
	 * Sends evrything from pooh_adverts to Pooh
	 */
	public function sendToPooh()
	{
		self::log("Fetched ".$this->messages_count." adverts. Trying to add them to pooh...");
		$inserted_result = array();
		$pooh_results = array();
		$this->inserted_adverts_count = array();

		//добавляем новые объявки
		foreach($this->pooh_adverts_new as $type => $ad_collection) {
			try {
				$this->inserted_adverts_count[$type]['total'] = count($ad_collection);
				$this->inserted_adverts_count[$type]['success'] = 0;
				$this->_recursiveAddToPooh($ad_collection, $type);
				// reset variables
				$this->pooh_adverts_new[$type]->clear();
				$this->messages_new[$type] = array();
				self::log($this->inserted_adverts_count[$type]['success']."/".$this->inserted_adverts_count[$type]['total']." of type \"".$type."\" has been accepted by Pooh");
			} catch (Exception $e) {
				self::log($e->getMessage());
			}
		}

		//редактируем старые
		foreach($this->pooh_adverts_edit as $type => $ad_collection) {
			try {
				$this->inserted_adverts_count[$type]['total'] = count($ad_collection);
				$this->inserted_adverts_count[$type]['success'] = 0;
				$this->_recursiveEditInPooh($ad_collection, $type);
				// reset variables
				$this->pooh_adverts_edit[$type]->clear();
				$this->messages_edit[$type] = array();
				self::log($this->inserted_adverts_count[$type]['success']."/".$this->inserted_adverts_count[$type]['total']." of type \"".$type."\" where edited in Pooh");
			} catch (Exception $e) {
				self::log($e->getMessage());
			}
		}

		return true;
	}


	/**
	 * _recursiveAddToPooh - рекурсивно (в случае ошибок) добавляем новые объявления в ПУХ
	 *
	 * @param mixed $ad_collection
	 * @param mixed $type
	 * @param mixed $first_attempt
	 * @access private
	 * @return void
	 */
	private function _recursiveAddToPooh($ad_collection, $type, $first_attempt = true)
	{
		$count = count($ad_collection);
		//отправляем в пух
		try {
			$results = in_array($type,$this->importAd_types)
					? $ad_collection->importAd()
					: $ad_collection->createAds();
		} catch (SoapFault $sf) {
			self::log($sf->getMessage());
		}
		$ExternalIds = array_keys((array)$ad_collection);
		if( ! empty($results)) {
			//бежим по результатам
			foreach($ExternalIds as $key => $ExternalId) {
				$result = $results[$key];
				$ExternalId = (property_exists($result, 'ExternalId'))
						? $result->ExternalId
						: $ExternalId;
				//if(($result->Result == Services_Pooh_AdOperationResult::SUCCESS) || ($count == 1)) {
					$this->_logResult($ad_collection, $type, $ExternalId, $result, false);
				//}
			}
		}

		//если были ошибки, то делим дальше
		/*if($errors_count = count($ad_collection)) {
			$new_limit = max(1,floor(count($ad_collection)/self::$settings['msg_pack_size_delimiter']));
			$ad_collection = array_chunk((array)$ad_collection, $new_limit, true);
			foreach($ad_collection as $new_ad_collection) {
				$this->_recursiveSendToPooh(new Services_Pooh_OmtAdCollection($new_ad_collection), $type, false);
			}
		}*/

	}


	/**
	 * _recursiveEditInPooh - рекурсивно (в случае ошибок) редактируем объявления в ПУХе
	 *
	 * @param mixed $ad_collection
	 * @param mixed $type
	 * @param mixed $first_attempt
	 * @access private
	 * @return void
	 */
	private function _recursiveEditInPooh($ad_collection, $type, $first_attempt = true)
	{
		$count = count($ad_collection);
		//отправляем в пух
		try {
			$results = $ad_collection->updateAds();
		} catch (SoapFault $sf) {
			self::log($sf->getMessage());
		}
		$ExternalIds = array_keys((array)$ad_collection);
		if( ! empty($results)) {
			//бежим по результатам
			foreach($ExternalIds as $key => $ExternalId) {
				$result = $results[$key];
				$ExternalId = (property_exists($result, 'ExternalId')) ? $result->ExternalId : $ExternalId;
				if(($result->Result == Services_Pooh_AdOperationResult::SUCCESS) || ($count == 1)) {
					$this->_logResult($ad_collection, $type, $ExternalId, $result, true);
				}
			}
		}

		//если были ошибки, то делим дальше
		if($errors_count = count($ad_collection)) {
			$new_limit = max(1,floor(count($ad_collection)/self::$settings['msg_pack_size_delimiter']));
			$ad_collection = array_chunk((array)$ad_collection, $new_limit, true);
			foreach($ad_collection as $new_ad_collection) {
				$this->_recursiveEditInPooh(new Services_Pooh_OmtAdCollection($new_ad_collection), $type, false);
			}
		}

	}
	/**
	 * Adds message
	 */
	public function addMessage($message, $type, $uniqid = '', $edit = false)
	{
		$target_list = $edit ? 'messages_edit' : 'messages_new';

		if(!empty($uniqid)) {
			$this->{$target_list}[$type][$uniqid] = $message;
		} else {
			$this->{$target_list}[$type][] = $message;
		}
	}

	/**
	 * Adds adverts to pooh
	 */
	public function addAdvert($advert, $type, $uniqid = '', $edit = false)
	{
		$target_list = $edit ? 'pooh_adverts_edit' : 'pooh_adverts_new';

		if(!isset($this->{$target_list}[$type])) {
			$this->{$target_list}[$type] = new Services_Pooh_OmtAdCollection();
		}

		if($uniqid) {
			$advert->setId($uniqid);
		}

		if(empty($uniqid)) {
			$this->{$target_list}[$type][] = $advert;
		} else {
			$this->{$target_list}[$type][$uniqid] = $advert;
		}
	}

	/**
	 * Checks if there's nothing to add
	 */
	public function isEmpty()
	{
		return empty($this->messages);
	}

	/**
	 * Resets all variables
	 */
	private function _reset()
	{
		$this->messages_new = array();
		$this->messages_edit = array();
		$this->pooh_adverts_new = array();
		$this->pooh_adverts_edit = array();
		$this->messages_count = 0;
	}

	/**
	 * @return void
	 */
	private function _reloadDbConnection()
	{
		self::log("Reloading DB connection",2);
		Common_Registry::get('DBAL_DBManager')->setInstance('writable_instance',null);
		Common_Registry::get('DBAL_DBManager')->setInstance('readable_instance',null);
		Common_Registry::get('DBAL_DBManager')->setInstance('irr_instance',null);
		Common_Db_Table::setConnection('writable_connection',null);
		Common_Db_Table::setConnection('readable_connection',null);
		Common_Db_IrrTable::setConnection('readable_connection',null);
		Common_Db_Table_Factory::setTable(Scanner_Queue_Message_CompleteAdvert::TABLE,null);
		Common_Db_Table_Factory::setTable(Irr_User::TABLE,null);
	}




	private function _logResult(&$ad_collection, $type, $ExternalId, $result, $edit = false)
	{
		$SUCCESS = ($result->Result == 1);

		$messages_list = $edit ? 'messages_edit' : 'messages_new';
		$message = $this->{$messages_list}[$type][$ExternalId]->getMessage();
		$source_id = $message->getServiceInformationElements()->getCommonDataElement("source_id");
		$id_importer_config = $message->getServiceInformationElements()->getCommonDataElement("id_importer_config");
		$offer_id = $message->getAdditionalDataElement('id');

		self::log("Source: ".$source_id."\t".$result->GlobalAdId."\t".$result->Result,2);

		if($SUCCESS) {
			$this->{$messages_list}[$type][$ExternalId]->delete();
		} else {
			$this->{$messages_list}[$type][$ExternalId]->makeHideByError($result->Result);
		}
		unset($ad_collection[$ExternalId]);

		if(empty($this->counter[$source_id])) {
			$this->counter[$source_id] = array('success' => 0, 'error' => 0);
		}
		++$this->counter[$source_id][$SUCCESS ? 'success' : 'error'];

		if($SUCCESS) {
			Parser_Advert_Complete_YML::logGuid($id_importer_config,$result->GlobalAdId, $offer_id);
			++$this->inserted_adverts_count[$type]['success'];
		}

	}


	public function addPoohStats()
	{
		foreach($this->counter as $source_id => $stats) {
			$source = new Parser_Log_Source(array(Parser_Log_Source::ID_FIELD_NAME => $source_id));
			$source->addPoohStats($stats['success'], $stats['error']);
		}
	}

}
