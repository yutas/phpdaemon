<?php

namespace Daemon\Application\Socket;

class Message
{
	public function toJSON()
	{
		return json_encode($this);
	}


	public static function create($json)
	{
		$class = get_called_class();
		$obj = new $class();
		foreach(json_decode($json) as $par => $val)
		{
			$obj->$par = $val;
		}
		return $obj;
	}
}
