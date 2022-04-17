<?php
/**
 * Author: 青舟网络
 * Email: qingzo@qq.com
 * Web:  www.qingzow.com
 * Time: 2022/3/23
 */


namespace qz;

use think\App;
use think\facade\Config;
use think\facade\Request;

class Controller extends \think\Controller
{
    protected function fetch($template = '', $vars = [], $config = []){

        $Addons_system = \think\facade\App::getRootPath().'addons'.DIRECTORY_SEPARATOR;
        // 设置模板路径为插件目录下的view目录
        Config::set('template.view_path',$Addons_system);
        // 系统模板目录
        $AddonsPath = $Addons_system.strtolower(Request::module().DIRECTORY_SEPARATOR.'view'.DIRECTORY_SEPARATOR);
        // 判断用户传入的时文件名还是目录+文件
        $template = str_replace('/','\\',$template);
        $ist = explode('\\',$template);
        if(!file_exists($template)) {
            // 如果用户直接渲染了绝对路径
            if(count($ist) == 1){
                $template = $AddonsPath.Request::controller().DIRECTORY_SEPARATOR.$template.'.html';
            }
            if(count($ist) == 2){
                $template = $AddonsPath.$template.'.html';
            }
        }

        return parent::fetch($template, $vars, $config);
    }
}