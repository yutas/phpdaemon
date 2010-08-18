<?php
define('CONFIG_DIR','/www/api/applications/b2b/config/');

class Application_Example extends Application_Base_DB
{
	protected static $settings;
	private $counter = 0;


	public function  __construct()
	{
		$db_config = include CONFIG_DIR.'database.php';
		$this->set_db_config($db_config['default']);
	}


	public function  __clone()
	{
		$this->db_connect();
	}



	public function master_runtime()
	{
		echo "Master runtime\n";
		if($this->counter < 2)		//пока значение счетчика меньше двух
		{
			//создаем дочерний процесс и передаем имена функций, которые будут выполняться в дочернем процессе
			$this->spawn_child(FALSE,'child_main_action','child_after_action');
		}
		++$this->counter;
		sleep(1);
	}


	public function child_main_action()
	{
		$x = 0;
		while($x < 20){
			self::log('child '.posix_getpid().' mysql_id='.$this->db->thread_id);
			sleep(1);
		}
		return TRUE;
	}

	public function child_before_action()
	{
		
	}


	public function child_after_action()
	{
		$this->db_close();
	}

}