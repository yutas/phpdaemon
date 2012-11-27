<?php
namespace Daemon\Utils;

use \Daemon\Utils\Helper;

class Config
{
	const PARAMS_KEY = 0;
	const DESC_KEY = 1;

	private static $data = array();
	private static $cache = array();

	public static function getHelpMessage($class)
	{
		$help_message = "\tApplication \"".$class::NAME."\" settings:".PHP_EOL;
		foreach(static::$class_map[$class][self::DESC_KEY] as $name => $desc) {
			$help_message .= "\t--$name$desc".PHP_EOL;
		}
		return $help_message;
	}

	public static function get($path, $default = null)
	{
		if(empty(self::$cache[$path]))
		{
			self::$cache[$path] = Helper::array_get(static::$data, $path, $default);
		}
		return self::$cache[$path];
	}


	public static function set($path, $value)
	{
		if(Helper::array_set(static::$data, $path, $value))
		{
			unset(self::$cache[$path]);
			return true;
		}
	}


	public static function load($config_file)
	{
		if ( ! ($yaml = file_get_contents($config_file)))
		{
			throw new \Exception("Failed to read config file");
		}
		if ( ! (static::$data = yaml_parse($yaml)))
		{
			throw new \Exception("Failed to parse config file");
		}

		return true;
	}


	public static function mergeArgs($args)
	{
		foreach($args as $alias => $value)
		{
			if($path = self::get("Aliases.{$alias}.path"))
			{
				Helper::array_set(static::$data, $path, $value);
			}
		}
		return true;
	}


	public static function getHelp()
	{
		$help = '';
		foreach(self::get('Aliases') as $alias => $data)
		{
			$alias = strlen($alias) > 1 ? "--{$alias}" : "-{$alias}";
			$help .= "\t{$alias}{$data['help']}".PHP_EOL;
		}
		return $help;
	}
}
