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
        if ($args[1]) Y::debug('<font color="red">'.$args[0].'</font>');
    }

    public function message()
    {
        $message = unserialize($this->getMessage());
        if (count($message) > 1) return $message;
        return $message[0];
    }
}