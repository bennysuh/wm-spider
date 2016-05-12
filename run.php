<?php

require 'vendor/autoload.php';

use Spider\User;
use Workerman\Worker;

$w = new Worker();

$w->count = 4;

$w->onWorkerStart = function() {
    
    $user = new User();
    
    for ($i = 0; $i < 100; $i++) {
        $content = $user->getUser();
        preg_match_all('#["|\']https://www.zhihu.com/people/(.*?)["|\']#', $content, $out);
    
        array_map(function($u) use ($user) {
            $user->put($u);
        }, $out[1]);
    }

};

$w->run();