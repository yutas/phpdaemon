#!/usr/bin/php -q
<?php
namespace Daemon;

use Daemon\Examples\Example2 as Appl;
use Daemon\Utils\Helper;

require_once dirname(__DIR__) . '/vendor/autoload.php';

//инициализируем исполняемое приложение
$appl = new Appl();

//запускаем главный цикл
Daemon::run($appl);
