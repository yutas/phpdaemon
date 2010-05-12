<?php


abstract class Daemon_Application
{

	protected $master_thread = FALSE;

	//описывает действие, которое будет повторятся в главном цикле демона
	public function master_action(){}

	//действие, которое выполняет дочерний процесс
	public function child_action(){}

	//если есть необходимость, запускает дочерний процесс и передает ему ссылку на себя
	public function spawn_child(){}

	//инициализирует ссылку на главный процесс демона
	public function set_master_thread(Master_Thread $master)
	{
		$this->master_thread = $master;
	}
}