<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

abstract class Action extends Template
{
    protected $var = []; //提供一个默认的模板变量

    private $_call = [];

    public function __construct()
    {
        $this->var['template_url'] = Y::templateUrl();
    }

    private static function _get_called_method()
    {
        $backtrace = debug_backtrace();
        array_shift($backtrace);
        return $backtrace[1]['function'];
    }

    private static function _xmlEncode($data, $_xmlTag = true)
    {
        if (!is_array($data) || count($data) <= 0) return false;
        $xml = $_xmlTag ? '<xml>' : '';
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $xml .= '<'.$key.'>'.self::_xmlEncode($val, false).'</'.$key.'>';
            } else if (is_numeric($val)) {
                $xml .= '<'.$key.'>'.$val.'</'.$key.'>';
            } else {
                $xml .= '<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
            }
        }
        $xml .= $_xmlTag ? '</xml>' : '';
        return $xml;
    }

    protected function format($type)
    {
        $this->_call['format'] = $type;
        return $this;
    }

    protected function call()
    {
        $call = $this->_call;
        $this->_call = [];
        return $call;
    }

    /**
     * 输出模板
     * eg: $this->format('json')->display();
     */
    public function display($template = '')
    {
        $call = $this->call();
        $viewFormat = isset($call['format']) ? $call['format'] : Y::config('display_format');
        if (!$template) {
            $template = strtolower(substr(get_class($this), 0, -6));
            $action = self::_get_called_method();
            if ($action != 'index' && $template != '_empty') $template .= '/'.$action;
            if ($template == '_empty') $template = $action;
        }
        $this->var['template'] = $template;
        $this->assign('var', $this->var);
        switch (strtoupper($viewFormat)) {
            case 'JSON':
                $this->assign($this->var);
                $this->unAssign('var');
                echo json_encode($this->vars(), JSON_UNESCAPED_UNICODE + JSON_NUMERIC_CHECK);
            break;
            case 'XML':
                $this->assign($this->var);
                $this->unAssign('var');
                echo self::_xmlEncode($this->vars());
            break;
            default: parent::display($template);
        }
    }

    private function _info($type, $content = '', $jump = 1, $template = '')
    {
        if ($jump == 1) $jump = $_SERVER['HTTP_REFERER'];
        if ($jump == 2) $jump = 'javascript:history.back();';
        if (is_array($content)) {
            $data = $content;
        } else {
            if ($content) $data['content'] = $content;
        }
        $data['type'] = $type;
        if ($jump) $data['jump'] = $jump;
        if (!$template) $template = $type;
        $this->var = array_merge($this->var, $data);
        $this->display($template);
        exit();
    }

    protected function error($content = '', $jump = null, $template = '')
    {
        $this->_info('error', $content, $jump, $template);
    }

    protected function success($content = '', $jump = null, $template = '')
    {
        $this->_info('success', $content, $jump, $template);
    }

    protected function errorJson($content = '', $jump = null, $template = '')
    {
        $this->format('json')->error($content, $jump, $template);
    }

    protected function successJson($content = '', $jump = null, $template = '')
    {
        $this->format('json')->success($content, $jump, $template);
    }
}