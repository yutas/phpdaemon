#!/usr/bin/php -q
<?php

include_once "Daemon.php";

$thread = new MasterThread;

$thread->start();
