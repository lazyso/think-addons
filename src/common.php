<?php
/**
 * Author: 青舟网络
 * Email: qingzo@qq.com
 * Web:  www.qingzow.com
 * Time: 2022/3/22
 */

use think\facade\App;
use think\facade\Route;
use think\Loader;
use think\facade\Hook;
use think\facade\Config;
use think\facade\Cache;

define('ADDON_PATH',dirname(App::getAppPath()) . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR);

// 如果插件目录不存在则创建
if (! is_dir(ADDON_PATH)) {
    @mkdir(ADDON_PATH, 0777, true);
}
// 注册插件类的根命名空间
Loader::addNamespace('addons', ADDON_PATH);

// 闭包自动识别插件目录配置
Route::group('addons', function () {
    if (!isset($_SERVER['REQUEST_URI'])) {
        return 'error addons';
    }
    // 请求位置
    $path = ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ($ext) {
        $path = substr($path, 0, strlen($path) - (strlen($ext) + 1));
    }
    $pathinfo = explode('/', $path);
    // 路由地址
    if ($pathinfo[0] == 'addons' && isset($pathinfo[2])) {
        // 获取路由地址
        $module = $pathinfo[1];
        $controller = ucfirst($pathinfo[2]);
        $act = $pathinfo[3];

        $view_path = ADDON_PATH . $module . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        Config::set('template.view_path', $view_path);

        $s = "\\addons\\{$module}\\controller\\{$controller}@{$act}";
        Route::rule(':rule',$s);
    }
})->middleware(function ($request, \Closure $next) {
    // 路由地址
    $pathinfo = explode('/', $request->path());
    $request->setModule($pathinfo[1]);
    $request->setController($pathinfo[2]);
    $request->setAction($pathinfo[3]);
    return $next($request);
});

// 闭包自动识别插件目录配置
Hook::add('app_init', function () {
    // 获取开关
    $autoload = (bool)Config::get('addons.autoload', false);
    // 非正是返回
    if (!$autoload) {
        return;
    }
    // 当debug时不缓存配置
    $config = App::isDebug() ? [] : Cache::get('addons',[]) ;
    if(!is_array($config)){
        $config = [];
    }
    if (App::isDebug() || empty($config)) {
        // 读取插件目录及钩子列表
        $base = get_class_methods("\\qz\\Addons");
        // 读取插件目录中的php文件
        foreach (glob(ADDON_PATH . '*/*.php') as $addons_file) {
            // 格式化路径信息
            $info = pathinfo($addons_file);
            // 获取插件目录名
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            // xinsu -  修改

            // 找到插件入口文件 如果文件名 和目录名 相同 则表示这是入口文件
            if ($info['filename'] == ucfirst($name)) {
                // 先判断该插件是否是启用状态
                $infoSet = get_addons_info($name);
                if((string) $infoSet['status'] == '1'){
                    // 读取出所有公共方法
                    $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . $info['filename']);
                    // 跟插件基类方法做比对，得到差异结果
                    $hooks = array_diff($methods, $base);
                    // 循环将钩子方法写入配置中
                    foreach ($hooks as $hook) {
                        if (!isset($config['hooks'][$hook])) {
                            $config['hooks'][$hook] = [];
                        }
                        // 兼容手动配置项
                        if (is_string($config['hooks'][$hook])) {
                            $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                        }
                        if (!in_array($name, $config['hooks'][$hook])) {
                            $config['hooks'][$hook][] = $name;
                        }
                    }
                    Cache::set('initHook', $config['hooks']);
                    // 检测路由文件并进行注册
                    if(file_exists(ADDON_PATH.$name.DIRECTORY_SEPARATOR.'route.php')){
                        $file = require ADDON_PATH.$name.DIRECTORY_SEPARATOR.'route.php';
                        foreach ($file as $key=>$v){
                            $routeUrl = $key;
                            $routeInfo = explode('/',$v);
                            if(count($routeInfo) === 3){
                                $routeInfoUrl = "\\addons\\{$routeInfo[0]}\\controller\\".ucfirst($routeInfo[1]."@{$routeInfo[2]}");
                                   Route::rule($routeUrl,$routeInfoUrl)->middleware(function ($request, \Closure $next) {
                                    // 路由地址
                                    $route = explode('/', str_replace('\\','/',$request->routeInfo()['route']));
                                    $info = explode('@',$route[4]);
                                    $request->setModule($route[2]);
                                    $request->setController($info[0]);
                                    $request->setAction($info[1]);
                                    return $next($request);
                                });;
                            }

                        }
                    }

                }
            }

        }
    }
    Cache::set('addons', $config);
});

// 闭包初始化行为
Hook::add('action_begin', function () {
    // 获取系统配置
    $data = App::isDebug() ? [] : Cache::get('initHook', []);
    // 读取预设hook钩子
    $config = config('addons.');
    $addons = isset($config['hooks']) ? $config['hooks'] : [];
    if (App::isDebug() || empty($data)) {
        // 初始化钩子 - 配置文件设置的
        foreach ($addons as $key => $values) {
            if (is_string($values)) {
                $values = explode(',', $values);
            } else {
                $values = (array)$values;
            }
            $addons[$key] = array_filter(array_map('get_addons_class', $values));
            Hook::add($key, $addons[$key]);
        }
        $initHook = Cache::get('initHook',[]);
        if($initHook){
            foreach ($initHook as $key => $values) {
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array)$values;
                }
                $addons[$key] = array_filter(array_map('get_addons_class', $values));
                $initHook[$key] = $addons[$key];
                Hook::add($key, $addons[$key]);
            }
            $Hookinfo = array_merge($addons,$initHook);
            Cache::set('initHook', $Hookinfo);
        }

    } else {
        Hook::import($data, false);
    }
});



/**
 * 处理插件钩子
 * @param string $hook 钩子名称
 * @param mixed $params 传入参数
 * @return void
 */
function hook($hook, $params = [])
{
    $result = Hook::listen($hook, $params);
    if (is_array($result)) {
        foreach ($result as &$item) {
            if ($item instanceof \think\response\View) {
                $item = $item->getContent();
            }
        }
        $result = join(PHP_EOL, $result);
    }
    echo $result;
}

/**
 * 获取插件类的类名
 * @param $name 插件名
 * @param string $type 返回命名空间类型
 * @param string $class 当前类名
 * @return string
 */
if (!function_exists('get_addons_class')) {
    function get_addons_class($name, $type = 'hook', $class = null)
    {
        $name = Loader::parseName($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);
            foreach ($class as $key => $cls) {
                $class[$key] = Loader::parseName($cls, 1);
            }
            $class = implode('\\', $class);
        } else {
            $class = Loader::parseName(is_null($class) ? $name : $class, 1);
        }
        switch ($type) {
            case 'controller':
                $namespace = "\\addons\\" . $name . "\\controller\\" . $class;
                break;
            default:
                $namespace = "\\addons\\" . $name . "\\".ucfirst($name);
        }

        return class_exists($namespace) ? $namespace : '';
    }
}

/**
 * 获取插件类的配置文件数组
 * @param string $name 插件名
 * @return array
 */
if (!function_exists('get_addons_config')) {
    function get_addons_config($name, $parse = false)
    {
        static $_config = array();

        // 获取当前插件目录
        $addons_path = ADDON_PATH . $name . DIRECTORY_SEPARATOR;
        // 读取当前插件配置信息
        if (is_file($addons_path . 'config.php')) {
            $config_file = $addons_path . 'config.php';
        }

        if (isset($_config[$name])) {
            return $_config[$name];
        }

        $config = [];
        if (isset($config_file) && is_file($config_file)) {
            $temp_arr = include $config_file;
            if (is_array($temp_arr)) {
                if ($parse) {
                    foreach ($temp_arr as $key => $value) {
                        if ($value['type'] == 'group') {
                            foreach ($value['options'] as $gkey => $gvalue) {
                                foreach ($gvalue['options'] as $ikey => $ivalue) {
                                    $config[$ikey] = $ivalue['value'];
                                }
                            }
                        } else {
                            $config[$key] = $temp_arr[$key]['value'];
                        }
                    }
                } else {
                    $config = $temp_arr;
                }
            }
            unset($temp_arr);
        }
        $_config[$name] = $config;

        return $config;
    }
}

/**
 * 获取插件信息
 * @param $name
 * @return array
 */
if (!function_exists('get_addons_info')) {
    function get_addons_info($name)
    {
        $class = "\\addons\\{$name}\\".ucfirst($name);
        if (!class_exists($class)) {
            return [];
        }
        $addon = new $class();
        return $addon->getInfo();
    }
}

/**
 * 获取插件信息外部专用
 * @return array
 */
if (!function_exists('getInfoS')) {
    function getInfoS($name)
    {
        $info = [];
        $addons_path = ADDON_PATH . $name . DIRECTORY_SEPARATOR;
        $info_path = $addons_path . 'info.ini';
        if (is_file($info_path)) {
            $info = parse_ini_file($info_path);
        }
        return $info;
    }
}

/**
 * 插件显示内容里生成访问插件的url
 * @param $url
 * @param array $param
 * @param bool|string $suffix 生成的URL后缀
 * @param bool|string $domain 域名
 * @return bool|string
 */
if (!function_exists('addons_url')) {
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        if (empty($url)) {
            // 生成 url 模板变量
            $addons = request()->module();
            $controller = request()->controller();
            $controller = str_replace('/', '.', $controller);
            $action = request()->action();
        } else {
            $url = Loader::parseName($url, 1);
            $url = parse_url($url);
            $case = config('url_convert');
            $addons = $case ? Loader::parseName($url['scheme']) : $url['scheme'];
            $controller = $case ? Loader::parseName($url['host']) : $url['host'];
            $action = trim($case ? strtolower($url['path']) : $url['path'], '/');

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }
        return url("/addons/{$addons}/{$controller}/{$action}", $param, $suffix, $domain);
    }
}

