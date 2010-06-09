#!/usr/bin/php -q
<?php

error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
date_default_timezone_set('Europe/Minsk');

include_once "Daemon.php";
include_once "Application_Example.php";

//входные параметры демона и приложения
$settings = array('daemon' => array('sigwait_nano' => 1000000,'sigwait_sec' => 0) );

//инициализируем параметры
Daemon::init($settings);

//инициализируем исполняемое приложение
$appl = new Application_Example();

//запускаем главный цикл
Daemon::run($appl);