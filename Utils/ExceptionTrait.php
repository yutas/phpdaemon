<?php

namespace Daemon\Utils;

use Daemon\Component\Exception\Exception;

trait ExceptionTrait
{
    public static function throwException($message, $code, \Exception $previous = null)
    {
        $e = new Exception($message, $code, $previous);
        $e->setThrower(Logger::getSenderName(get_called_class()));
        throw $e;
    }
}
