<?php

namespace Daemon\Component\Exception;


class Exception extends \Exception
{
    protected $thrower;

    public function setThrower($thrower)
    {
        $this->thrower = $thrower;
    }

    public function getThrower()
    {
        return $this->thrower;
    }
}