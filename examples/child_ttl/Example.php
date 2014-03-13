<?php

use Daemon\Component\Application\Application;

class Master extends Application
{
    const LOG_NAME = 'example-master';

    private $counter = 0;

    public function run()
    {
        static::log("Master runtime");
        if($this->getMaster()->canSpawnChild())
        {
            //fork child process
            $this->spawnChild(new Worker());
        }
        sleep(1);
    }

    public function onRun() {}
    public function onShutdown() {}
}

class Worker extends Application
{
    const LOG_NAME = 'example-worker';

    public function run()
    {
        self::log('child '.posix_getpid());
        sleep(1);
    }

    public function onRun() {}
    public function onShutdown() {}
}

