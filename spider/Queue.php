<?php

namespace Spider;

use Redis;

class Queue
{
    private $redis;

    protected $tube = 'SPIDER_QUEUE';

    protected static $instance = null;

    private function __construct()
    {
        $this->redis = new Redis();
        $this->redis->pconnect('127.0.0.1');
        $this->redis->select(2);
    }

    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function enqueue($msg)
    {
        $this->redis->lPush($this->tube, $msg);
    }

    public function dequeue()
    {
        return $this->redis->rPop($this->tube);
    }

    /**
     * @param string $tube
     * @return $this
     */
    public function setTube($tube)
    {
        $this->tube = $tube;
        return $this;
    }

    public function tubes()
    {
        return $this->redis->keys('*');
    }

}