<?php

define("FRAMEWORK_PATH",dirname(__FILE__)."/");

function daemon_autoload($class)
{
	$filename = str_replace("\\","/",$class).".php";
	$filename = preg_replace("#^Daemon\/#",FRAMEWORK_PATH,$filename);
	if(file_exists($filename))
	{
		require_once $filename;
	}
	else
	{
		throw new Exception("Class not found for autoload: ".$class);
	}
}

spl_autoload_register('daemon_autoload');
