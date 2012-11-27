<?php
namespace Daemon\Application\Socket;

/**
 * Envelope - класс-конверт, который будет содержать адресата, отправителя и любое сообщение
 *
 * @version $id$
 * @author Domashevich Artyom <adomashevich@prontosoft.by>
 */
class Envelope
{
	private $receiver;
	private $sender;
	private $message_class;
	private $message;
	private $connection_id;


	public function __construct(Message $message, $receiver, $sender)
	{
		$this->message = $message;
		$this->message_class = get_class($message);
		$this->receiver = $receiver;
		$this->sender = $sender;
	}

	public function __toString()
	{
		return json_encode(array(
			'message_class' => $this->message_class,
			'message' => $this->message->toJSON(),
			'receiver' => $this->receiver,
			'sender' => $this->sender,
		));
	}

	public static function __fromString($json)
	{
		if( ! ($data = json_decode($json, true)))
		{
			throw new \Exception("Incorrect envelope format: received data - '".$json."'");
		}

		$message_class = $data['message_class'];
		$message = $message_class::create($data['message']);
		$envelope = new self($message, $data['receiver'], $data['sender']);
		return $envelope;
	}

	public function getMessage()
	{
		return $this->message;
	}

	public function getReceiver()
	{
		return $this->receiver;
	}

	public function getSender()
	{
		return $this->sender;
	}

	public function getConnectionId()
	{
		return $this->connection_id;
	}

	public function setConnectionId($id)
	{
		$this->connection_id = $id;
	}
}
