<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class Template
{
    private $_template;

    private $_vars = [];

    public function assign($vars, $value = '')
    {
        if (is_array($vars)) {
            $this->_vars = array_merge($this->_vars, $vars);
        } else {
            $this->_vars[$vars] = $value;
        }
    }

    public function vars()
    {
        return $this->_vars;
    }

    public static function templateFile($template)
    {
        return Y::config('template_path').'/'.$template.Y::config('template_suffix');
    }

    public static function compileFile($template)
    {
        return Y::config('template_compile_path').'/'.$template.'.compile';
    }

    private static function _compile($template)
    {
        $templateFile = self::templateFile($template);
        $compileFile = self::compileFile($template);
        if (!is_file($compileFile) || @filemtime($templateFile) > @filemtime($compileFile)) {
            if (is_file($templateFile)) {
                $content = file_get_contents($templateFile);
                $content = self::parse($content);
                Y::makeDir(dirname($compileFile));
                file_put_contents($compileFile, $content);
            } else {
                throw new YException(__METHOD__.' [找不到模板文件: '.$templateFile.']');
            }
        }
        return $compileFile;
    }

    public static function parse($content)
    {
        $ld = Y::config('template_left_delimiter');
        $rd = Y::config('template_right_delimiter');
        $content = preg_replace('/<head>/i', "<head>\r\n<meta name=\"generator\" content=\"YYTPHP\" />", $content);
        //模板包含模板
        $content = preg_replace('/'.$ld."template=(\'|\"?)(.+)\\1".$rd.'/i', "<?php include self::_compile('\\2');?>", $content);
        //模板包含文件
        $content = preg_replace('/'.$ld."include=(\'|\"?)(.+)\\1".$rd.'/i', "<?php include Y::config('template_path').'/\\2';?>", $content);
        //PHP标签
        $content = preg_replace('/'.$ld."php\s+(.+)".$rd.'/', "<?php \\1?>", $content);
        //echo变量
        $content = preg_replace('/'.$ld."(\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)".$rd.'/', "<?php if(isset(\\1)) echo \\1;?>", $content);
        //echo常量
        $content = preg_replace('/'.$ld."([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)".$rd.'/s', "<?php if(defined('\\1')) echo \\1;?>", $content);
        //echo函数
        $content = preg_replace('/'.$ld."([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff:]*\(([^{}]*)\))".$rd.'/', "<?php echo \\1;?>", $content);
        //if
        $content = preg_replace('/'.$ld."if\s+(.+?)".$rd.'/', "<?php if(\\1) { ?>", $content);
        //else
        $content = preg_replace('/'.$ld."else".$rd.'/', "<?php } else { ?>", $content);
        //elseif
        $content = preg_replace('/'.$ld."elseif\s+(.+?)".$rd.'/', "<?php } elseif (\\1) { ?>", $content);
        //end if
        $content = preg_replace('/'.$ld."\/if".$rd.'/', "<?php } ?>", $content);
        //foreach
        $content = preg_replace('/'.$ld."loop\s+(\S+)\s+(\S+)".$rd.'/', "<?php if(isset(\\1) && is_array(\\1)) foreach(\\1 AS \\2) { ?>", $content);
        $content = preg_replace('/'.$ld."loop\s+(\S+)\s+(\S+)\s+(\S+)".$rd.'/', "<?php if(is_array(\\1)) foreach(\\1 AS \\2 => \\3) { ?>", $content);
        $content = preg_replace('/'.$ld."\/loop".$rd.'/', "<?php } ?>", $content);
        //数组引号
        $content = preg_replace_callback('/'.$ld."(\\$[a-zA-Z0-9_\[\]\'\"\$\x7f-\xff]+)".$rd.'/s', ['self', '_quote'], $content);
        return $content;
    }

    private static function _quote($matches)
    {
        return self::quote('<?php if(isset('.$matches[1].')) echo '.$matches[1].';?>');
    }

    public static function quote($string)
    {
        return str_replace("\\\"", "\"", preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $string));
    }

    public function fetch($__template__)
    {
        $__file__ = self::_compile($__template__);
        if ($this->_vars) extract($this->_vars);
        ob_start();
        if (is_file($__file__)) {
            include $__file__;
            $content = ob_get_clean();
            return $content;
        }
        throw new YException(__METHOD__.' [找不到模板编译文件: '.$__file__.']');
    }

    public static function cacheFile()
    {
        return Y::config('template_cache_path').'/'.Y::config('template_cache_name').'.html';
    }

    private static function _checkCache($cacheFile)
    {
        if (!is_file($cacheFile)) return false;
        return true;
    }

    private $_readCache = false;

    public function isCache($template)
    {
        if (!Y::config('template_cache_path')) throw new YException(__METHOD__.' [缓存路径未设置]');
        if (!Y::config('template_cache_name')) throw new YException(__METHOD__.' [缓存名称未设置]');

        $this->_template = $template;
        if (!Y::config('template_caching')) {
            Y::debug('<font color="green">'.__METHOD__.' [页面缓存未开启 Y::config(\'template_caching\') 需要为真]</font>');
            return;
        }
        $this->_readCache = true;

        $templateFile = self::templateFile($template);
        $cacheFile = self::cacheFile();
        if (!self::_checkCache($cacheFile)) return false;
        return true;
    }

    public function display($template = '')
    {
        if ($this->_template) $template = $this->_template;
        if ($this->_readCache) {
            $templateFile = self::templateFile($template);
            $cacheFile = self::cacheFile();
            if (!self::_checkCache($cacheFile)) {
                $content = $this->fetch($template);
                Y::makeDir(dirname($cacheFile));
                file_put_contents($cacheFile, $content);
            } else {
                $content = file_get_contents($cacheFile);
            }
            echo $content;
            return;
        }
        echo $this->fetch($template);
    }

    public function clearCache($search = '')
    {
        if ($search) {
            $files = glob(Y::config('template_cache_path').'/'.$search.'.html');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) unlink($file);
                }
                return;
            }
        }
        if (is_file(self::cacheFile())) unlink(self::cacheFile());
    }
}