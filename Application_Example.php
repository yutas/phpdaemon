<?php
define('CONFIG_DIR','/www/api/applications/b2b/config/');

class Application_Example extends Application_Base
{
	protected static $settings;
	private $counter = 0;


	public function master_runtime()
	{
		self::log("Master runtime");
		if($this->counter < 2)		//пока значение счетчика меньше двух
		{
			//создаем дочерний процесс и передаем имена функций, которые будут выполняться в дочернем процессе
			$this->spawn_child(FALSE,'child_main_action',FALSE);
		}
		++$this->counter;
		sleep(1);
	}


	public function child_main_action()
	{
		$x = 0;
		while($x < 20){
			self::log('child '.posix_getpid());
			sleep(1);
		}
		return TRUE;
	}

	public function child_before_action()
	{
		
	}

}
