<?php

namespace Daemon\Application\Socket;

//TODO: сообщение должно уметь приаттачивать любые параметры
class Message
{
	protected $message;

	public function __construct($message)
	{
		$this->setMessage($message);
	}

	public function __toString()
	{
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

	public static function create($message, $connection_id = 0)
	{
		$class = get_called_class();
		return new $class($message, $connection_id);
	}
}
