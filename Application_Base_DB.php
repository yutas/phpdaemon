<?php

class Application_Base_DB extends Application_Base
{
    protected $db;                          //соединение с БД (mysqli)
    protected $db_config = array();
    protected $reconnect_attemts = 10;      //попытки переподключиться к БД



    protected function set_db_config($_db_config)
    {
        $this->db_config = $_db_config;
    }


    protected function db_connect()
    {
        unset($this->db);
        //mysqli
        $this->db = new mysqli($this->db_config['host'],$this->db_config['user'],$this->db_config['password'],$this->db_config['database']);
        if( $this->db->connect_error )
        {
            self::log('Could not connect to database: error '.$this->connect_errno.' '.$this->db->connect_error);
            //если нет, отправляем письмо админам с ахтунгом
            if($this->db_errno() == 2006)
            {
                return $this->reconnect();
            }
            return FALSE;
        }

        //charset
        if(isset($this->db_config['charset']))
        {
            $this->db_query('SET NAMES '.$this->db_config['charset']);
        }

        return TRUE;
    }



    protected function db_query($query)
    {
        $res = $this->db->query($query);
        if($this->db_error())
        {
            self::log('DB error '.$this->db_errno().': '.$this->db_error().' --- query: '.$query);
            if($this->db_errno() == 2006)
            {
                if($this->reconnect())
                {
                    return $this->db_query($query);
                }
            }
            return false;
        }
        return $res;
    }


    protected function db_call_sp($sql)
    {
        if($res = $this->db_query($sql))
        {
            while($this->db->next_result()) $this->db->store_result();
            return $res->fetch_assoc();
        }
        else
        {
            return false;
        }
    }

    /**
     * Функция переподключается к БД
     *
     * @return boolean
     */
    protected function reconnect()
    {
        if($this->reconnect_attemts)
        {
            self::log('Trying to reconnect... ');
            unset($this->db);
            --$this->reconnect_attemts;
            if($this->db_connect())
            {
                $this->reconnect_attemts = 10;
                self::log('Successfully reconnected! ');
                return TRUE;
            }
            else
            {
                return $this->reconnect();
            }
        }
    }


    protected function db_fetch($result)
    {
        return $this->fetch_all($result);
    }

    protected function db_ping()
    {
        return $this->db->ping();
    }


    protected function db_error()
    {
        return $this->db->error;
    }

    protected function db_errno()
    {
        return $this->db->errno;
    }

    protected function db_thread_id()
    {
        return $this->db->thread_id;
    }

    protected function db_close()
    {
        return $this->db->close();
    }

    protected function db_real_escape_string($_string)
    {
        return $this->db->real_escape_string($_string);
    }


    protected function fetch_all($res)
    {
        $rows = array();
        if($res->num_rows)
        {
            while($rows[] = $res->fetch_assoc()){}
            return $rows;
        }
        return FALSE;
    }


}
