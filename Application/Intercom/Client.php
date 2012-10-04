<?php

namespace Daemon\Application\Intercom;
use \Daemon\Utils\Helper as Helper;
use \Daemon\Daemon as Daemon;
use \Daemon\Application\Socket\Client as SocketClient;
use \Daemon\Application\Socket\Connection as SocketConnection;

/**
 * Класс реализует работу апи через сокеты
 */
class Client extends SocketClient
{
	public function listen()
	{
		return $this->read();
	}
}
