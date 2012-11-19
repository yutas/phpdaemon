#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Application\Examples\Example as Appl;

//TODO: постараться убрать все нотисы и варнинги
error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
date_default_timezone_set('Europe/Minsk');

include_once "autoload.php";

//входные параметры демона и приложения
$settings = array('daemon' => array('sigwait' => 1,'pid_dir' => 'tmp/','log_dir' => 'tmp/') );

//инициализируем исполняемое приложение
$appl = new Appl();

//запускаем главный цикл
Daemon::run($settings, $appl);
