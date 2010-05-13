#!/usr/bin/php -q
<?php

include_once "Daemon.php";
include_once "Example_Application.php";
date_default_timezone_set('Europe/Minsk');

//Daemon::set_name("phpdtest");

//задаем нужное выполняемое приложение
$appl = new Example_Application();
Daemon::set_application($appl);

Daemon::init();

//запускаем главный цикл
Daemon::run();