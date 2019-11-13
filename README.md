# 当前版本为 1.7.0

### 入口文件

```php
//定义根目录
define('ROOT_PATH', __DIR__);
require '../YYTPHP/Y.php';
//注册类自动加载路径
//Y::regAutoLoad(ROOT_PATH.'/common', ROOT_PATH.'/common/model');

//加载数据库配置
//Y::config('db', require ROOT_PATH.'/config/db.php');

//(初始化: 页面gzip, debug信息, 时差, 提交的请求, 路由)
//在不需要加载控制器调用 
//Y::init(); 

//设置配置
$config['debug'] = true;
Y::config($config);

//运行控制器
//默认加载 IndexAction.php 不存在则 _EmptyAction.php
Y::run(ROOT_PATH.'/controller');

```

# controller/_EmptyAction.php 404


```php
class _EmptyAction extends Action
{
    public function _empty()
    {
        $this->display('404');
    }
}
```

### config/db.php 数据库配置

```php
return [
    //eg: DB::server(table)->fetch();
    'server' => [
        'db_driver'                 => 'mysqli',
        'db_type'                   => 'mysql',
        'db_host'                   => 'localhost',
        'db_name'                   => 'dbname',
        'db_user'                   => 'root',
        'db_password'               => 'root',
        'db_long_connect'           => false,
    ]
]
```

### controller/index 控制器


```php
class IndexAction extends CommonAction
{
    public function index()
    {
        //JSON格式输出 也可通过配置
        //Y::config('display_format', HTML | JSON| XML)
        $this->format('json')->display();
    }
}
```

### 数据库操作


```php
//简单查询
DB::server('member')->where('id', 1)->fetch();

//获取SQL
DB::server('member')->where('id', 1)->sql();
DB::server('member')->where('id', 1)->fetchSql(true)->fetch();

//连贯查询
DB::server('member')
    ->alias('m')
    ->join('member_data md', 'md.member_id = m.id')
    //支持INNER 默认 LEFT RIGHT FULL
    ->join('member_like ml', 'ml.member_id = m.id', 'LEFT')
    ->field('m.*,md.tel,ml.type')
    ->group('sex')
    ->where('id', 'NOT IN', [1,2,3])
    ->where('id', '>=', 1)
    ->where('id', '<=', 100)
    ->where('username', 'LIKE', '%username%')
     //[] 作用为转义通匹符号, 如: _ %
    ->whereOr('username', 'LIKE', ['%username%', '%[%]%'])
    ->limit(10, 20)
    ->order('m.id DESC')
    ->fetchAll();
    
//记录数
DB::server('member')->count('id');
//最大
DB::server('member')->max('id');
//最小
DB::server('member')->min('id');
//总和
DB::server('member')->sum('id');
//平均
DB::server('member')->avg('id');

```

### 模板语法


```php
//包含模板
{template="header"}

//包含文件
{include="common.php"}

//循环 $key 可省略
{loop $data $key $list}
{/loop}

//条件
{if $data}{elseif $var}{else}{/if}

//显示
{$var} {test($var)}

//原生
{php $var = 1}

```

### 一个带缓存的 controller/IndexAction.php


```php

class IndexAction extends Action
{
    public function _empty()
    {
        $this->assign('title', '非法操作');
        Y::header('404');
        $this->error('该页面不存在');
    }

    public function index()
    {
        //配置模板缓存路径
        Y::config('template_cache_path', ROOT_PATH.'/data/cache/template');
        //配置缓存名称
        Y::config('template_cache_name', 'index-1');
        //开启缓存
        Y::config('template_caching', true);

        if (!$this->isCache('index')) {
            echo '<h1>写入缓存</h1>';
            $this->assign('content', 'Hello World !');
            $this->assign('title', 'YYTPHP测试');
        }

        $this->display();
    }

    public function clear()
    {
        Y::config('template_cache_path', ROOT_PATH.'/data/cache/template');
        //删除缓存 参数格式可参照glob函数
        $this->clearCache('index-*');
        echo '<a href="'.Y::url().'">已删除</a>';
    }
}

```
---

- [x] core目录存放核心文件

- [x] helper目录存放常用类
