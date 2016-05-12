<?php

require 'vendor/autoload.php';

$w = new \Workerman\Worker();

$w->count = 1;

$queue = new \Pheanstalk\Pheanstalk('127.0.0.1');

$w->onWorkerStart = function() use ($queue) {
    $pid = posix_getpid();

    $dh = opendir('cache');
    while (($file = readdir($dh)) !== false)  {
        $username = explode('.json', $file);
        if (is_string($username[0])) {
            $queue->useTube('zhihu')->put($username[0]);
        }
    }
};

$w->runAll();