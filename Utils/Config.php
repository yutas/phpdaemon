<?php
namespace Daemon\Utils;

use \Daemon\Utils\Helper;

class Config
{
	const PARAMS_KEY = 0;
	const DESC_KEY = 1;

    private static $base = array();
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

    public static function getBase($path, $default = null)
    {
        $cachePath = 'base.' . $path;
        if(empty(self::$cache[$cachePath]))
        {
            self::$cache[$cachePath] = Helper::array_get(static::$base, $path, $default);
        }
        return self::$cache[$cachePath];
    }

	public static function set($path, $value)
	{
		if(Helper::array_set(static::$data, $path, $value)) {
			unset(self::$cache[$path]);
			return true;
		}
	}


	public static function load($config_file = null, $config_data = null)
	{
		if ( ! empty($config_file) && ! ($yaml = file_get_contents($config_file))) {
			throw new \Exception("Failed to read config file {$config_file}");
		}

		if (empty($config_data) && ! ($data = yaml_parse($yaml))) {
			throw new \Exception("Failed to parse config file {$config_file}");
		}

        static::$data = static::$base = array_merge(static::$data, $data);
		self::$cache = array();
	}


	public static function mergeArgs($args)
	{
		foreach($args as $alias => $value) {
			if($path = self::get("Aliases.{$alias}.path")) {
				Helper::array_set(static::$data, $path, $value);
			}
		}
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


	/**
	 * update - обновляет конфигурацию системы
	 *
	 * @param mixed $config_file
	 * @static
	 * @access public
	 * @return void
	 */
	public static function update($config_file = false)
	{
		$config_file = $config_file ? : Config::get('Flags.config');
		if(self::load($config_file))
		{
			return Config::set('Flags.config', $config_file);
		}
	}
}
