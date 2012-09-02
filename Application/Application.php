<?php
namespace Daemon\Application;

abstract class Application
{
    protected static $settings = array(
        'asdfasd' => 1,
    );

	protected static $settings_desc = array(
        'asdfasd' => " - some shit",
	);
	public static function getSettings() { return static::$settings; }
	public static function getSettingsDesc() { return static::$settings_desc; }
}
