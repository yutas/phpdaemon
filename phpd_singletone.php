#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Examples\Example as Appl;
use Daemon\Thread\Master as Master;

require_once dirname(__DIR__) . '/vendor/autoload.php';

class DaemonSingletonExample extends DaemonSingleton
{
	protected static $allowed_appls = array(
		Application\Example1::NAME => '\Daemon\Application\Example1',
		Application\Example2::NAME => '\Daemon\Application\Example2',
	);
}

//входные параметры демона и приложения
$settings = array('daemon' => array('sigwait' => 1000,'pid_dir' => 'tmp/','log_dir' => 'tmp/') );

//запускаем главный цикл
DaemonSingletonExample::run($settings);
