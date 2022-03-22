<?php
/**
 * Author: 青舟网络
 * Email: qingzo@qq.com
 * Web:  www.qingzow.com
 * Time: 2022/3/23
 */


namespace sys;

use think\App;

class Controller extends \think\Controller
{
    protected $info;
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        if(empty($this->info)){
            $this->info = getInfoS('demo');
        }

    }

}