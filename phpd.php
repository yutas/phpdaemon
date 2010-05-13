#!/usr/bin/php -q
<?php

include_once "Daemon.php";
include_once "Application_Example.php";
date_default_timezone_set('Europe/Minsk');

//Daemon::set_name("phpdtest");

//задаем нужное выполняемое приложение
$appl = new Application_Example();
Daemon::set_application($appl);

Daemon::init();

//запускаем главный цикл
Daemon::run();