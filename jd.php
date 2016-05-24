<?php

use Spider\Queue;

require 'vendor/autoload.php';

class Jd
{
    protected $curl;

    protected $db;

    protected $queue;

    protected $url = 'http://search.jd.com/s_new.php';

    protected $keyword = "计算机";

    public function __construct()
    {
        $this->curl = new \Curl\Curl();

        $cookie = 'jdAddrId=; jdAddrName=; TrackID=1CckpswWa6Up3iXFb87xuEoS9KanEK0wXrsyjZ8-kCT1CSHzPyIgB6QObz2RAQc8hZPnrpeP_Lw0dVngYRvCjfiCObjfwBCqhV9FDcIBYcROT7qCD7rCgd93zz3_ETk4Z; pinId=eYu14qv1Xsc; cn=7; __jdv=122270672|bing|-|organic|%25E4%25BA%25AC%25E4%25B8%259C%2B%25E6%258A%2580%25E6%259C%25AF; areaId=1; __jda=122270672.675236518.1457669113.1463497161.1464052365.29; __jdb=122270672.11.675236518|29.1464052365; __jdc=122270672; ipLoc-djd=1-72-2799-0; ipLocation=%u5317%u4EAC; mx=0_X; xtest=48870.33540.14ee22eaba297944c96afdbe5b16c65b.14ee22eaba297944c96afdbe5b16c65b; __jdu=675236518';
        $this->curl->setOpt(CURLOPT_COOKIE, $cookie);

        $this->curl->setOpt(CURLOPT_ENCODING, 'gzip');

        $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36';
        $this->curl->setUserAgent($chrome);

        $this->db = new medoo([
            'database_type' => 'mysql',
            'database_name' => 'test',
            'server'        => '127.0.0.1',
            'username'      => 'root',
            'password'      => 'fyibmsd',
            'charset'       => 'utf8',
            'port'          => 3306,
            'prefix'        => '',
        ]);

        $this->queue = Queue::getInstance();
    }

    public function run($page = 1)
    {

        $data = [
            "keyword"   => $this->keyword,
            "enc"       => "utf-8",
            "qrst"      => "1",
            "rt"        => "1",
            "stop"      => "1",
            "vt"        => "2",
            "offset"    => "3",
            "page"      => $page,
            "s"         => "28",
            "scrolling" => "y",
            "pos"       => "30",
            "log_id"    => "1464060137.94462"
        ];


        $this->curl->get($this->url, $data);

        $this->queue->enqueue($this->data($this->curl->response));

        \Spider\Utils::log("采集第 $page 页");
    }

    public function data($content)
    {
        preg_match_all('|<em>(.*?)</em>|', $content, $out);

        $data = [];

        foreach ($out[1] as $item) {
            if (strlen($item) <= 2) {
                continue;
            }
            $data[] = implode($this->keyword, explode("<font class=\"skcolor_ljg\">" . $this->keyword . "</font>", $item));
        }

        if (count($data) < 2) {
            exit('OVER');
        }

        return json_encode($data);
    }

    /**
     * @param string $keyword
     */
    public function setKeyword($keyword)
    {
        $this->keyword = $keyword;
    }

    public function setTube($tube)
    {
        $this->queue->setTube($tube);
    }

}

$jd = new Jd();



$jd->setTube($_SERVER['argv'][1]);
$jd->setKeyword($_SERVER['argv'][2]);

$page = array_key_exists(3, $_SERVER['argv']) ? $_SERVER['argv'][3] : 200;

for ($i = 1; $i < $page; $i++) {
    $jd->run($i);
}
