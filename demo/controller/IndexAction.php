<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

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

    public function phpinfo()
    {
        phpinfo();
    }
}