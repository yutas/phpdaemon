#!/usr/bin/php -q
<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoload.php';
require 'Example.php';

use Daemon\Daemon;
use Daemon\Utils\Config;

// run main cycle
Config::set('project_root', __DIR__);
Daemon::run(new Master(), 'config.yml');
