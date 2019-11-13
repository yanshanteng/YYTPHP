<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class YException extends Exception
{
    public function __construct()
    {
        $args = func_get_args();
        parent::__construct(serialize($args));
    }

    public function message()
    {
        $message = unserialize($this->getMessage());
        if (count($message) > 1) return $message;
        return $message[0];
    }
}