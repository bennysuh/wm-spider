<?php

require 'vendor/autoload.php';

use Spider\User;
use Workerman\Worker;

$w = new Worker();

$w->count = 4;
$user = new User();

$w->onWorkerStart = function() use ($user) {

    while ($username = $user->getUser()) {
        
        $follow = $user->getUserFollow($username);
        
        preg_match_all('#["|\']https://www.zhihu.com/people/(.*?)["|\']#', $follow, $out);

        array_map(function($u) use ($user) {
            $user->put($u);
        }, $out[1]);
    }

};

$w->runAll();