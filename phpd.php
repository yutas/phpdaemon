#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Application\Examples\Example1 as Appl;
use Daemon\Utils\Helper;

//TODO: постараться убрать все нотисы и варнинги
error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
date_default_timezone_set('Europe/Minsk');

include_once "autoload.php";

//инициализируем исполняемое приложение
$appl = new Appl();

//запускаем главный цикл
Daemon::run($appl);
