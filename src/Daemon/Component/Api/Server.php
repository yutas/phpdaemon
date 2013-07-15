<?php

namespace Daemon\Component\Api;

use \Daemon\Utils\Helper as Helper;
use \Daemon\Daemon as Daemon;
use \Daemon\Component\Socket;

/**
 * Класс реализует работу апи через сокеты
 */
class Server extends Socket\Server
{
	/**
	 * response - sends response to received message from client
	 *
	 * @param Message $message
	 * @access public
	 * @return void
	 */
	public function response(Socket\Envelope $envelope)
	{
		$this->write($envelope);
		$this->deleteConnection($envelope->getConnectionId());
	}
}
