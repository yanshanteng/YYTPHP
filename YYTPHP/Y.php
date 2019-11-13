<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */


if (PHP_VERSION < '5.4') exit('PHP_VERSION >= 5.4');

//记录运行时间与内存消耗
define('BEGIN_TIME', microtime(true));
define('BEGIN_MEMORY', memory_get_usage());

//设置页面编码
Y::header('charset', 'utf-8');

//关闭错误提示
error_reporting(E_ALL ^ E_NOTICE);

//打开输出控制缓冲
ob_start();

//定义根路径
defined('ROOT_PATH') OR define('ROOT_PATH', dirname(__DIR__));

//定义根URL路径
defined('ROOT_URL') OR define('ROOT_URL', Y::rootUrl());

//框架路径
define('YYTPHP_PATH', __DIR__);

//定义域名
define('DOMAIN', Y::domain());

//定义时间戳
define('TIME', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time());

//加载默认的配置
Y::config(require YYTPHP_PATH.'/core/config.php');

//注册类自动加载路径
$autoload = [
    YYTPHP_PATH,
    YYTPHP_PATH.'/core',
    YYTPHP_PATH.'/core/db',
    YYTPHP_PATH.'/core/db/driver',
    YYTPHP_PATH.'/helper'
];
Y::regAutoload($autoload);

abstract class Y
{
    const VERSION = '1.7.0';

    /**
     * 初始化: 页面gzip, debug信息, 时差, 提交的请求, 路由
     * 在不需要加载控制器时调用
     */
    public static function init()
    {
        if (self::$_config['gzip'] && !ob_get_contents()) ob_start('ob_gzhandler');
        if (self::$_config['debug']) set_error_handler(['Debug', 'catcher']);
        if (self::$_config['debug']) register_shutdown_function(['Debug', 'display']);
        date_default_timezone_set('Etc/GMT-'.self::$_config['timezone']);
        self::initRequests();
        self::initRoute();
    }

    /**
     * 分发控制器文件
     * @param string 控制器路径
     */
    public static function run($path)
    {
        self::init();
        $classRoute = self::route(1);
        $className = trim($classRoute) ? trim($classRoute) : 'Index';
        $className = ucfirst($className).'Action';
        $actionRoute = self::route(2);
        $action = trim($actionRoute) ? $actionRoute : 'index';
        $file = $path.'/'.$className.'.php';
        try{
            if (!is_file($file)) {
                self::debug(__METHOD__.' [无法加载控制器文件: '.$file.']');
                $className = '_EmptyAction';
                $file = $path.'/'.$className.'.php';
                self::debug(__METHOD__.' [自动加载控制器文件: '.$file.']');
                if (!is_file($file)) {
                    if (self::$_config['debug']) {
                        throw new YException(__METHOD__.' [无法加载控制器文件: '.$file.']');
                    }
                    self::header('404');
                    return;
                }
                $action = trim($classRoute) ? trim($classRoute) : 'index';
            }
            //注册类自动加载路径
            self::regAutoload($path);
            $Controller = new $className();
            $filters = array_merge(get_class_methods('Template'), get_class_methods('Action'));
            $filters = array_unique($filters);
            if (!is_callable([$Controller, $action]) || in_array($action, $filters)) {
                self::debug(__METHOD__.' [加载失败: '.get_class($Controller).'::'.$action.']');
                $action = '_empty';
                self::debug(__METHOD__.' [自动调用: '.get_class($Controller).'::'.$action.']');
                if (!is_callable([$Controller, $action])) {
                    if (self::$_config['debug']) {
                        throw new YException(__METHOD__.' [加载失败: '.get_class($Controller).'::'.$action.']');
                    }
                    self::header('404');
                    return;
                }
            }
            $Controller->$action();
        } catch (YException $e) {
            if (self::$_config['debug']) {
                Debug::add('<font color="red">'.$e->message().'</font>');
            } else {
                self::header('404');
                return;
            }
        }
    }

    /**
     * 初始化路由
     */
    public static function initRoute()
    {
        $url = !empty($_GET['r']) ? trim($_GET['r']) : '';
        $path = preg_replace('/'.self::$_config['url_suffix'].'$/', '', $url);
        //iis6 path is GBK
        if (isset($_SERVER['SERVER_SOFTWARE']) && stristr($_SERVER['SERVER_SOFTWARE'], 'IIS')) {
            if (mb_detect_encoding($path) == 'GBK') $path = mb_convert_encoding($path, 'UTF-8', 'GBK');
        }
        $paths = explode(self::$_config['url_space'], $path);
        $order = 1;
        foreach ($paths as $value) {
            if (trim($value)) {
                $_GET['_URL_'][$order] = $value;
                $order++;
            }
        }
        //处理$_SERVER['REQUEST_URI']差异
        if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) { //IIS7 + Rewrite Module
            $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
        } else if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { //IIS6 + ISAPI Rewite
            $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
        }
    }

    /**
     * 获取路由值
     * @param int URL顺序
     * @param mixed 设置一个值
     * @return string
     */
    public static function route($order, $value = '')
    {
        $order += self::$_config['route_start'];
        if ($value) {
            $_GET['_URL_'][$order] = $value;
            return;
        }
        if (isset($_GET['_URL_'][$order])) return $_GET['_URL_'][$order];
    }

    /**
     * 获取URL地址
     * 使用:作为/替换符 eg: Y::url('admin:user/add')
     * @param string 使用@作为当前目录替代符
     * @param mixed 如果为null则读取配置
     * @return string
     */
    public static function url($args = '', $basePath = null)
    {
        $url = DOMAIN.ROOT_URL;
        if (is_null($basePath)) {
            if (self::$_config['url_base_path']) $url .= '/'.self::$_config['url_base_path'];
        } else {
            if ($basePath) $url .= '/'.$basePath;
        }
        if (!$args) return $url;
        $url .= '/';
        if (stristr($args, ':')) {
            if (stristr($args, '@')) {
                $_SERVER['PHP_SELF'] = strtolower($_SERVER['PHP_SELF']);
                $current = ltrim(dirname(str_replace(ROOT_URL, '', $_SERVER['PHP_SELF'])), '/');
                if ($current == '\\' || $current == '/') $current = '';
                $args = str_replace('@', $current, $args);
            }
            $baseUrl = explode(':', $args);
            $args = array_pop($baseUrl);
            $baseUrl = join('/', $baseUrl).'/';
            $url .= $baseUrl;
        }
        if (!self::$_config['url_rewrite']) {
            $phpSelf = basename($_SERVER['SCRIPT_FILENAME']);
            if ($phpSelf != 'index.php') $url .= $phpSelf;
            $url .= '?r=';
        }
        $args = explode('/', $args);
        $result = '';
        if (!empty($args[0])) {
            foreach ($args as $value) $result .= self::$_config['url_space'].$value;
            return $url.substr($result, 1).self::$_config['url_suffix'];
        } else {
            return rtrim($url, '/').$result;
        }
    }

    private static $_config = [];
    /**
     * 读取或者设置配置(无参数存在为读取所有配置)
     * @param string 配置名(只存在该参数时为读取)
     * @param mixed 配置值(该参数存在时为设置)
     * @return mixed
     */
    public static function config($key = null, $value = null)
    {
        if (is_null($key)) return self::$_config;
        if (is_string($key)) {
            if (is_null($value)) return isset(self::$_config[$key]) ? self::$_config[$key] : null;
            self::$_config[$key] = $value;
        }
        if (is_array($key)) self::$_config = array_merge(self::$_config, $key);
    }

    private static $_autoload = [];
    /**
     * 注册类自动加载目录
     * @param string 类文件目录(可传入多个)
     */
    public static function regAutoLoad()
    {
        if (empty(self::$_autoload)) spl_autoload_register(['self', '_autoLoad']);
        $args = func_get_args();
        if ($args) {
            foreach ($args as $dir) {
                if (is_array($dir)) {
                    self::$_autoload = array_merge(self::$_autoload, $dir);
                } else {
                    self::$_autoload[] = $dir;
                }
            }
        }
        self::$_autoload = array_unique(self::$_autoload);
    }

    private static function _autoload($class)
    {
        $load = false;
        foreach (self::$_autoload as $path) {
            $file = realpath($path.'/'.basename($class).'.php');
            if (is_file($file) && (preg_match('/^[A-Z]+$/', substr(basename($file), 0, 1)) || $class == '_EmptyAction')) {
                require $file;
                $file = str_replace(YYTPHP_PATH, '[YYTPHP]', $file);
                $file = str_replace(ROOT_PATH.DIRECTORY_SEPARATOR, '', $file);
                Debug::add('<b>'.$file.'</b>', 1);
                $load = true;
            }
        }
        if ($load === false) {
            echo '<p>类文件'.$file.'不存在</p>';
            debug_print_backtrace();
            echo '<p>搜索路径: </p>';
            echo '<p>'.join('<br />', self::$_autoload).'</p>';
            exit();
        }
    }

    /**
     * 设置、获取、删除SESSION
     * @param string
     * @param mixed
     * @return mixed
     */
    public static function session($name, $value = '')
    {
        if (!session_id()) session_start();
        $prefix = self::$_config['session_prefix'];
        $sessionId = $prefix ? md5($prefix).'_'.$name : $name;
        if (is_null($value)) {
            unset($_SESSION[$sessionId]);
        } else {
            if ($value === '') {
                if (isset($_SESSION[$sessionId])) return $_SESSION[$sessionId];
            } else {
                $session = $_SESSION;
                @session_regenerate_id(true);
                if (!session_id()) session_start();
                $_SESSION = $session;
                $_SESSION[$sessionId] = $value;
                return $_SESSION[$sessionId];
            }
        }
    }

    /**
     * 设置/获取COOKIE
     * @param string
     * @param mixed
     * @return mixed
     */
    public static function cookie($name, $value = '')
    {
        if (is_null($value)) {
            if (isset($_COOKIE[$name])) {
                setcookie($name, null, TIME - 1,
                    self::$_config['cookie_path'],
                    self::$_config['cookie_domain'],
                    self::$_config['cookie_secure']);
                unset($_COOKIE[$name]);
            }
        } else {
            if ($value == '') {
                if (isset($_COOKIE[$name])) {
                    return unserialize(html_entity_decode($_COOKIE[$name]));
                }
            } else {
                $value = serialize($value);
                $expire = TIME + (3600 * self::$_config['cookie_expire']);
                setcookie($name, $value, $expire,
                    self::$_config['cookie_path'],
                    self::$_config['cookie_domain'],
                    self::$_config['cookie_secure']);
                $_COOKIE[$name] = $value;
                return unserialize($value);
            }
        }
    }

    /**
     * 添加一条debug信息
     * @param string
     * @param int 1为包含文件的信息
     */
    public static function debug($message, $type = 0)
    {
        if (self::$_config['debug']) Debug::add($message, $type);
    }

    /**
     * 获取根URL
     * @return string
     */
    public static function rootUrl()
    {
        if (isset($_SERVER['PWD'])) $_SERVER['DOCUMENT_ROOT'] = $_SERVER['PWD'];
        if (!isset($_SERVER['DOCUMENT_ROOT'])) {
            if (isset($_SERVER['SCRIPT_FILENAME'])) {
                $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
            }
        }
        if (empty($_SERVER['DOCUMENT_ROOT']) && isset($_SERVER['PATH_TRANSLATED'])) {
            $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
        }
        $result = str_replace(str_replace('\\', '/', strtolower($_SERVER['DOCUMENT_ROOT'])), '', str_replace('\\', '/', strtolower(ROOT_PATH)));
        return $result ? '/'.ltrim($result, '/') : '';
    }

    /**
     * 获取模板URL
     * @return string
     */
    public static function templateUrl()
    {
        return str_replace(ROOT_PATH, ROOT_URL, self::$_config['template_path']);
    }

    /**
     * 判断请求是否为AJAX
     * @return bool
     */
    public static function isAjax()
    {
        if (!empty($_GET['ajax'])) return true;
        if (isset($_GET['ajax']) && !$_GET['ajax']) return false;
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            if ('xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH']))
                return true;
        }
        return false;
    }

    /**
     * 页面跳转
     * @param string URL地址
     * @param boolean 是否跳出iframe
     */
    public static function jump($url, $isTop = false)
    {
        if ($isTop) {
            exit('<script>window.top.location.href=\''.$url.'\'</script>');
        }
        if (self::isAjax()) {
            exit('<script>window.location.href=\''.$url.'\'</script>');
        }
        if (!headers_sent()) {
            header('Location:'.$url);
        } else {
            echo '<meta http-equiv=\'Refresh\' content=\'0;URL='.$url.'\'>';
        }
        exit();
    }

    /**
     * 获取运行耗时
     * @return string
     */
    public static function runtime()
    {
        $stopTime = microtime(true);
        return number_format(($stopTime - BEGIN_TIME), 4);
    }

    /**
     * 获取运行消耗内存
     * @return string
     */
    public static function runMemory()
    {
        $format = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pos = 0;
        $stopMemory = memory_get_usage();
        $size = $stopMemory - BEGIN_MEMORY;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, 2).' '.$format[$pos];
    }

    /**
     * 打印结果
     * 可接收多个不同类型的参数
     * @return string
     */
    public static function dump()
    {
        $args = func_get_args();
        echo '<pre>';
        foreach ($args as $arg) {
            if (is_array($arg)) {
                print_r($arg);
                echo '<br>';
            } else if (is_string($arg)) {
                echo $arg.'<br>';
            } else {
                var_dump($arg);
                echo '<br>';
            }
        }
        echo '</pre>';
    }

    /**
     * 获取域名
     * @return string
     */
    public static function domain()
    {
        $protocol = (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) ? 'https://' : 'http://';
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        } else if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } else {
            if (isset($_SERVER['SERVER_PORT'])) {
                $port = ':'.$_SERVER['SERVER_PORT'];
                if ((':80' == $port && 'http://' == $protocol) || (':443' == $port && 'https://' == $protocol))
                    $port = '';
            } else {
                $port = '';
            }
            if (isset($_SERVER['SERVER_NAME'])) {
                $host = $_SERVER['SERVER_NAME'].$port;
            } else if (isset($_SERVER['SERVER_ADDR'])) {
                $host = $_SERVER['SERVER_ADDR'].$port;
            }
        }
        return $protocol.$host;
    }

    /**
     * 获取IP
     * @return string
     */
    public static function realIp()
    {
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } else if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } else if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match ('/[\d\.]{7,15}/', $ip, $matches) ? $matches[0] : '';
    }

    /**
     * 递归创建目录
     * @param string 目录路径
     * @param int 目录权限
     * @return bool
     */
    public static function makeDir($dir, $mode = 0777)
    {
        if (!is_dir($dir)) {
            self::makeDir(dirname($dir), $mode);
            return mkdir($dir, $mode);
        }
        return true;
    }

    /**
     * 初始化请求(过滤系统自动添加的反斜杠，转义HTML标签)
     */
    public static function initRequests()
    {
        if (get_magic_quotes_gpc()) {
            if (!empty($_GET)) $_GET = self::_stripslashesDeep($_GET);
            if (!empty($_POST)) $_POST = self::_stripslashesDeep($_POST);
            if (!empty($_REQUEST)) $_REQUEST = self::_stripslashesDeep($_REQUEST);
            if (!empty($_SESSION)) $_SESSION = self::_stripslashesDeep($_SESSION);
            if (!empty($_COOKIE)) $_COOKIE = self::_stripslashesDeep($_COOKIE);
        }
        if (!empty($_GET)) $_GET = self::_htmlspecialcharsDeep($_GET);
        if (!empty($_POST)) $_POST = self::_htmlspecialcharsDeep($_POST);
        if (!empty($_REQUEST)) $_REQUEST = self::_htmlspecialcharsDeep($_REQUEST);
        if (!empty($_SESSION)) $_SESSION = self::_htmlspecialcharsDeep($_SESSION);
        if (!empty($_COOKIE)) $_COOKIE = self::_htmlspecialcharsDeep($_COOKIE);
    }

    private static function _stripslashesDeep($value)
    {
        return is_array($value) ? array_map(['self', '_stripslashesDeep'], $value) : stripslashes($value);
    }

    private static function _htmlspecialcharsDeep($value)
    {
        return is_array($value) ? array_map(['self', '_htmlspecialcharsDeep'], $value) : htmlspecialchars($value);
    }

    /**
     * 发送header头
     * @param string 类型 eg:Y::header('404');
     * @param string 值 eg:Y::header('charset', 'utf-8');
     */
    public static function header($type, $value = '')
    {
        switch ($type) {
            case '403': header('HTTP/1.1 403 Forbidden'); break; //禁止访问
            case '404': header('HTTP/1.1 404 Not Found'); break; //页面不存在
            case '500': header('HTTP/1.1 500 Internal Server Error'); break; //服务器错误
            case 'charset': header('Content-type:text/html; charset='.$value); break; //设置编码
            case 'download': //下载 eg: Y::header('download', 'game.zip');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.$value.'"');
                header('Content-Transfer-Encoding: binary');
                break;
            case 'cache': //缓存页面(单位:小时) eg: Y::header('cache', 24);
                $value = intval($value) * 3600;
                header('Cache-Control:max-age='.$value.', must-revalidate');
                header('Last-Modified:'.gmdate('D, d M Y H:i:s').'GMT');
                header('Expires:'.gmdate('D, d M Y H:i:s', time() + $value).'GMT');
                break;
            case 'no-cache': //页面禁止缓存
                header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Pragma: no-cache');
                break;
            default: header($type);
        }
    }

    public static function log($message, $filename = '')
    {
        self::makeDir(self::$_config['log_path']);
        $log = '['.date('Y-m-d H:i:s').'] '.$message."\n";
        $filename = trim($filename) ? iconv('UTF-8', 'GB2312//IGNORE', $filename) : date('Y-m-d');
        if (!@error_log($log, 3, self::$_config['log_path'].'/'.$filename.'.log')) {
            throw new YException(__METHOD__.' [日志写入失败，请检查'.self::$_config['log_path'].' log: '.$message.']');
        }
    }
}