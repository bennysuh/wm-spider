<?php
namespace Spider;

use Curl\Curl;
use Pheanstalk\Pheanstalk;

class User
{
    const TUBE = 'zhihu';
    
    protected $curl;
    protected $queue;
    
    protected $url = "https://www.zhihu.com/people/%s";
    
    public function __construct()
    {
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_COOKIE, trim(file_get_contents('cookie.txt')));
        $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36';
        $this->curl->setUserAgent($chrome);
        
        $this->queue = new Pheanstalk('127.0.0.1');
        $this->queue->put('fyibmsd');
    }
    
    public function getUser()
    {
        $job = $this->queue->watch(self::TUBE)->ignore('default')->reserve();
        $username = $job->getData();
        var_dump($username);
        $url = sprintf($this->url, $username);
        $this->curl->get($url);
        return ($this->curl->httpStatusCode == 200) ? $this->curl->response : false;
    }
    
    public function put($job)
    {
        $this->queue->useTube(self::TUBE)->put($job);
    }
}