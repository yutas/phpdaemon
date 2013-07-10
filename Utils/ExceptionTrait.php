<?php

namespace Daemon\Utils;

use Daemon\Component\Exception\Exception;

trait ExceptionTrait
{
    public static function throwException($message, $code = Logger::L_ERROR, \Exception $previous = null)
    {
        $e = new Exception($message, $code, $previous);
        $e->setThrower($previous instanceOf Exception && $previous->getThrower() ? $previous->getThrower() :  Logger::getSenderName(get_called_class()));
        throw $e;
    }
}
