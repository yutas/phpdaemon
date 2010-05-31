<?php

class Application_Base_DB extends Application_Base
{
	protected $db;							//соединение с БД (mysqli)
	protected $db_config = array();



	protected function set_db_config($_db_config)
	{
		$this->db_config = $_db_config;
	}


	protected function db_connect()
	{
		//mysqli
		$this->db = new mysqli($this->db_config['host'],$this->db_config['user'],$this->db_config['password'],$this->db_config['database']);
		if( $this->db->connect_error )
		{
			self::log('Could not connect to database: '.$this->db_error());
			//если нет, отправляем письмо админам с ахтунгом
			die();
		}

		//charset
		if(isset($this->db_config['charset']))
		{
			$this->db_query('SET NAMES '.$this->db_config['charset']);
		}
	}



	protected function db_query($query)
	{
		$res = $this->db->query($query);
		if($this->db_error())
		{
			self::log('DB error: '.$this->db_error().' --- query: '.$query);
			return false;
		}
		return $res;
	}


	protected function db_fetch($result)
	{
		return $result->fetch_all(MYSQL_ASSOC);
	}

	protected function db_ping()
	{
		return $this->db->ping();
	}


	protected function db_error()
	{
		return $this->db->error;
	}

	protected function db_thread_id()
	{
		return $this->db->thread_id;
	}

	protected function db_close()
	{
		return $this->db->close();
	}

	protected function db_real_escape_string($_string)
	{
		return $this->db->real_escape_string($_string);
	}
}