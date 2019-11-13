<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

abstract class Method
{
    public static function strlen($str)
    {
        preg_match_all('/./us', $str, $match);
        return count($match['0']);
    }

    /**
     * 验证数据(使用异常来捕获)
     * @param 数据
     * @param 验证方法名
     * @param 验证规则
     * @param 错误信息
     * @throw
     */
    public static function check($value, $checkName, $rules, $error)
    {
        switch ($checkName) {
            case 'len':
                $value = trim($value);
            case 'len-trim':
                if (!strstr($rules, '-')) {
                    if (self::strlen($value) != $rules) throw new YException($error);
                } else {
                    list($min, $max) = explode('-', $rules);
                    if (!$max) {
                        if (self::strlen($value) < $rules) throw new YException($error);
                    } else if (!(self::strlen($value) >= $min && self::strlen($value) <= $max)) {
                        throw new YException($error);
                    }
                }
                break;
            case 'inc':
                if ($rules == '__html__') {
                    $rules = array('<', '>', '"', '&');
                    $value = html_entity_decode($value);
                }
                if (is_array($rules)) {
                    $errors = array();
                    foreach ($rules as $rule) {
                        if (stristr($value, $rule)) $errors[] = $rule;
                    }
                    if (!empty($errors)) throw new YException($error.': '.join(' ', $errors));
                } else if (stristr($value, $rules)) {
                    throw new YException($error.': '.$rules);
                }
                break;
            case 'eq':
                $value = trim($value);
                if (is_array($rules)) {
                    foreach ($rules as $rule) {
                        if ($value == $rule) throw new YException($error);
                    }
                } else if ($value == $rules) {
                    throw new YException($error);
                }
                break;
            case 'timeout':
                $resTime = time() - strtotime($value);
                if ($resTime > $rules) throw new YException($error);
                break;
            default:
                throw new YException('不支持该验证方式: '.$checkName);
        }
    }

    /**
     * 字符串高亮
     * @param string
     * @param string
     * @param string
     * @param boolean true为UBB模式
     * @return string
     */
    public static function highlight($content, $key, $color = 'red', $isUbb = '')
    {
        if (empty($key)) return $content;
        $kFi = substr($key, 0, 1);
        $kLen = strlen($key);
        $lLen = strlen($content);
        $con = '';
        for ($ln = 0; $ln < $lLen; $ln++) {
            $ls = substr($content, $ln, 1);
            if ($ls == '<') {
                while ($ls != '>') {
                    $con .= $ls;
                    $ln++;
                    $ls = substr($content, $ln, 1);
                }
                $con .= $ls;
            } else if (strtolower($ls) == strtolower($kFi)) {
                $lKey = substr($content, $ln, $kLen);
                if (strtolower($lKey) != strtolower($key)) {
                    $con .= $ls;
                } else {
                    $ln += $kLen -1;
                    $con .= !empty($isUbb) ? '[color='.$color.'][b]' : '<strong><font color="'.$color.'">';
                    $con .= $lKey;
                    $con .= !empty($isUbb) ? '[/b][/color]' : '</font></strong>';
                }
            } else {
                $con .= $ls;
            }
        }
        return $con;
    }

    /**
     * 过滤HTML代码
     * @param string
     * @param bool 是否去除空格换行符
     * @param string 保留的标签
     * @return string
     */
    public static function clearHtml($html, $clearSpaceRn = true, $allow = '')
    {
        $html = html_entity_decode($html);
        $html = strip_tags($html, $allow);
        if (!$clearSpaceRn) return $html;
        $html = trim($html);
        $html = str_replace('　', '', $html);
        $html = str_replace('&nbsp;', '', $html);
        $html = str_replace('&nbsp', '', $html);
        $html = preg_replace('/\s+/','', $html);
        $html = str_replace(PHP_EOL, '', $html);
        return $html;
    }

    /**
     * 还原HTML代码
     * @param string
     * @return string
     */
    public static function backHtml($html)
    {
        return html_entity_decode($html);
    }

    public static function backHtmlDeep($value)
    {
        if (is_array($value)) {
            $result = array();
            foreach ($value as $key => $val) {
                if (is_array($val)) {
                    $result[] = self::backHtmlDeep($val);
                } else {
                    $result[$key] = html_entity_decode($val);
                }

            }
            return $result;
        }
        return array();
    }

    /**
     * 二维数组排序
     * @param array
     * @param string 需要排序的键值
     * @param string ASC 正序 DESC 倒序
     * @return array
     */
    public static function multiArraySort($multiArray, $sortKey, $sort = 'DESC')
    {
        if (is_array($multiArray)) {
            foreach ($multiArray as $row) {
                if (is_array($row)) {
                    $keyArray[] = $row[$sortKey];
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
        $sort = strtolower($sort) == 'desc' ? 3 : 4;
        array_multisort($keyArray, $sort, $multiArray);
        return $multiArray;
    }

    /**
     * 格式化金额
     * @param float 金额
     * @param int 保留尾数
     * @return float
     */
    public static function formatMoney($money, $mantissa = 2)
    {
        $money = bcdiv($money, 1, $mantissa);
        if (floor($money) == $money) {
            $money = intval($money);
        } else {
            $money = floatval($money);
        }
        return $money;
    }

    public static function formatTime($time)
    {
        $time -= 1;
        if ($time > time()) {
            $t = $time - time();
            $suffix = '后';
        } else {
            $t = time() - $time;
            $suffix = '前';
        }
        $f = array(
            '31536000' => '年',
            '2592000' => '个月',
            '604800' => '星期',
            '86400' => '天',
            '3600' => '小时',
            '60' => '分钟',
            '1' => '秒'
        );
        foreach ($f as $k => $v) {
            if (0 != $c = floor($t / (int)$k)) {
                $m = floor($t % $k);
                foreach ($f as $x => $y) {
                    if (0 != $r = floor($m / (int)$x)) return $c.$v.$r.$y.$suffix;
                }
                return $c.$v.$suffix;
            }
        }
    }

    public static function formatByte($size, $dec = 2)
    {
        $a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $pos = 0;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, $dec).' '.$a[$pos];
    }

    public static function substr($str, $start = 0, $length, $charset = 'utf-8', $suffix = '...')
    {
        if (self::strlen($str) <= $length) return $str;
        if (function_exists('mb_substr')) {
            return mb_substr($str, $start, $length, $charset).$suffix;
        } else if (function_exists('iconv_substr')) {
            return iconv_substr($str, $start, $length, $charset).$suffix;
        }
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join('', array_slice($match['0'], $start, $length));
        return $slice.$suffix;
    }

    public static function delFile($file)
    {
        if (empty($file)) return false;
        if (@is_file($file)) return @unlink($file);
        $ret = true;
        if ($handle = @opendir($file)) {
            while ($filename = @readdir($handle)) {
                if ($filename == '.' || $filename == '..') continue;
                if (!self::delFile($file.'/'.$filename)) $ret = false;
            }
        } else {
            $ret = false;
        }
        @closedir($handle);
        if (file_exists($file) && !rmdir($file)) {
            $ret = false;
        }
        return $ret;
    }

    public static function delEmptyDir($path)
    {
        if (is_dir($path) && ($handle = opendir($path)) !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file != '.' && $file != '..') {
                    $curfile = $path.'/'.$file;
                    if (is_dir($curfile)) {
                        self::delEmptyDir($curfile);
                        if (count(scandir($curfile)) == 2) rmdir($curfile);
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * 输出类的方法以及注释(并不会输出逻辑)
     * @param string 类名
     * @return string
     */
    public static function displayClass($className)
    {
        if (is_string($className) && !class_exists($className)) return;

        $view = '<!DOCTYPE html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/><title>'.$className.'</title>';
        $view .= '<style type="text/css">body,h1{ margin:0; padding:0; } body{ background:#80ff80 }</style>';
        $view .= '</head><body>';
        $view .= '<h1 style="background:#9999cc;border-bottom:5px #666699 solid">'.$className.'</h1>';

        $Class = new ReflectionClass($className);

        $methodObjects = $Class->getMethods(ReflectionProperty::IS_PUBLIC);

        array_multisort($methodObjects);
        $view .='<div style="margin:20px;border:1px #666 solid; background:#a6fda6;padding:5px;">';
        foreach ($methodObjects as $methodObject) {
            $view .= '<p><a href="#'.$methodObject->name.'" style="color:blue">'.$methodObject->name.'</a></p>';
        }
        $view .='</div>';

        foreach ($methodObjects as $methodObject) {
            $view .= '<div id="'.$methodObject->name.'" style="margin:20px;border:1px #666 solid; background:#a6fda6">';

            $doc = $methodObject->getDocComment();
            if ($doc) $view .= '<pre style="color:green">&nbsp;&nbsp;&nbsp;&nbsp;'.$methodObject->getDocComment().'</pre>';
            $view .= '<h2>&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#800000">'.$methodObject->class.'</span>';
            $view .= '::<span style="color:blue">'.$methodObject->name.'</span>(';
            foreach ($methodObject->getParameters() as $parameter) {
                $view .= '<span style="color:blue">$</span><span style="color:#008080">'.$parameter->name.'</span>';
                if ($parameter->isOptional()) {
                    $view .= ' = <span style="color:#ff00ff">\''.$parameter->getDefaultValue().'\'</span>';
                }
                $view .= ', ';
            }
            $view = rtrim($view, ', ');
            $view .= ')</h2>';
            $view .= '</div>';
        }

        $view .= '</body></html>';
        echo $view;
    }

    /**
     * 从一个数组中提取重复的值
     * @param array
     * @return array
     */
    public static function fetchRepeatArray($array)
    {
        //获取去掉重复数据的数组
        $uniqueArr = array_unique($array);
        //获取重复数据的数组
        $repeatArr = array_diff_assoc($array, $uniqueArr);
        return $repeatArr;
    }

    public static function isMobile()
    {
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) return true;
        if (isset ($_SERVER['HTTP_VIA'])) {
            return stristr($_SERVER['HTTP_VIA'], 'wap') ? true : false;
        }
        if (isset ($_SERVER['HTTP_USER_AGENT'])) {
            $clientKeywords = array ('nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh', 'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo', 'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp', 'wap', 'mobile');
            if (preg_match('/('.implode('|', $clientKeywords).')/i', strtolower($_SERVER['HTTP_USER_AGENT']))) return true;
        }
        if (isset ($_SERVER['HTTP_ACCEPT'])) {
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) return true;
        }
        return false;
    }

    /**
     * 数组过滤首尾空白字符
     * @param array
     * @param mixed 不需要过滤的数组key, 多个传递数组
     * @return array
     */
    public static function trimDeep($value, $filter = '')
    {
        if (is_array($value)) {
            $result = array();
            foreach ($value as $key => $val) {
                if (is_array($val)) {
                    $result[] = self::trimDeep($val, $filter);
                } else {
                    if (is_array($filter)) {
                        $result[$key] = in_array($key, $filter) ? $val : trim($val);
                    } else {
                        $result[$key] = $key == $filter ? $val : trim($val);
                    }
                }

            }
            return $result;
        }
        return array();
    }

    /**
     * 将一个字符串部分字符用$re替代隐藏
     * @param string    $string   待处理的字符串
     * @param int       $start    规定在字符串的何处开始，
     *                            正数 - 在字符串的指定位置开始
     *                            负数 - 在从字符串结尾的指定位置开始
     *                            0 - 在字符串中的第一个字符处开始
     * @param int       $length   可选。规定要隐藏的字符串长度。默认是直到字符串的结尾。
     *                            正数 - 从 start 参数所在的位置隐藏
     *                            负数 - 从字符串末端隐藏
     * @param string    $re       替代符
     * @return string   处理后的字符串
     */
    public static function hideStr($string, $start = 0, $length = 0, $re = '*')
    {
        if (empty($string)) return false;
        $strarr = array();
        $mbStrlen = mb_strlen($string);
        while ($mbStrlen) {//循环把字符串变为数组
            $strarr[] = mb_substr($string, 0, 1, 'utf8');
            $string = mb_substr($string, 1, $mbStrlen, 'utf8');
            $mbStrlen = mb_strlen($string);
        }
        $strlen = count($strarr);
        $begin = $start >= 0 ? $start : ($strlen - abs($start));
        $end = $last = $strlen - 1;
        if ($length > 0) {
            $end = $begin + $length - 1;
        } else if ($length < 0) {
            $end -= abs($length);
        }
        for ($i = $begin; $i <= $end; $i++) {
            $strarr[$i] = $re;
        }
        if ($begin >= $end || $begin >= $last || $end > $last) return false;
        return implode('', $strarr);
    }

    //分割字符
    public static function split($string, $len = 1)
    {
        $start = 0;
        $strlen = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string, $start, $len);
            $string = mb_substr($string, $len, $strlen);
            $strlen = mb_strlen($string);
        }
        return $array;
    }

    public static function xmlToArray($xml)
    {
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }

    public static function arrayToXml($data, $_xmlTag = true)
    {
        if (!is_array($data) || count($data) <= 0) return false;
        $xml = $_xmlTag ? '<xml>' : '';
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $xml .= '<'.$key.'>'.self::arrayToXml($val, false).'</'.$key.'>';
            } else if (is_numeric($val)) {
                $xml .= '<'.$key.'>'.$val.'</'.$key.'>';
            } else {
                $xml .= '<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
            }
        }
        $xml .= $_xmlTag ? '</xml>' : '';
        return $xml;
    }

    public static function uniqueArray($array)
    {
        $array = array_filter($array);
        $array = array_unique($array);
        return $array;
    }
}