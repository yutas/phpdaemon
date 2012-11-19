<?php

namespace Daemon\Application\Socket;
use \Daemon\Utils\Helper as Helper;
use Daemon\Daemon as Daemon;

/**
 * Класс реализует работу апи через сокеты
 */
class Server extends Socket
{
	//TODO: номера не должны кончаться, возможно нужно использовать текущее время или md5(microtime())
	const MAX_CONNECTION_ID = PHP_INT_MAX;

	protected $resource;

	protected $connections = array();
	protected $current_connection_id = 0;

	//TODO: выставлять правильные права доступа на файл сокета
	public function init()
	{
		if( ! ($this->resource = socket_create($this->getType(), SOCK_STREAM, 0)))
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
		while(true)
		{
			$client_resource = socket_accept($this->resource);
			if($client_resource === false) break;
			if($this->current_connection_id == self::MAX_CONNECTION_ID)
			{
				$this->current_connection_id = 0;
			}
			$id = ++$this->current_connection_id;
			$new_pack[$id] = new Connection($client_resource, $id);
		}
		$this->connections = $new_pack + $this->connections;
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

	public function write(Message $message)
	{
		$this->getConnection($message->getConnectionId())->write($message);
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
}
