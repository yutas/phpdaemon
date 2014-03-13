<?php

spl_autoload_register(function ($class) {
    $filename = dirname(__DIR__) . DIRECTORY_SEPARATOR
                . str_replace("\\", DIRECTORY_SEPARATOR, preg_replace("/^Daemon/", 'lib', $class)) . '.php';
    if (file_exists($filename)) include $filename;
});

$composerAutoloadFile = dirname(__DIR__). DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($composerAutoloadFile)) {
    include $composerAutoloadFile;
}