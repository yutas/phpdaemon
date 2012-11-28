<?php

namespace Daemon\Application\Socket;
use \Daemon\Utils\Helper;

//TODO: изучить тонкости сокетных соединений
class Connection
{
	//const DELIMITER = "\n\n============\n\n";
	//TODO разделитель переводить в символы при передаче в сокет
	const DELIMITER = "\u00C0\u00C1\u00C2\u00C3\u00C4\u00C5\u00C6\u00C7";
	const READ_LENGTH = 102400;
	const RESOURCE_TYPE = 'Socket';

	private $resource;
	private $id;

	public function __construct($resource, $id)
	{
		if(get_resource_type($resource) !== self::RESOURCE_TYPE)
		{
			throw new \Exception("Unsupported resourse type '".get_resource_type($resource)."' received");
		}
		$this->resource = $resource;
		socket_set_nonblock($this->resource);
		$this->id = $id;
	}

	public function read()
	{
		$data = '';
		$env_list = array();
		while($chunk = socket_read($this->resource, self::READ_LENGTH))
		{
			$data .= $chunk;
		}
		if( ! empty($data))
		{
			$data = explode(self::DELIMITER, $data);
			foreach($data as $e)
			{
				if( ! empty($e))
				{
					$e = Envelope::__fromString($e);
					$e->setConnectionId($this->id);
					$env_list[] = $e;
				}
			}
		}
		return $env_list;
	}

	public function write(Envelope $envelope)
	{
		if( ! socket_write($this->resource, $envelope.self::DELIMITER))
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
