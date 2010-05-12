#!/usr/bin/php -q
<?php

include_once "Daemon.php";
date_default_timezone_set('Europe/Minsk');

class Application extends Daemon_Application
{
	private $counter = 0;
	
	public function master_action()
	{
		echo 2;
		if(!$this->counter)
		{
			$this->master_thread->spawn_child(array($this,'child_action'));
		}
		$this->counter++;
	}


	public function child_action()
	{
		echo 3;
	}
}

//Daemon::set_name("phpdtest");
Daemon::init();

//задаем нужное выполняемое приложение
$appl = new Application();
Daemon::set_application($appl);

//запускаем главный цикл
Daemon::run();