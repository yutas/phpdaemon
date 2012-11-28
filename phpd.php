#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Application\Examples\Example1 as Appl;
use Daemon\Utils\Helper;

//TODO: постараться убрать все нотисы и варнинги
error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
date_default_timezone_set('Europe/Minsk');

define("DAEMON_PATH",dirname(__FILE__)."/");
require_once 'autoload.php';
spl_autoload_register('daemon_autoload');

//инициализируем исполняемое приложение
$appl = new Appl();

//запускаем главный цикл
Daemon::run($appl);
