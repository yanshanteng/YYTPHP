<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class Crypt
{
    //des-ecb 密钥需要8位
    //aes-128-ecb 密钥需要16位
    //aes-256-ecb 密钥需要32位
    //更多方法 openssl_get_cipher_methods() 查看
    public static function encode($data, $key, $method = 'aes-256-ecb', $iv = 0)
    {
        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        return openssl_encrypt($data, $method, $key, $iv);
    }

    public static function decode($cryptStr, $key, $method = 'aes-256-ecb', $iv = 0)
    {
        $data = openssl_decrypt($cryptStr, $method, $key, $iv);
        return json_decode($data, true);
    }
}