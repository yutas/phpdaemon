<?php
namespace Daemon\Application\Examples;

use \Daemon\Application\Application;
use \Daemon\Config;

class Example extends Application
{
	const LOG_NAME = 'example';

	private $counter = 0;

	public function run()
	{
		static::log("Master runtime");
		if($this->counter < 2)		//пока значение счетчика меньше двух
		{
			//создаем дочерний процесс и передаем имена функций, которые будут выполняться в дочернем процессе
			$this->spawnChild(FALSE,'child_main_action',FALSE);
		}
		++$this->counter;
		sleep(1);
	}


	public function child_main_action()
	{
		$x = 0;
		while($x < 20){
			static::log('child '.posix_getpid());
			sleep(1);
			$x++;
		}
		return TRUE;
	}

	public function child_before_action()
	{

	}

}
