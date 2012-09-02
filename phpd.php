#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Application\Example as Appl;
use Daemon\Master\Master as Master;


error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
date_default_timezone_set('Europe/Minsk');

include_once "autoload.php";

//входные параметры демона и приложения
$settings = array('daemon' => array('sigwait' => 1000,'pid_dir' => 'tmp/','log_dir' => 'tmp/') );

try {
	//инициализируем исполняемое приложение
	$appl = new Appl();
	$master = new Master();

	//запускаем главный цикл
	Daemon::run($settings, $master, $appl);
} catch(Exception $e) {
	echo "Caught exception of class ".get_class($e).": ".$e->getMessage().PHP_EOL;
}
