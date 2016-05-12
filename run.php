<?php

require 'vendor/autoload.php';

use Spider\User;
use Workerman\Worker;

$w = new Worker();

$w->count = 4;
$user = new User();

$w->onWorkerStart = function(Worker $w) use ($user) {

    $user->log('Start');

    for ($i = 0; $i < 1000; $i++) {
        $user::$parent = $user->getUserData();
    }

    $user->getUserIndex($user::$parent, 'followees');
    $user->getUserIndex($user::$parent, 'followers');


};

$w->runAll();
