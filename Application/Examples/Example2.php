<?php
namespace Daemon\Application\Examples;

use \Daemon\Application\Application;
use \Daemon\Application\Config;

class Example2 extends Application
{
	const NAME = 'example2';

	private $counter = 0;

	public function  __construct($only_help = false)
	{
		parent::__construct($only_help);
		Config::create(__CLASS__, array(), array(), true);
		if($only_help)
		{
			return;
		}
	}

	public function run()
	{
		self::log("[".static::NAME."] Master runtime");
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
			self::log("[".static::NAME.'] child '.posix_getpid());
			sleep(1);
			$x++;
		}
		return TRUE;
	}

	public function child_before_action()
	{

	}

}