<?php

class Thread_Collection
{
    public $threads = array();
    public $waitstatus;
    public $spawncounter = 0;
	
    /* @method push
    @description Pushes certain thread to the collection.
    @param object Thread to push.
    @return void
    */
    public function push($thread)
    {
        ++$this->spawncounter;
        $thread->spawnid = $this->spawncounter;
        $this->threads[$thread->spawnid] = $thread;
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
        return $this->spawncounter; //sizeof($this->threads);
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
		if(intval($_spawn_id))
		{
			unset($this->threads[$_spawn_id]);
			--$this->spawncounter;
		}
	}
}
