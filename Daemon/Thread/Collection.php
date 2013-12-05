<?php
namespace Daemon\Thread;

class Collection
{
    public $threads = array();
    public $waitstatus;
	private $child_limit = 0;
	//TODO: органично встроить статические/динамические коллекции


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
	public function deleteChild($_spawn_id)
	{
        $_spawn_id = intval($_spawn_id);

		if($_spawn_id > 0 && array_key_exists($_spawn_id, $this->threads))
		{
			unset($this->threads[$_spawn_id]);
			return true;
		}

		return false;
	}

	public function canSpawnChild()
	{
		return $this->getNumber() < $this->child_limit;
	}
}
