<?php

require_once INCLUDE_DIR_3D.'phpdaemon/Daemon.php';

class Common_Daemon_Appl extends Application_Base
{
	const DAEMON_NAME = '';


	protected static function sigUsr1()
	{
		$pid = self::getPid();
		if($pid) {
			return posix_kill(self::getPid(),SIGUSR1);
		}
	}

	protected static function sigUsr2()
	{
		$pid = self::getPid();
		if($pid) {
			return posix_kill(self::getPid(),SIGUSR2);
		}
	}

	protected static function getPid()
	{
		$pid = 0;
		$pid_file_name = self::getPidFileName();
		if(file_exists($pid_file_name)) {
			$pid = file_get_contents($pid_file_name);
		}
		return $pid;
	}


	protected static function getPidFileName()
	{
		return DAEMON_PID_DIR.(static::DAEMON_NAME).'.pid';
	}


	public static function get_settings()
	{
		return array_merge(parent::get_settings(), static::$settings);
	}

	public static function get_settings_desc()
	{
		return array_merge(parent::get_settings_desc(), static::$settings_desc);
	}

	public static function get_help()
	{
		$settings_desc = self::get_settings_desc();
		$help_message = "\nApplication \"".static::DAEMON_NAME."\" settings:\n";
		foreach($settings_desc as $name => $desc) {
			$help_message .= "\t--$name$desc\n";
		}
		return $help_message;
	}
}
