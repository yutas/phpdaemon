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
		if( ! ($this->resource = socket_create($this->getType(true), SOCK_STREAM, 0)))
		{
			$this->throwError("Failed to create client socket");
		}
		if( ! (socket_connect($this->resource, $this->getAddress(), $this->port)))
		{
			$this->throwError("Failed to connect to socket server");
		}
		$this->connection = new Connection($this->resource);
	}

	/**
	 * listen - server listens to accepted clients, client listens to server
	 *
	 * @access public
	 * @return void
	 */
	public function listen()
	{
		return $this->connection->read();
	}

	public function write(Envelope $envelope)
	{
		$this->connection->write($envelope);
	}

	public function shutdown()
	{
		$this->connection->close();
	}
}
