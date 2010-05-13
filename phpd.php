#!/usr/bin/php -q
<?php

include_once "Daemon.php";
include_once "Application.php";
date_default_timezone_set('Europe/Minsk');

//Daemon::set_name("phpdtest");
Daemon::init();

//задаем нужное выполняемое приложение
$appl = new Application();
Daemon::set_application($appl);

//запускаем главный цикл
Daemon::run();