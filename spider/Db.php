<?php

namespace Spider;

class Db
{
    protected static $db = null;
    
    /**
     * @var \PDOStatement $sth
    */
    protected static $sth = null;
    
    protected function __construct()
    {}

    public static function prepare($statement)
    {
        if (self::$db == null) {
            self::$db = new \PDO('mysql:host=127.0.0.1;dbname=book');
        }
        
        self::$sth = self::$db->prepare($statement);
        return self::$sth;
    }
    
    public static function execute(array $array)
    {
        self::$sth->execute($array);
    }
    
}
