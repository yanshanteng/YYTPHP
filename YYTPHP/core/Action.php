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

        switch (strtoupper($viewFormat)) {
            case 'JSON':
                $this->assign($this->var);
                echo json_encode($this->vars(), JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;
            case 'XML':
                $this->assign($this->var);
                echo self::_xmlEncode($this->vars());
            break;
            default:
                $this->var['template'] = $template;
                $this->var['template_url'] = Y::templateUrl();
                $this->assign('var', $this->var);
                parent::display($template);
        }
    }

    private function _info($type, $content = '')
    {
        if (is_array($content)) {
            $data = $content;
        } else {
            if ($content) $data['content'] = $content;
        }
        $data['type'] = $type;
        $this->var = array_merge($this->var, $data);
        $this->display($type);
        exit();
    }

    protected function error($content = '')
    {
        $this->_info('error', $content);
    }

    protected function success($content = '')
    {
        $this->_info('success', $content);
    }

    protected function errorJson($content = '')
    {
        $this->format('json')->error($content);
    }

    protected function successJson($content = '')
    {
        $this->format('json')->success($content);
    }
}