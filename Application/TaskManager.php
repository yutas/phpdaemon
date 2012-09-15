<?php

namespace Daemon\Application;


class TaskManager extends Base
{
	private $config = array();


	public function __construct(array $config = array())
	{
		$this->config = $config;
	}


	public function runBefore()
	{
		//создаем сокет-серверы для внешнего и внутреннего апи
		//
		//создаём пулы по-умолчанию
	}


	public function spawnChild()
	{
		//передаём в дочерний процесс класс воркера (написать отдельно) со своими методами
	}


	public function run()
	{
		//Здесь слушаем апи (внешнее, затем внутренее), потом проверяем очередь заданий, создаем воркеров или назначаем задания текущим в пулах
	}

}
