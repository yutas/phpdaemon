<?php

use Daemon\Component\Application\Application;

class Master extends Application
{
    const LOG_NAME = 'example-master';

    private $counter = 0;

    public function run()
    {
        static::log("Master runtime");
        if($this->counter < 2)		//пока значение счетчика меньше двух
        {
            //создаем дочерний процесс и передаем имена функций, которые будут выполняться в дочернем процессе
            $this->spawnChild(new Worker());
        }
        ++$this->counter;
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
        $x = 0;
        while($x < 10){
            self::log('child '.posix_getpid());
            sleep(1);
            $x++;
        }
        return TRUE;
    }

    public function onRun() {}
    public function onShutdown() {}
}

