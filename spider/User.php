<?php
namespace Spider;

use Curl\Curl;
use medoo;
use Pheanstalk\Pheanstalk;

class User
{
    const TUBE = 'zhihu';
    
    protected $curl;
    protected $db;
    protected $queue;

    protected $url = "https://www.zhihu.com/people/%s/%s";

    protected $node = 'https://www.zhihu.com/node/%s';
    
    public static $parent = 'fyibmsd';


    /**
     * @var \Pheanstalk\Job
    */
    public $job;

    public function __construct()
    {
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_COOKIE, trim(file_get_contents('cookie.txt')));
        $this->curl->setOpt(CURLOPT_ENCODING , 'gzip');

        $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36';
        $this->curl->setUserAgent($chrome);
        
        $this->queue = new Pheanstalk('127.0.0.1');

        $this->queue->useTube(self::TUBE)->put(self::$parent);

        $this->db = new medoo([
            'database_type' => 'mysql',
            'database_name' => 'test',
            'server' => '127.0.0.1',
            'username' => 'root',
            'password' => 'fyibmsd',
            'charset' => 'utf8',
            'port' => 3306,
            'prefix' => '',
        ]);
    }
    
    public function getUserData()
    {
        $this->job = $this->queue->watch(self::TUBE)->ignore('default')->reserve();

        $username = $this->job->getData();

        $this->log('采集用户 '. $username);

        $url = sprintf($this->url, $username, 'about');
        $this->curl->get($url);

        if ($this->curl->httpStatusCode == 200) {
            $this->queue->delete($this->job);
            $content = $this->curl->response;
            $result = $this->getUserAbout($content);

            $result['username'] = $username;
            $result['parent_username'] = self::$parent;
            $result['addtime'] = time();


            file_put_contents(dirname('.') . '/cache/' . $username . '.json', json_encode($result));
            $this->log($username . ' : OK!');

            // $this->db->insert('user', $result);
            // echo $this->db->last_query() . PHP_EOL;

            return $username;
        }

        return false;
    }

    public function getUserIndex($username, $user_type = 'followees')
    {
        $url = sprintf($this->url, $username, $user_type);

        $this->curl->get($url);
        
        if ($this->curl->httpStatusCode == 200) {
            $content = $this->curl->response;
            preg_match_all('#<h2 class="zm-list-content-title"><a data-tip=".*?" href="https://www.zhihu.com/people/(.*?)" class="zg-link" title=".*?"#', $content, $out);

            $count = count($out[1]);
            $keyword = $user_type == 'followees' ? '关注了' : '关注者';

            $this->log("采集用户 -- {$user_type} -- [{$count}]");

            preg_match('#<span class="zg-gray-normal">'.$keyword.'</span><br />\s<strong>(.*?)</strong><label> 人</label>#', $content, $out);
            $user_count = empty($out[1]) ? 0 : intval($out[1]);

            preg_match('#<input type="hidden" name="_xsrf" value="(.*?)"/>#', $content, $out);
            $_xsrf = empty($out[1]) ? '' : trim($out[1]);

            preg_match('#<div class="zh-general-list clearfix" data-init="(.*?)">#', $content, $out);
            $url_params = empty($out[1]) ? '' : json_decode(html_entity_decode($out[1]), true);

            if (!empty($_xsrf) && !empty($url_params) && is_array($url_params)) {
                $url = sprintf($this->node, $url_params['nodename']);
                $params = $url_params['params'];
                $page = 1;

                $users = [];

                for ($i = 0; $i < $user_count; $i += 20) {
                    $this->log("采集用户 -- {$url_params['nodename']} -- 第{$page}页");
                    $params['offset'] = $i;
                    $data = $this->curl->buildPostData([
                        'method' => 'next',
                        'params' => json_encode($params),
                        '_xsrf'  => $_xsrf
                    ]);
                    $this->curl->post($url, $data);
                    $content = $this->curl->response;

                    foreach ($content->msg as $row) {
                        preg_match_all('#<h2 class="zm-list-content-title"><a data-tip=".*?" href="https://www.zhihu.com/people/(.*?)" class="zg-link" title=".*?"#', $row, $out);
                        $users[] = $out[1][0];
                    }
                    $page++;
                    $this->put($users);
                    $users = [];
                }
                
                return $users;
            }

            return $this->curl->response;
        }
        
        return false;
    }
    
    public function getUserAbout($content)
    {
        $data = [];
        if (empty($content))
        {
            return $data;
        }

        // 一句话介绍
        preg_match('#<span class="bio" title=["|\'](.*?)["|\']>#', $content, $out);
        $data['headline'] = empty($out[1]) ? '' : $out[1];
        
        // 头像
        //preg_match('#<img alt="龙威廉"\ssrc="(.*?)"\sclass="zm-profile-header-img zg-avatar-big zm-avatar-editor-preview"/>#', $content, $out);
        preg_match('#<img class="Avatar Avatar--l" src="(.*?)" srcset#', $content, $out);
        $data['headimg'] = empty($out[1]) ? '' : $out[1];

        // 昵称
        preg_match_all('#<title> (.*?) - 知乎</title>#', $content, $out);
        $data['nickname'] = empty($out[1][0]) ? '' : $out[1][0];

        // 居住地
        preg_match('#<input autocomplete="off" aria-haspopup="true" type="text" name="location" value=["|\'](.*?)["|\']#', $content, $out);
        $data['location'] = empty($out[1]) ? '' : $out[1];

        // 所在行业
        preg_match('#<span class="business item" title=["|\'](.*?)["|\']>#', $content, $out);
        $data['business'] = empty($out[1]) ? '' : $out[1];

        // 性别
        preg_match('#<span class="item gender" ><i class="icon icon-profile-(.*?)"></i></span>#', $content, $out);
        $gender = empty($out[1]) ? 'other' : $out[1];
        if ($gender == 'female')
            $data['gender'] = 0;
        elseif ($gender == 'male')
            $data['gender'] = 1;
        else
            $data['gender'] = 2;

        // 公司或组织名称
        preg_match('#<span class="employment item" title=["|\'](.*?)["|\']>#', $content, $out);
        $data['employment'] = empty($out[1]) ? '' : $out[1];

        // 职位
        preg_match('#<span class="position item" title=["|\'](.*?)["|\']>#', $content, $out);
        $data['position'] = empty($out[1]) ? '' : $out[1];

        // 学校或教育机构名
        preg_match('#<span class="education item" title=["|\'](.*?)["|\']>#', $content, $out);
        $data['education'] = empty($out[1]) ? '' : $out[1];

        // 专业方向
        preg_match('#<span class="education-extra item" title=["|\'](.*?)["|\']>#', $content, $out);
        $data['education_extra'] = empty($out[1]) ? '' : $out[1];

        // 新浪微博
        preg_match('#<a class="zm-profile-header-user-weibo" target="_blank" href="(.*?)"#', $content, $out);
        $data['weibo'] = empty($out[1]) ? '' : $out[1];

        // 个人简介
        preg_match('#<span class="content">\s(.*?)\s</span>#s', $content, $out);
        $data['description'] = empty($out[1]) ? '' : trim(strip_tags($out[1]));

        // 关注了、关注者
        preg_match('#<span class="zg-gray-normal">关注了</span><br />\s<strong>(.*?)</strong><label> 人</label>#', $content, $out);
        $data['followees'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#<span class="zg-gray-normal">关注者</span><br />\s<strong>(.*?)</strong><label> 人</label>#', $content, $out);
        $data['followers'] = empty($out[1]) ? 0 : intval($out[1]);

        // 关注专栏
        preg_match('#<strong>(.*?) 个专栏</strong>#', $content, $out);
        $data['followed'] = empty($out[1]) ? 0 : intval($out[1]);

        // 关注话题
        preg_match('#<strong>(.*?) 个话题</strong>#', $content, $out);
        $data['topics'] = empty($out[1]) ? 0 : intval($out[1]);

        // 关注专栏
        preg_match('#个人主页被 <strong>(.*?)</strong> 人浏览#', $content, $out);
        $data['pv'] = empty($out[1]) ? 0 : intval($out[1]);

        // 提问、回答、专栏文章、收藏、公共编辑
        preg_match('#提问\s<span class="num">(.*?)</span>#', $content, $out);
        $data['asks'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#回答\s<span class="num">(.*?)</span>#', $content, $out);
        $data['answers'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#专栏文章\s<span class="num">(.*?)</span>#', $content, $out);
        $data['posts'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#收藏\s<span class="num">(.*?)</span>#', $content, $out);
        $data['collections'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#公共编辑\s<span class="num">(.*?)</span>#', $content, $out);
        $data['logs'] = empty($out[1]) ? 0 : intval($out[1]);

        // 赞同、感谢、收藏、分享
        preg_match('#<strong>(.*?)</strong> 赞同#', $content, $out);
        $data['votes'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#<strong>(.*?)</strong> 感谢#', $content, $out);
        $data['thanks'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#<strong>(.*?)</strong> 收藏#', $content, $out);
        $data['favs'] = empty($out[1]) ? 0 : intval($out[1]);
        preg_match('#<strong>(.*?)</strong> 分享#', $content, $out);
        $data['shares'] = empty($out[1]) ? 0 : intval($out[1]);
        return $data;
    }
    
    public function put($job)
    {
        if (is_array($job)) {
            array_map(function($item) {
                $this->queue->useTube(self::TUBE)->put($item);
            }, $job);
        } else {
            $this->queue->useTube(self::TUBE)->put($job);
        }
    }

    public function log($log)
    {
        $pid = posix_getpid();
        echo "[x] {$pid} ---------- \e[32;40m {$log} \e[0m -----------", PHP_EOL;
    }
}