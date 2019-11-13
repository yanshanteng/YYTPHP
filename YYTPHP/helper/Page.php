<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class Page
{
    public $url;

    public $firstUrl;

    public $urlType = 'href';

    private $_config = array('count' => '', 'list_num' => '', 'current' => '', 'url_current' => '');

    private $_countPage;

    private $_pageKey = 'p';

    /**
     * 分页构造函数
     * @param int 数据统计
     * @param int 每页显示
     * @param int 当前页标识
     * @param int 当前页
     */
    public function __construct($count, $listNum = 20, $current = '', $pageKey = 'p')
    {
        $this->_pageKey = $pageKey;
        if (!$current) $current = isset($_GET[$this->_pageKey]) && $_GET[$this->_pageKey] >= 1 ? $_GET[$this->_pageKey] : 1;

        $this->_config['count'] = intval($count);
        $this->_config['list_num'] = intval($listNum);
        $this->_config['current'] = intval($current);
        $this->_config['url_current'] = intval($current);

        $this->_countPage = ceil($this->_config['count'] / $this->_config['list_num']);
        if ($this->_config['current'] < 1) $this->_config['current'] = 1;
        if ($this->_config['current'] > $this->_countPage) $this->_config['current'] = $this->_countPage;
    }

    public function isFull()
    {
        if ($this->_config['url_current'] > $this->_countPage) return true;
        return false;
    }

    public function hasNext()
    {
        return $this->_config['url_current'] < $this->_countPage;
    }

    public function countPage()
    {
        return $this->_countPage;
    }

    public function realCurrentPage()
    {
        return $this->_config['url_current'];
    }

    public function currentPage()
    {
        return $this->_config['current'];
    }

    public function formatArray($array)
    {
        $start = ($this->_config['current'] - 1) * $this->_config['list_num'];
        if ($start < 0) $start = 0;
        if ($start > $this->_config['count']) {
            $start = $this->_config['count'] - intval(($this->_config['list_num'] / 2) + 1);
        }
        $result = array();
        for ($i = $start; $i< ($start + $this->_config['list_num']); $i++) {
            if (!empty($array[$i])) array_push($result, $array[$i]);
        }
        return $result;
    }

    public function limit()
    {
        $start = ($this->_config['current'] - 1) * $this->_config['list_num'];
        if ($start < 1) $start = 0;
        if ($start > $this->_config['count']) $start = $this->_config['count'] - $this->_config['list_num'];
        return $start.','.$this->_config['list_num'];
    }

    public function parseUrl($page = '')
    {
        if ($page == 1 && $this->firstUrl) return $this->firstUrl;
        if (!$this->url) {
            if (substr($_SERVER['QUERY_STRING'], 0, 1) == $this->_pageKey) {
                $space = '?';
            } else {
                $space = empty($_SERVER['QUERY_STRING']) ? '?' : '&';
            }
            $this->url = Y::domain().$_SERVER['REQUEST_URI'];
            $this->url = str_replace('&amp;'.$this->_pageKey.'='.$this->_config['current'], '', $this->url);
            $this->url = str_replace($space.$this->_pageKey.'='.$this->_config['current'], '', $this->url);
            $this->url .= $space.$this->_pageKey.'=';
            $this->url .= '$page';
        }
        return str_replace('$page', $page, $this->url);
    }

    public function all($config = array())
    {
        if (empty($config['prev_name'])) $config['prev_name'] = '上页';
        if (empty($config['next_name'])) $config['next_name'] = '下页';
        if (empty($config['first_name'])) $config['first_name'] = '首页';
        if (empty($config['last_name'])) $config['last_name'] = '尾页';

        $prev = $this->_config['current'] - 1;
        if ($prev < 1) $prev = 1;

        if ($this->_config['current'] > 1) {
            $result['prev'] = ' <a '.$this->urlType.'="'.$this->parseUrl($prev).'">'.$config['prev_name'].'</a> ';
        } else {
            $result['prev'] = ' <span class="on">'.$config['prev_name'].'</span> ';
        }

        $next = $this->_config['current'] + 1;
        if ($next > $this->_countPage) $next = $this->_countPage;

        if ($this->_config['current'] < $this->_countPage) {
            $result['next'] = ' <a '.$this->urlType.'="'.$this->parseUrl($next).'">'.$config['next_name'].'</a> ';
        } else {
            $result['next'] = ' <span class="on">'.$config['next_name'].'</span> ';
        }
        if ($this->_config['current'] > 1) {
            $result['first'] = ' <a '.$this->urlType.'="'.$this->parseUrl(1).'">'.$config['first_name'].'</a> ';
        } else {
            $result['first'] = ' <span class="on">'.$config['first_name'].'</span> ';
        }

        if ($this->_config['current'] < $this->_countPage) {
            $result['last'] = ' <a '.$this->urlType.'="'.$this->parseUrl($this->_countPage).'">'.$config['last_name'].'</a> ';
        } else {
            $result['last'] = ' <span class="on">'.$config['last_name'].'</span> ';
        }

        $result['jump'] = ' <span class="select"><select onchange="window.location=\''.$this->parseUrl('\'+this.value+\'').'\';">';
        for ($p = 1; $p <= $this->_countPage; $p++) {
            $result['jump'] .= '<option value="'.$p.'"';
            if ($this->_config['current'] == $p) $result['jump'] .= ' selected';
            $result['jump'] .= '>'.$p.' / '.$this->_countPage.'</option>';
        }
        $result['jump'] .= '</select><i class="iconfont icon-down"></i></span> ';

        $result['num'] = '';
        for ($i = $this->_config['current'] - 5; $i <= $this->_config['current'] + 5 && $i <= $this->_countPage; $i++) {
            if ($i > 0) {
                if ($i == $this->_config['current']) {
                    $result['num'] .= ' <span class="on">'.$i.'</span> ';
                } else {
                    $result['num'] .= ' <a '.$this->urlType.'="'.$this->parseUrl($i).'">'.$i.'</a> ';
                }
            }
        }
        $result['list_num'] = $this->_config['list_num'];
        $result['count_page'] = $this->_countPage;
        $result['count'] = $this->_config['count'];
        $result['current'] = $this->_config['current'];
        return $result;
    }

    public function view($type = 1, $showJump = true)
    {
        $result = '';
        if ($type == '1') {
            $config['first_name'] = '&laquo;';
            $config['last_name'] = '&raquo;';
            $all = $this->all($config);
            $result = $all['num'];
            if ($all['current'] > 6) $result = $all['first'].'...'.$result;
            if ($all['current'] + 5 < $all['count_page']) $result .= '...'.$all['last'];
            $result .= ' <span>每页'.$all['list_num'].'条 共'.$all['count'].'条</span>';
            if ($showJump) $result .= $all['jump'];
        }
        if ($type == '2') {
            $all = $this->all();
            $result = $all['first'];
            $result .= $all['prev'];
            if ($showJump) $result .= $all['jump'];
            $result .= $all['next'];
            $result .= $all['last'];
        }
        return $result;
    }
}