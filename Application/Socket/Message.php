<?php

namespace Daemon\Application\Socket;

//TODO: сообщение должно уметь приаттачивать любые параметры
class Message
{
	const MCLASS_KEY = 'mclass';

	protected $message;
	protected $connection_id;

	public function __construct($message, $connection_id = 0)
	{
		$this->setMessage($message);
		$this->connection_id = intval($connection_id);
	}

	public function __toString()
	{
		return json_encode(array(
			self::MCLASS_KEY => get_class($this),
			'message' => $this->message,
		));
	}

	public function getConnectionId()
	{
		return $this->connection_id;
	}

	public function setMessage($message)
	{
		$this->message = (string)$message;
	}

	public function getMessage()
	{
		return $this->message;
	}

	public static function __fromString($message, $connection_id = 0)
	{
		$m = json_decode($message, true);
		if(empty($m[self::MCLASS_KEY]))
		{
			throw new \Exception("Incorrect message format: message '".$message."'");
		}
		$mclass = $m[self::MCLASS_KEY];
		unset($m[self::MCLASS_KEY]);
		return $mclass::create($m['message'], $connection_id);
	}

	public static function create($message, $connection_id = 0)
	{
		$class = get_called_class();
		return new $class($message, $connection_id);
	}
}
