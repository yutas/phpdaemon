#!/usr/bin/php -q
<?php

error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
date_default_timezone_set('Europe/Minsk');

include_once "Daemon.php";
include_once "Application_Example.php";

//входные параметры демона и приложения
$settings = array('daemon' => array('sigwait' => 1000,'pid_dir' => 'tmp/','log_dir' => 'tmp/') );

//инициализируем исполняемое приложение
$appl = new Application_Example();

//запускаем главный цикл
Daemon::run($settings, $appl);
