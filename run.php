<?php

require 'vendor/autoload.php';

use Spider\User;
use Workerman\Worker;

$w = new Worker();

$w->count = 4;
$user = new User();

$w->onWorkerStart = function() use ($user) {

    $user->log('Start');

    for ($i = 0; $i < 1000; $i++) {
        $user::$parent = $user->getUserData();
    }

    $followees_users = $user->getUserIndex($user::$parent, 'followees');
    $followers_users = $user->getUserIndex($user::$parent, 'followers');

    $user->put($followees_users);
    $user->put($followers_users);

};

$w->runAll();
