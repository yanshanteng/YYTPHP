<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class Crypt
{
    /**
     * 加密
     * @param string 加密字符串
     * @param bool 是否加入超时时间戳
     * @param string 密钥(8位数)
     * @return string
     */
    public static function encode($str, $isTimeout = true, $key = '_YYTPHP_')
    {
        if ($isTimeout) $str = time().$str;
        $crypt = openssl_encrypt($str, 'DES-ECB', $key);
        return strtoupper(bin2hex($crypt));
    }

    /**
     * 解密
     * @param string 加密过的字符串
     * @param int 设置超时间隔(0为不限)
     * @param string 密钥(8位数)
     * @return string
     */
    public static function decode($str, $timeout = 60, $key = '_YYTPHP_')
    {
        if (strlen($key) != 8) return false;
        $str = pack('H*', strtoupper($str));
        $str = openssl_decrypt($str, 'DES-ECB', $key);
        if (!$str) return false;
        if ($timeout > 0) {
            if ((time() - substr($str, 0, 10)) > $timeout) return false;
            $str = substr($str, 10);
        }
        return $str;
    }
}