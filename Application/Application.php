<?php
namespace Daemon\Application;

abstract class Application
{
	const NAME = '';
    protected static $config = array(
        'asdfasd' => 1,
    );

	protected static $config_desc = array(
        'asdfasd' => " - some shit",
	);
	public static function getConfig() { return static::$config; }
	public static function getConfigDesc() { return static::$config_desc; }

	public static function getHelp()
	{
		$config_desc = static::getConfigDesc();
		$help_message = "\tApplication \"".static::NAME."\" settings:\n";
		foreach($config_desc as $name => $desc) {
			$help_message .= "\t--$name$desc\n";
		}
		return $help_message;
	}
}
