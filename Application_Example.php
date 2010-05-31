<?php

class Application_Example extends Application_Base
{
	protected static $settings;

	public function master_runtime()
	{
		echo 1;
		sleep(1);
	}


//	public function child_action()
//	{
//		self::log('Mysqli thread_id = '.$this->db->thread_id);
//		sleep(1);
//		return TRUE;
//	}
//
//	public function child_action_1()
//	{
//		echo "Child before action! \n";
//	}
//
//
//	public function child_action_2()
//	{
//		echo "Child AFTER action! \n";
//	}
//
//
//	private function db_connect()
//	{
//		//mysqli
//		$this->db = new mysqli($this->db_config['host'],$this->db_config['user'],$this->db_config['password'],$this->db_config['database']);
//		if( $this->db->connect_error )
//		{
//			$this->log('Could not connect to database: '.$this->db_error());
//			//если нет, отправляем письмо админам с ахтунгом
//			die();
//		}
//
//		//charset
//		if(isset($this->db_config['charset']))
//		{
//			$this->db->query('SET NAMES '.$this->db_config['charset']);
//		}
//	}

}