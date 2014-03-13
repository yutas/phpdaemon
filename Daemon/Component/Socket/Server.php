<?php

namespace Daemon\Component\Socket;

use Daemon\Daemon;
use Daemon\Utils\Helper;
use Daemon\Utils\Logger;

/**
 * Класс реализует работу апи через сокеты
 */
class Server extends Socket
{
	use \Daemon\Utils\LogTrait;
	const LOG_NAME = 'Socket_Server';

	protected $resource;
	protected $connections = array();

	public function init()
	{
		if( ! ($this->resource = socket_create($this->getType(true), SOCK_STREAM, 0)))
		{
			$this->throwError("Failed to create server");
		}

		if( ! socket_bind($this->resource, $this->getAddress(), $this->port))
		{
			$this->throwError("Failed to bind to socket ".$this->getAddress());
		}

		if( ! socket_listen($this->resource))
		{
			$this->throwError("Failed to set up socket listener");
		}

		if(Socket::TYPE_UNIX === $this->getType())
		{
			if( ! chmod($this->getAddress(), 0777))
			{
				$this->throwError("Failed to change socket file mode");
			}
		}
		socket_set_nonblock($this->resource);
	}



	/**
	 * accept_connections - only for server
	 *
	 * @param int $limit
	 * @access public
	 * @return void
	 */
	private function accept_connections()
	{
		$new_pack = array();
		while (true)
		{
			$client_resource = socket_accept($this->resource);
			if ($client_resource === false)
			{
				break;
			}
			$id = $this->generateId();
			static::log("Registered new connection: ".$id, Logger::L_TRACE);
			$this->connections[$id] = new Connection($client_resource, $id);
		}
		//$this->connections = $new_pack + $this->connections;
	}

	/**
	 * listen - server listens to accepted connections, client listens to server
	 *
	 * @access public
	 * @return void
	 */
	public function listen()
	{
		$this->accept_connections();
		$messages = array();
		foreach($this->connections as $connection)
		{
			$messages = array_merge($messages, $connection->read());
		}
		return $messages;
	}

	public function write(Envelope $envelope)
	{
		$this->getConnection($envelope->getConnectionId())->write($envelope);
	}

	private function getConnection($id)
	{
		if(empty($this->connections[$id]))
		{
			throw new \Exception("Tried to get unexisting connection ".$id);
		}
		return $this->connections[$id];
	}

	protected function deleteConnection($id)
	{
		if(empty($this->connections[$id]))
		{
			throw new \Exception("Tried to delete unexisting connection ".$id);
		}
		$this->connections[$id]->close();
		unset($this->connections[$id]);
	}

	public function shutdown()
	{
		foreach($this->connections as $connection)
		{
			$connection->close();
		}
		socket_shutdown($this->resource);
		socket_close($this->resource);
		if($this->type == self::TYPE_UNIX)
		{
			unlink($this->file);
		}
	}


	public function generateId()
	{
		return crc32(microtime(true).md5(rand(0,1000)));
	}
}
