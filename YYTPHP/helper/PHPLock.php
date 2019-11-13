<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class PHPlock
{
    private $_path; //文件锁存放路径

    private $_fp; //文件句柄

    private $_key; //锁标识

    private $_eaccelerator; //是否存在eaccelerator标志

    const HASH_NUM = 100; //锁粒度, 设置越大粒度越小

    /**
     * 构造函数
     * @param string 锁文件存放路径 结尾不用加/
     */
    public function __construct($path)
    {
        //判断是否存在eAccelerator, 启用了eAccelerator之后可以进行内存锁提高效率
        $this->_eaccelerator = function_exists('eaccelerator_lock');
        $this->_path = $path;
    }

    public function setKey($key)
    {
        $this->_key = $key;
    }

    private function _filePath()
    {
        return $this->_path.'/'.($this->_mycrc32($this->_key) % self::HASH_NUM).'.txt';
    }

    private function _mycrc32($string)
    {
        $crc = abs(crc32($string));
        if ($crc & 0x80000000) {
            $crc ^= 0xffffffff;
            $crc += 1;
        }
        return $crc;
    }

    private function _check()
    {
        if (!$this->_key) throw new YException('未设置锁标识');
    }

    public function lock()
    {
        $this->_check();

        if ($this->_eaccelerator) return eaccelerator_lock($this->_key);

        $file = $this->_filePath();
        $this->_fp = fopen($file, 'w+');
        if ($this->_fp === false) return;
        return flock($this->_fp, LOCK_EX);
    }

    public function unlock()
    {
        $this->_check();

        if ($this->_eaccelerator) return eaccelerator_unlock($this->_key);

        if ($this->_fp !== false) {
            flock($this->_fp, LOCK_UN);
            clearstatcache();
        }
        fclose($this->_fp);
    }
}