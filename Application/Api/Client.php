<?php

namespace Daemon\Application\Api;
use \Daemon\Utils\Helper as Helper;
use \Daemon\Daemon as Daemon;
use \Daemon\Application\Socket\Client as SocketClient;
use \Daemon\Application\Socket\Connection as SocketConnection;

/**
 * Класс реализует работу апи через сокеты
 */
class Client extends SocketClient
{
	const RESPONSE_TIMEOUT = 5;	//in seconds
	const READ_TIMEOUT = 100;	//in microseconds

	private $last_activity_time;

	public function init()
	{
		parent::init();
		$this->last_activity_time = time();
	}

	public function getResponse()
	{
		do {
			$message = $this->connection->read(true);
			usleep(self::READ_TIMEOUT);
		} while(empty($message) && (time() - $this->last_activity_time < self::RESPONSE_TIMEOUT));

		if(empty($message))
		{
			throw new \Exception("Response timeout");
		}
		$this->connection->close();
		return $message->getMessage();
	}

	//TODO API
	public function send(Command $message)
	{
		$this->connection->write($message);
		$this->last_activity_time = time();
	}

	public function listen()
	{
		throw new \BadMethodCallException();
	}
}
