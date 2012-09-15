#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Application\Example as Appl;
use Daemon\Thread\Master as Master;


error_reporting(E_ALL ^E_NOTICE ^E_WARNING ^E_STRICT);
date_default_timezone_set('Europe/Minsk');

include_once "autoload.php";
Application\Example1::getHelp(); exit;



class DaemonSingletonExample extends DaemonSingleton
{
	protected static $allowed_appls = array(
		Application\Example1::NAME => '\Daemon\Application\Example1',
		Application\Example2::NAME => '\Daemon\Application\Example2',
	);
}

//входные параметры демона и приложения
$settings = array('daemon' => array('sigwait' => 1000,'pid_dir' => 'tmp/','log_dir' => 'tmp/') );



try {
	//запускаем главный цикл
	DaemonSingletonExample::run($settings, new Master(), null);
} catch(Exception $e) {
	echo "Caught exception of class ".get_class($e).": ".$e->getMessage().PHP_EOL;
}
