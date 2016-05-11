<?php

require 'vendor/autoload.php';

use Spider\User;
use Workerman\Worker;

function initCurl()
{

}

$w = new Worker();

$w->count = 4;



$w->onWorkerStart = function() {
    
    $user = new User();
    
    $content = $user->getUser();
    $out = preg_match_all('#["|\']https://www.zhihu.com/people/(.*?)["|\']#', $content, $out);

    array_map(function($u) use ($user) {
        $user->put($u);
    }, $out[1]);

};

$w->run();