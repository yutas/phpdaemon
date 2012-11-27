<?php

namespace Daemon\Application\Api;
use \Daemon\Utils\Helper as Helper;
use \Daemon\Daemon as Daemon;
use \Daemon\Application\Socket\Server as SocketServer;

/**
 * Класс реализует работу апи через сокеты
 */
class Server extends SocketServer
{
	/**
	 * response - sends response to received message from client
	 *
	 * @param Message $message
	 * @access public
	 * @return void
	 */
	public function response(Command $message)
	{
		$this->write($message);
		$this->deleteConnection($message->getConnectionId());
	}
}
