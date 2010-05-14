<?php


abstract class Application_Base
{

	protected $appl_settings = array();
	protected $master_thread = FALSE;


	public function  __construct(){}


	//передаем параметры из командной строки
	public function apply_settings($_appl_settings)
	{
		$this->appl_settings = array_merge($this->appl_settings,$_appl_settings);
	}

	//описывает действие, которое будет повторятся в главном цикле демона
	//когда функция вернет TRUE, процесс завершится
	public function master_runtime(){}

	//если есть необходимость, запускает дочерний процесс и передает ему ссылку на себя
	//когда функция вернет TRUE, процесс завершится
	public function spawn_child(){}

	//функция, которая выполняется перед главным циклом
	public function before_runtime(){}

	//функция, которая выполняется после главного цикла
	public function after_runtime(){}

	//инициализирует ссылку на главный процесс демона
	public function set_master_thread(Thread_Master $master)
	{
		$this->master_thread = $master;
	}
}