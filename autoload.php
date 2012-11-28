<?php

function daemon_autoload($class)
{
	$class = ltrim($class, '\\');
	$filename = str_replace("\\","/",$class).".php";
	$filename = preg_replace("#^Daemon\/#",DAEMON_PATH,$filename);
	if( ! file_exists($filename))
	{
		throw new Exception("Class not found for autoload: ".$class);
	}
	require_once $filename;
}
