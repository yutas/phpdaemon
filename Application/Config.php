<?php
namespace Daemon\Application;

class Config
{
	const PARAMS_KEY = 0;
	const DESC_KEY = 1;

	private static $class_map = array(
		//class_name => $config_array
	);

	public static function create($class, array $config = array(), array $config_desc = array(), $extend = true)
	{
		$parent = get_parent_class($class);
		if(empty(static::$class_map[$class]))
		{
			static::$class_map[$class][self::PARAMS_KEY] = $config;
			static::$class_map[$class][self::DESC_KEY] = $config_desc;
		}
	   	if($extend && $parent && ! empty(static::$class_map[$parent]))
		{
			static::$class_map[$class][self::PARAMS_KEY] = array_merge(
				static::$class_map[$parent][self::PARAMS_KEY],
				static::$class_map[$class][self::PARAMS_KEY]
			);
			static::$class_map[$class][self::DESC_KEY] = array_merge(
				static::$class_map[$parent][self::DESC_KEY],
				static::$class_map[$class][self::DESC_KEY]
			);
		}
	}

	public static function add($class, array $config = array(), array $config_desc = array())
	{
		static::$class_map[$class][self::PARAMS_KEY] = array_merge(
			static::$class_map[$class][self::PARAMS_KEY],
			$config
		);
		static::$class_map[$class][self::DESC_KEY] = array_merge(
			static::$class_map[$class][self::DESC_KEY],
			$config
		);
	}

	public static function getHelpMessage($class)
	{
		$help_message = "\tApplication \"".$class::NAME."\" settings:".PHP_EOL;
		foreach(static::$class_map[$class][self::DESC_KEY] as $name => $desc) {
			$help_message .= "\t--$name$desc".PHP_EOL;
		}
		return $help_message;
	}

	public static function get($class)
	{
		if( !isset(static::$class_map[$class]))
		{
			Daemon::logError("Appl config for class '".$class."' doesn't exist");
		}
		return static::$class_map[$class];
	}
}
