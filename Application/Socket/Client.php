<?php

namespace Daemon\Application\Socket;
use \Daemon\Utils\Helper as Helper;

/**
 * Класс реализует работу апи через сокеты
 */
class Client extends Socket
{
	protected $connection;

	public function init()
	{
		if( ! ($this->resource = socket_create($this->getType(), SOCK_STREAM, 0)))
		{
			$this->throwError("Failed to create client socket");
		}
		if( ! (socket_connect($this->resource, $this->getAddress(), $this->port)))
		{
			$this->throwError("Failed to connect to api server");
		}
		$this->connection = new Connection($this->resource);
	}

	/**
	 * listen - server listens to accepted clients, client listens to server
	 *
	 * @access public
	 * @return void
	 */
	public function read()
	{
		return $this->connection->read();
	}

	public function write(Message $message)
	{
		$this->connection->write($message);
	}

	public function shutdown()
	{
		$this->connection->close();
	}
}
