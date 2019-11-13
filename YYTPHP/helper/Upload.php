<?php
/**
 *------------------------------------------------
 * Author YYT[QQ:375776626]
 *------------------------------------------------
 */

class Upload
{
    public $config = array('exts' => '*', 'save_path' => '', 'save_name' => '', 'max_size' => '');

    private static function _check($errorNum)
    {
        switch ($errorNum) {
            case 1:throw new YException('上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值');
            case 2:throw new YException('上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值');
            case 3:throw new YException('文件只有部分被上传');
            case 4:throw new YException('没有文件被上传');
            case 6:throw new YException('找不到临时文件夹');
            case 7:throw new YException('文件写入失败');
            default:throw new YException('未知上传错误！');
        }
    }

    public function check($file)
    {
        if (isset($file['error']) && $file['error'] != 0) {
            self::_check($file['error']);
        }
        if ($this->config['exts'] != '*') {
            $fileSuffix = self::_fileSuffix($file['name']);
            $exts = explode(',', $this->config['exts']);
            if (!in_array(strtolower($fileSuffix), $exts)) {
                throw new YException('不允许上传该文件 '.$fileSuffix);
            }

        }
        if ($this->config['max_size']) {
            if (isset($file['size']) && $file['size'] > $this->config['max_size']) {
                throw new YException('上传的文件大小超过设置'.self::_formatByte($this->config['max_size']).' 当前'.self::_formatByte($file['size']));
            }
        }
    }

    private static function _formatByte($size, $dec = 2)
    {
        $a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $pos = 0;
        while ($size >= 1024) {
            $size /= 1024;
            $pos++;
        }
        return round($size, $dec).' '.$a[$pos];
    }

    private static function _fileSuffix($fileName)
    {
        return substr(strrchr($fileName, '.'), 1, 10);
    }

    private static function _makeDir($dir, $mode = 0777)
    {
        if (!is_dir($dir)) {
            self::_makeDir(dirname($dir), $mode);
            return mkdir($dir, $mode);
        }
        return true;
    }

    public function save($file)
    {
        if (!$this->config['save_path']) throw new YException('未设置存放目录');
        if (!$this->config['save_name']) throw new YException('未设置存放名称');
        $this->check($file);

        self::_makeDir($this->config['save_path']);
        $saveName = $this->config['save_name'].'.'.self::_fileSuffix(strtolower($file['name']));
        move_uploaded_file($file['tmp_name'], $this->config['save_path'].'/'.$saveName);
        return array('path' => $this->config['save_path'].'/'.$saveName, 'name' => $file['name'], 'size' => $file['size'], 'type' => $file['type']);
    }

    public function saves($files)
    {
        if (!$this->config['save_path']) throw new YException('未设置存放目录');
        if (!$this->config['save_name']) throw new YException('未设置存放名称');
        if ($files) {
            if (is_array($files)) {
                $files = self::formatFiles($files);
                foreach ($files as $file) {
                    if (!empty($file['name'])) $isFile = true;
                }
                if (!isset($isFile)) throw new YException('没有文件被上传');

                foreach ($files as $file) {
                    $this->check($file);
                }

                self::_makeDir($this->config['save_path']);
                $result = array();
                foreach ($files as $key => $file) {
                    if (!empty($file['name'])) {
                        $saveName = $this->config['save_name'].$key.'.'.self::_fileSuffix($file['name']);
                        move_uploaded_file($file['tmp_name'], $this->config['save_path'].'/'.$saveName);
                        $result[] = array('path' => $this->config['save_path'].'/'.$saveName, 'name' => $file['name'], 'size' => $file['size'], 'type' => $file['type']);
                    }
                }
                return $result;
            }
        }
        return false;
    }

    public static function formatFiles($uploads)
    {
        if (is_array($uploads)) {
            foreach ($uploads['name'] as $key => $name) {
                $result[$key]['name'] = $name;
                $result[$key]['tmp_name'] = $uploads['tmp_name'][$key];
                $result[$key]['size'] = $uploads['size'][$key];
                $result[$key]['error'] = $uploads['error'][$key];
                $result[$key]['type'] = $uploads['type'][$key];
            }
            return $result;
        }
    }
}