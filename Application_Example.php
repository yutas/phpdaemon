<?php

class Application_Example extends Application_Base
{
	protected static $settings;
	private $counter = 0;

	public function master_runtime()
	{
		echo "Master runtime\n";
		if($this->counter < 1)		//пока значение счетчика меньше двух
		{
			//создаем дочерний процесс и передаем имена функций, которые будут выполняться в дочернем процессе
			$this->spawn_child('child_before_action','child_main_action','child_after_action');
		}
		++$this->counter;
		sleep(1);
	}


	public function child_main_action()
	{
		$x = 0;
		while($x < 20){
			echo "Child main action ".$x."\n";
			$x++;
			sleep(1);
		}
//		return TRUE;
	}

	public function child_before_action()
	{
		echo "Child BEFORE action! \n";
	}


	public function child_after_action()
	{
		echo "Child AFTER action! \n";
	}

}