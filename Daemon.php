<?php

include_once "thread.php";
include_once "MasterThread.php";
include_once "WorkerThread.php";
include_once "threadCollection.php";


/**
 * Класс отвечает за все операции с демоном
 */
class Daemon
{

	protected $master;							//класс главного процесса
	protected $settings = array();				//настройки демона
	
	
	public function  __construct()
	{
		$settings['pid'] = "/var/tmp/phpd.pid";
	}

	//создает главный процесс
	public function create_master()
	{

	}


	

}