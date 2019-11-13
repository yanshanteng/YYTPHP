<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class _EmptyAction extends Action
{
    public function _empty()
    {
        $this->assign('title', '非法操作');
        Y::header('404');
        $this->error('该页面不存在');
    }
}