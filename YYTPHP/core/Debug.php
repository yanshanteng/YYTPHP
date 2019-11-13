<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class Debug
{
    private static $_vars = [
        'include_files'	=> [],
        'infos'         => [],
    ];

    //用法：set_error_handler(['Debug', 'catcher']);
    public static function catcher($errno, $errstr, $errfile, $errline)
    {
        $systemInfos = [
            E_WARNING       => '运行时警告',
            E_NOTICE        => '运行时提醒',
            E_STRICT        => '编码标准化警告',
            E_USER_ERROR    => '自定义错误',
            E_USER_WARNING  => '自定义警告',
            E_USER_NOTICE   => '自定义提醒',
            'Unkown'        => '未知错误'
        ];

        if (!isset($systemInfos[$errno])) $errno = 'Unkown';
        $color = $errno == E_NOTICE || $errno == E_USER_NOTICE ? '#000088' : 'red';
        $info = '<font color="'.$color.'">';
        $info .= '<b>'.$systemInfos[$errno].'</b>[在文件 '.$errfile.' 中,第 '.$errline.' 行]:';
        $info .= $errstr;
        $info .= '</font>';
        self::add($info);
    }

    /**
     * 添加一条Debug信息
     * @param string 信息
     * @param int 类型(1:包含文件 2:SQL 默认: 系统信息)
     */
    public static function add($info, $type = 0)
    {
        switch ($type) {
            case 1:
                self::$_vars['include_files'][] = $info;
                break;
            default:
                self::$_vars['infos'][] = $info;
        }
    }

    public static function display()
    {
        echo '<div style="clear:both;text-align:left;font-size:11px;color:#888;width:95%;margin:10px;padding:10px;background:#F5F5F5;border:1px dotted #778855;position:relative;z-index:100">';
        echo '<div style="float:left;width:100%;"><span style="float:left;"><b>PHP版本</b>('.PHP_VERSION.') <b>运行耗时</b>('.Y::runtime().' 秒) <b>内存占用</b>('.Y::runMemory().') <b>查询数据</b>('.DB::countQuery().') <b>页面请求</b> '.$_SERVER['REQUEST_URI'].'</span><span onclick="this.parentNode.parentNode.style.display=\'none\'" style="cursor:pointer;float:right;width:35px;background:#500;border:1px solid #555;color:white">关闭X</span></div><br>';
        echo '<ul style="margin:0px;padding:0 10px 0 10px;list-style:none">';
        if (count(self::$_vars['include_files'])) {
            echo '<li><b>［包含文件］</b></li>';
            foreach (self::$_vars['include_files'] as $file)
                echo '<li style="padding-left:10px;">'.$file.'</li>';

        }
        if (count(self::$_vars['infos']) || session_id()) {
            echo '<li><b>［系统信息］</b></li>';
            if (session_id())
                echo '<li style="padding-left:10px;">session: 已开启 '.session_id().'</li>';
            foreach (self::$_vars['infos'] as $info)
                echo '<li style="padding-left:10px;">'.$info.'</li>';

        }
        echo '</ul>';
        echo '</div>';
    }
}