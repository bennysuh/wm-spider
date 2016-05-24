<?php

namespace Spider;

class Utils
{
    public static function log($log)
    {

        echo "[x] {" . posix_getpid() . "} ---------- \e[32;40m {$log} \e[0m -----------", PHP_EOL;
    }
}
