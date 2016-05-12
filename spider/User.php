<?php
namespace Spider;

use Curl\Curl;
use Pheanstalk\Pheanstalk;

class User
{
    const TUBE = 'zhihu';
    
    protected $curl;
    protected $queue;
    
    protected $url = "https://www.zhihu.com/people/%s/followees";
    
    public function __construct()
    {
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_COOKIE, trim(file_get_contents('cookie.txt')));
        $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36';
        $this->curl->setUserAgent($chrome);
        
        $this->queue = new Pheanstalk('127.0.0.1');
        $this->queue->useTube(self::TUBE)->put('fyibmsd');
    }
    
    public function getUser()
    {
        $job = $this->queue->watch(self::TUBE)->ignore('default')->reserve();
        $username = $job->getData();
        $url = sprintf($this->url, $username);
        var_dump($url);
        $this->curl->get($url);
        if ($this->curl->httpStatusCode == 200) {
            $this->queue->delete($job);
            $result = $this->curl->response;
            file_put_contents(realpath('.') . '/cache/' . $username, $result);
            return $result;
        }
        return false;
    }
    
    public function put($job)
    {
        $this->queue->useTube(self::TUBE)->put($job);
    }
}