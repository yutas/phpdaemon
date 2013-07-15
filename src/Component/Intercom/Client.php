<?php

namespace Daemon\Component\Intercom;
use \Daemon\Component\Socket;

/**
 * Класс реализует работу апи через сокеты
 */
class Client extends Socket\Client
{
	private $id;

	public function init()
	{
		parent::init();
		$this->id = posix_getpid();
		$this->handshake();
	}

	private function handshake()
	{
		$this->send(new Message\Handshake(), 0);
	}

	public function getId()
	{
		return $this->id;
	}

	public function send(Message\Message $message, $receiver = 0)
	{
		$envelope = new Socket\Envelope($message, $receiver, $this->getId());
		parent::write($envelope);
	}

	public function shutdown()
	{
		$this->send(new Message\Farewell(), 0);
		parent::shutdown();
	}
}
