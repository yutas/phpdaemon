<?php


abstract class Daemon_Application
{

	protected $master_thread = FALSE;

	//описывает действие, которое будет повторятся в главном цикле демона
	//когда функция вернет TRUE, процесс завершится
	public function master_runtime(){}

	//если есть необходимость, запускает дочерний процесс и передает ему ссылку на себя
	public function spawn_child(){}

	//функция, которая выполняется перед главным циклом
	public function before_runtime()
	{

	}

	//функция, которая выполняется после главного цикла
	public function after_runtime()
	{

	}

	//инициализирует ссылку на главный процесс демона
	public function set_master_thread(Master_Thread $master)
	{
		$this->master_thread = $master;
	}
}