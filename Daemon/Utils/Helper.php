<?php

namespace Daemon\Utils;

class Helper
{
	public static function array_get(array $array, $path = '', $default = null)
	{
		if ( ! empty($path)) {
			foreach (explode('.', $path) as $key) {
				if ( ! isset($array[$key])) {
					$array = $default;
					break;
				} else {
					$array = $array[$key];
				}
			}
		}
		return $array;
	}

	public static function array_set(array &$array, $path, $value)
	{
		$a = &$array;
		if ( ! empty($path)) {
			$keys = explode('.', $path);
			$depth = count($keys) - 1;
			foreach ($keys as $key) {
				if ( ! array_key_exists($key, $a)) {
					$a[$key] = $depth ? array() : null;
				} elseif($depth && ! is_array($a[$key])) {
					$a[$key] = array();
				}
				$a = &$a[$key];
				$depth--;
			}
		}
		$a = $value;
		return true;
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

	public static function onRequestStart() {
		$dat = getrusage();
		define('PHP_TUSAGE', microtime(true));
		define('PHP_RUSAGE', $dat["ru_utime.tv_sec"]*1e6+$dat["ru_utime.tv_usec"]);
	}

	public static function getCpuUsage() {
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

    public static function checkFile($path, $checkWritable = false)
    {
        self::checkFolder(dirname($path), $checkWritable);
        return self::_checkFile($path, $checkWritable, true);
    }

    public static function checkFolder($path, $checkWritable = false)
    {
        return self::_checkFile($path, $checkWritable, false);
    }

    private static function _checkFile($path, $checkWritable, $isFile)
    {
        $target = $isFile ? 'file' : 'folder';

        if ( ! $checkWritable && ! file_exists($path)) {
            throw new \Exception(ucfirst($target) . " \"$path\" does not exist");
        }

        if ($isFile && $checkWritable && ! file_exists($path) && ! touch($path)) {
            throw new \Exception("Failed to create $target \"$path\"");
        }

        if ( ! $isFile && is_file($path) || $isFile && is_dir($path)) {
            throw new \Exception("\"$path\" must be a $target");
        }

        if ( ! is_readable($path)) {
            throw new \Exception(ucfirst($target) . " \"$path\" must be readable");
        }

        if ($checkWritable && ! is_writable($path)) {
            throw new \Exception(ucfirst($target) . " \"$path\" must be writable");
        }

        return true;
    }
}
