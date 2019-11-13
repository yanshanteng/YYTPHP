<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

return [
    'debug'                     => false,

    'db' => [
       /**
        * 数据库配置
        * eg:DB::dbname('tablename')->fetch();
        */
        /*'dbname' => [
           'db_driver'                 => 'PDO',
           'db_type'                   => 'mysql',
           'db_host'                   => 'localhost',
           'db_port'                   => 3306,
           'db_name'                   => 'dbname',
           'db_user'                   => '',
           'db_password'               => '',
           'db_charset'                => 'UTF8MB4',
           'db_long_connect'           => false,
        ]*/
    ],

    'template_path'                 => ROOT_PATH.'/template',
    'template_compile_path'         => ROOT_PATH.'/data/temp',
    'template_suffix'               => '.html',
    'template_cache_path'           => '',
    'template_cache_name'           => '',
    'template_caching'              => false,
    'template_left_delimiter'       => '{',
    'template_right_delimiter'      => '}',

    'display_format'            => 'html', //输出格式 默认html (json xml)

    'cache_path'                => ROOT_PATH.'/data/cache',
    'log_path'                  => ROOT_PATH.'/data/log',

    'url_space'                 => '/',
    'url_suffix'                => '.html',
    'url_rewrite'               => false,
    'url_base_path'             => '',

    'route_start'               => 0, //调用Y::route时开始值

    'session_prefix'            => 'YYTPHP_',

    'cookie_expire'             => 12, //过期时效(小时)
    'cookie_path'               => '/',
    'cookie_domain'             => '',
    'cookie_secure'             => false, //是否通过安全的 HTTPS 连接来传输 cookie

    'timezone'                  => 8, //时差
    'gzip'                      => true, //页面压缩(如页面不显示请关闭)
];