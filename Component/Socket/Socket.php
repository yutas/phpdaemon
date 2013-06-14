<?php

namespace Daemon\Component\Socket;

use Daemon\Utils\Helper as Helper;

/**
 * Класс реализует работу апи через сокеты
 */
abstract class Socket
{
	const TYPE_INET = 'inet';
	const TYPE_UNIX = 'unix';

	protected $resource;
	protected $type = self::TYPE_UNIX;
	protected $ip = '127.0.0.1';
	protected $port = 34000;
	protected $file = '/tmp/api.sock';

	public function __construct(array $config)
	{
		$this->type = Helper::array_get($config,'type',self::TYPE_UNIX);
		if($this->type == self::TYPE_UNIX)
		{
			$this->file = Helper::array_get($config,'file');
		}
		elseif($this->type == self::TYPE_INET)
		{
			$this->ip = Helper::array_get($config,'ip');
			$this->port = Helper::array_get($config,'port');
			if(empty($this->ip) || empty($this->port))
			{
				throw new \Exception("Both host and port must be set");
			}
		}
	}

	public function init()
	{

	}

	public function shutdown()
	{

	}

	protected function getAddress()
	{
		return $this->type == self::TYPE_UNIX ? $this->file : $this->ip;
	}

	protected function getType($raw = false)
	{
		return $raw ? ($this->type == self::TYPE_UNIX ? AF_UNIX : AF_INET) : $this->type;
	}

	protected function throwError($prefix = '')
	{
		throw new \Exception(rtrim($prefix, '.').". Socket error #".socket_last_error().": ".socket_strerror(socket_last_error()));
	}
}
