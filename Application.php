<?php

class Application extends Daemon_Application
{
	private $counter = 0;

	public function master_runtime()
	{
		echo 2;
		if($this->counter < 1)
		{
			$this->master_thread->spawn_child(array($this,'child_action'));
		}
		$this->counter++;
//		if($this->counter == 10)
//		{
//			return TRUE;
//		}
		sleep(1);
	}


	public function child_action()
	{
		echo 3;
		sleep(1);
	}
}