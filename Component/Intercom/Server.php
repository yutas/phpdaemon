<?php

namespace Daemon\Component\Intercom;

use \Daemon\Daemon;
use \Daemon\Utils\Helper;
use \Daemon\Utils\Logger;
use \Daemon\Component\Socket;

/**
 * Класс реализует работу апи через сокеты
 */
class Server extends Socket\Server
{
	const LOG_NAME = 'Intercom';

	private $clients_base = array();

	public function listen()
	{
		try {
			$envelopes = parent::listen();
			foreach($envelopes as $k => $env)
			{
				$msg = $env->getMessage();
				//разбираем служебные сообщения
				if($msg instanceof Message\Service)
				{
					if($msg instanceof Message\Handshake)
					{
						$this->registerClient($env->getConnectionId(), $env->getSender());
					}
					if($msg instanceof Message\Farewell)
					{
						$this->unregisterClient($env->getConnectionId(), $env->getSender());
					}
					unset($envelopes[$k]);
				}
			}
			return $envelopes;
		} catch(\Exception $e) {
			static::logError($e->getMessage());
		}
	}


	public function send($message, $receiver)
	{
		try {
			$envelope = new Socket\Envelope($message, $receiver, 0);
			$envelope->setConnectionId($this->getConnectionId($receiver));
			parent::write($envelope);
		} catch(\Exception $e) {
			static::logError($e->getMessage());
		}
	}

	/**
	 * handshake - знакомимся с клиентом (заносим его в базу)
	 *
	 * @param mixed $connection_id
	 * @param mixed $sender
	 * @access private
	 * @return void
	 */
	private function registerClient($connection_id, $client_id)
	{
		if(empty($connection_id) || empty($client_id))
		{
			throw new \Exception("Both connection id and client id must be set to register a client");
		}
		if(empty($this->connections[$connection_id]))
		{
			throw new \Exception("Tried to register client with unexisting connection (conn_id: $connection_id, client_id: $client_id)");
		}
		if( ! empty($this->clients_base[$client_id]))
		{
			throw new \Exception("This client was previously registered (conn_id: $connection_id, client_id: $client_id)");
		}
		if(in_array($connection_id, $this->clients_base))
		{
			throw new \Exception("This connection was registered for another client (conn_id: $connection_id, client_id: $worker_pid)");
		}
		$this->clients_base[$client_id] = $connection_id;
		static::log("Registered client (conn_id: $connection_id, client_id: $client_id)", Logger::L_TRACE);
		return true;
	}

	protected function unregisterClient($connection_id, $client_id)
	{
		if(empty($connection_id) || empty($client_id))
		{
			throw new \Exception("Both connection id and client id must be set to unregister a client");
		}
		if(empty($this->connections[$connection_id]))
		{
			throw new \Exception("Tried to unregister client with unexisting connection (conn_id: $connection_id, client_id: $client_id)");
		}
		if(empty($this->clients_base[$client_id]))
		{
			throw new \Exception("This client was previously unregistered (conn_id: $connection_id, client_id: $client_id)");
		}
		if( ! ($connection_id == $this->clients_base[$client_id]))
		{
			throw new \Exception("This connection was registered for another client (conn_id: $connection_id, client_id: $worker_pid)");
		}
		unset($this->clients_base[$client_id]);
		unset($this->connections[$connection_id]);
		static::log("Unregistered client and connection (conn_id: $connection_id, client_id: $client_id)", Logger::L_TRACE);
		return true;
	}

	protected function getConnectionId($client_id)
	{
		if(empty($this->clients_base[$client_id]))
		{
			throw new \Exception("Trying to get unregistered client (client_id: $client_id)");
		}
		return $this->clients_base[$client_id];
	}
}
