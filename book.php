<?php

require 'vendor/autoload.php';

use Spider\Queue;
use Spider\Utils;
use Workerman\Lib\Timer;
use Workerman\Worker;

$w = new Worker();

//$medoo = new medoo(require 'config.php');

$w->count = 4;

$queue = new SplQueue();

foreach ([1, 2, 3, 4] as $val) {
    $queue->enqueue($val);
}

$w->onWorkerStart = function () {

    $queue = Queue::getInstance();

    Timer::add(0.5, function () use (&$queue) {
        while (true) {
            $result = $queue->dequeue();
            if (empty($result)) {
                break;
            }
            Utils::log($result);

        }
    });
};

$w->runAll();

