<?php

class Thread_Collection
{
    public $threads = array();
    public $waitstatus;
    public $spawncounter = 0;
	private $child_limit = 0;


	public function __construct($limit)
	{
		if( ! empty($limit) && intval($limit)) {
			$this->child_limit = intval($limit);
		}
	}

    /* @method push
    @description Pushes certain thread to the collection.
    @param object Thread to push.
    @return void
    */
    public function push($thread)
    {
        $this->threads[$thread->pid] = $thread;
    }

    /* @method start
    @description Starts the collected threads.
    @return void
    */
    public function start()
    {
        foreach($this->threads as $thread) {
            $thread->start();
        }
    }

    /* @method stop
    @description Stops the collected threads.
    @return void
    */
    public function stop($kill = FALSE)
    {
        foreach($this->threads as $thread) {
            $thread->stop($kill);
        }
    }

    /* @method getNumber
    @description Returns a number of collected threads.
    @return integer Number.
    */
    public function getNumber()
    {
        return sizeof($this->threads);
    }

    /* @method signal
    @description Sends a signal to threads.
    @param integer Signal's number.
    @return void
    */
    public function signal($sig)
    {
        foreach($this->threads as $thread) {
            $thread->signal($sig);
        }
    }


	/**
	 * удаляем запись из коллекции при завершении работы дочернего процесса
	 */
	public function delete_spawn($_spawn_id)
	{
		if(intval($_spawn_id) && ! empty($this->threads[intval($_spawn_id)]))
		{
			unset($this->threads[$_spawn_id]);
			return true;
		}
		return false;
	}

	public function can_spawn_child()
	{
		return $this->getNumber() < $this->child_limit;
	}
}
