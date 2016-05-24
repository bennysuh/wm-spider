<?php

use Spider\Db;
use Spider\Queue;
use Spider\Utils;
use Workerman\Lib\Timer;

require 'vendor/autoload.php';

$w = new \Workerman\Worker();

$w->count = 4;

$w->onWorkerStart = function () {

    $sql = 'INSERT INTO `ts_title` (title, tube) VALUES (:title, :tube)';
    Db::prepare($sql);

    $queue = Queue::getInstance();

    Timer::add(0.5, function () use (&$queue) {
        while (true) {
            $tubes = $queue->tubes();

            if (count($tubes) == 0) {
                break;
            }

            $books = $queue->setTube($tubes[0])->dequeue();

            if (empty($books)) {
                array_shift($tubes);
                continue;
            }

            foreach (json_decode($books, true) as $bookname) {
                Db::execute([':title' => $bookname, ':tube' => $tubes[0]]);
                Utils::log($bookname);
            }
        }
    });
};

$w->runAll();
