<?php
namespace Daemon\Examples;

use Daemon\Component\Application\Application;
use Daemon\Utils\Config;
use Daemon\Component\Socket;
use Daemon\Component\Intercom;
use Daemon\Component\Intercom\Message as IntercomMessage;
use Daemon\Component\Api;
use Daemon\Utils\Logger;

class Example1 extends Application
{
	use \Daemon\Utils\LogTrait;

	const LOG_NAME = 'example1';

	private $counter = 0;
	private $intercom;
	private $intercom_config = array(
		'type' => Socket\Socket::TYPE_UNIX,
		'ip' => '127.0.0.1',
		'port' => 30000,
		'file' => 'tmp/intercom.sock',
	);
	private $api_config = array(
		'type' => Socket\Socket::TYPE_INET,
		'ip' => '127.0.0.1',
		'port' => 30001,
		'file' => 'tmp/api.sock',
	);
	private $client;


	public function runBefore()
	{
		$this->intercom = new Intercom\Server($this->intercom_config);
		$this->intercom->init();

		$this->api = new Api\Server($this->api_config);
		$this->api->init();
	}

	public function run()
	{
		//static::log("Master runtime");
		if($e = $this->intercom->listen())
		{
			foreach($e as $envelope)
			{
				//echo $envelope."\n";
				/*$message = $envelope->getMessage();
				static::log(sprintf("Got message \"%s\"", $message->text), Logger::L_DEBUG);
				$response = new IntercomMessage\Message();
				$response->text = "Ответ мастера треду ".$envelope->getSender();
				$this->intercom->send($response, $envelope->getSender());*/
			}
		}
		if($e = $this->api->listen())
		{
			foreach($e as $envelope)
			{
				$message = $envelope->getMessage();
				static::log(sprintf("Got API message \"%s\"", $message->text));
				$response = new Socket\Envelope(new Api\Command());
				$this->api->response();
			}
		}
		if($this->counter < 1)		//пока значение счетчика меньше двух
		{
			//создаем дочерний процесс и передаем имена функций, которые будут выполняться в дочернем процессе
			$child_pid = $this->spawnChild('child_before_action', 'child_main_action', FALSE, 'onShutdown');
		}
		++$this->counter;
	}


	public function child_main_action()
	{
		unset($this->intercom);
		if($e = $this->client->listen())
		{
			foreach($e as $envelope)
			{
				$message = $envelope->getMessage();
				static::log(sprintf("Got message \"%s\"", $message->text), Logger::L_DEBUG);
			}
		}
		$message = new IntercomMessage\Message();
		$message->text = 'Тестовое сообщение от треда '.posix_getpid().' мастеру';
		//$this->client->send($message);
		sleep(1);
		return false;
	}

	public function child_before_action()
	{
		static::log("Before");
		$this->client = new Intercom\Client($this->intercom_config);
		$this->client->init();
	}

	public function onShutdown()
	{
		if($this->intercom instanceof Intercom\Server) $this->intercom->shutdown();
		if($this->client instanceof Intercom\Client) $this->client->shutdown();
		if($this->api instanceof Api\Server) $this->api->shutdown();
	}

}
