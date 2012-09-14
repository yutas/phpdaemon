<?php
namespace Daemon\Application;

abstract class Application
{
    protected static $config = array(
        'asdfasd' => 1,
    );

	protected static $config_desc = array(
        'asdfasd' => " - some shit",
	);
	public static function getConfig() { return static::$config; }
	public static function getConfigDesc() { return static::$config_desc; }
}
