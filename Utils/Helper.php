<?php

namespace Daemon\Utils;

class Helper
{
	public static function array_get(array $array, $key = '', $default = null)
	{
		foreach(explode('.', $key) as $key)
		{
			if( ! isset($array[$key]))
			{
				$array = $default;
				break;
			}
			else
			{
				$array = $array[$key];
			}
		}
		return $array;
	}

	public static function checkParams($params=array(), $rules = array()){
		if (empty($params) || empty($rules)){
			throw new \Exception('Plugin error. Wrong using function checkParams');
		}
		foreach ($rules as $field){
			if ( ! isset($params[$field])){
				$error_message = "Required item \"$field\" is empty. All items for this plugin: " . implode(", ",array_keys($rules));
				throw new \Exception($error_message);
			}
		}
	}

	function onRequestStart() {
		$dat = getrusage();
		define('PHP_TUSAGE', microtime(true));
		define('PHP_RUSAGE', $dat["ru_utime.tv_sec"]*1e6+$dat["ru_utime.tv_usec"]);
	}

	function getCpuUsage() {
		$dat = getrusage();
		$dat["ru_utime.tv_usec"] = ($dat["ru_utime.tv_sec"]*1e6 + $dat["ru_utime.tv_usec"]) - PHP_RUSAGE;
		$time = (microtime(true) - PHP_TUSAGE) * 1000000;

		// cpu per request
		if($time > 0) {
			$cpu = sprintf("%01.2f", ($dat["ru_utime.tv_usec"] / $time) * 100);
		} else {
			$cpu = '0.00';
		}

		return $cpu;
	}
}
