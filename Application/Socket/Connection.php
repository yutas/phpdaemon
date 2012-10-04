<?php

namespace Daemon\Application\Socket;
use \Daemon\Utils\Helper as Helper;

class Connection
{
	const DELIMITER = "\n\n============\n\n";
	const READ_LENGTH = 102400;
	const RESOURCE_TYPE = 'Socket';

	private $resource;
	private $id;

	public function __construct($resource, $id = 0)
	{
		if(get_resource_type($resource) !== self::RESOURCE_TYPE)
		{
			throw new \Exception("Unsupported resourse type '".get_resource_type($resource)."' received");
		}
		$this->resource = $resource;
		socket_set_nonblock($this->resource);
		$this->id = intval($id);
	}

	public function read($one = false)
	{
		$data = '';
		$msg_list = array();
		while($chunk = socket_read($this->resource, self::READ_LENGTH))
		{
			$data .= $chunk;
		}
		if( ! empty($data))
		{
			$data = explode(self::DELIMITER, $data);
			foreach($data as $m)
			{
				if( ! empty($m))
				{
					$msg_list[] = Message::__fromString($m, $this->id);
				}
			}
			if($one) $msg_list = array_shift($msg_list);
		}
		return $msg_list;
	}

	public function write(Message $message)
	{
		if( ! socket_write($this->resource, $message.self::DELIMITER))
		{
			$this->throwError("Failed to write to socket");
		}
	}

	public function close()
	{
		return socket_close($this->resource);
	}

	protected function throwError($prefix = '')
	{
		throw new \Exception(rtrim($prefix, '.').". Socket error #".socket_last_error().": ".socket_strerror(socket_last_error()));
	}
}
