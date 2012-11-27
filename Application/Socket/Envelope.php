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
	private $message;
	private $message_class;


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

	public static function __fromString($message)
	{
		$m = json_decode($message, true);
		if(empty($m[self::MCLASS_KEY]))
		{
			throw new \Exception("Incorrect message format: message '".$message."'");
		}
		$mclass = $m[self::MCLASS_KEY];
		unset($m[self::MCLASS_KEY]);
		return $mclass::create($m['message'], $connection_id);
	}

	public function getMessage()
	{
		return new $this->message_class($this->message);
	}
}
