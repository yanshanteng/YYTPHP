<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class DbMysqli extends DB
{
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
        if (isset($this->config['db_table'])) {
            $this->config['db_table'] = '`'.$this->config['db_table'].'`';
        }
    }

    public function config($key = '')
    {
        if ($key) return $this->config[$key];
        return $this->config;
    }

    private static $_connect = [];

    private function _connect()
    {
        $connectId = $this->connectId;
        if (!$connectId) $connectId = $this->connectId();
        if (isset(self::$_connect[$connectId])) return self::$_connect[$connectId];
        if ($this->config['db_long_connect'] == true) {
            if (substr(PHP_VERSION, 0, 3) < 5.3) throw new YException(__METHOD__.' [PHP5.3以上才支持Mysqli长连接, 需将db_long_connect配置为false]', true);
        }
        $host = $this->config['db_long_connect'] == true ? 'p:'.$this->config['db_host'] : $this->config['db_host'];
        @self::$_connect[$connectId] = new Mysqli($host,
            $this->config['db_user'],
            $this->config['db_password'],
            $this->config['db_name'],
            $this->config['db_port']);
        if (self::$_connect[$connectId]->connect_errno) {
            throw new YException(__METHOD__.' [数据库连接失败: '.iconv('GBK', 'UTF-8', self::$_connect[$connectId]->connect_error).']', true);
        }
        self::$_connect[$connectId]->set_charset($this->config['db_charset']);
        return self::$_connect[$connectId];
    }

    public function escapeString($string)
    {
        $Mysqli = $this->_connect();
        return '\''.$Mysqli->real_escape_string($string).'\'';
    }

    public function query($sql, $method = '')
    {
        $startTime = microtime(true);
        $method = strtolower($method);
        $Mysqli = $this->_connect();
        if ($method == 'fetch' || $method == 'fetchall' || $method == 'query' || $method == 'count')
            $isSelect = true;

        if (isset($isSelect)) {
            $result = $stmt = $Mysqli->query($sql);
        } else {
            $stmt = $Mysqli->prepare($sql);
            if (!empty($stmt)) $result = $stmt->execute();
        }
        if (!$method) return $result;

        if (!$stmt) throw new YException(__METHOD__.' [SQL错误: '.$sql.']<br />错误提示: '.$Mysqli->error, true);

        $result = [];
        switch ($method) {
            case 'fetch':
                $result = $stmt->fetch_assoc();
                break;
            case 'fetchall':
                $result = $stmt->fetch_all(1);
                break;
            case 'insert':
                $result = $Mysqli->insert_id;
                break;
            case 'update':
            case 'delete':
                $result = $stmt->affected_rows;
                break;
        }
        self::$countQuery++;
        $stopTime = microtime(true);
        Y::debug('MYSQLI [用时<font color="red">'.round(($stopTime - $startTime), 4).'</font>秒]: '.$sql);
        return $result === null ? [] : $result;
    }

    public function fields($table = '')
    {
        $this->config['db_table'] = $table ? $table : $this->config['db_table'];
        if (!$this->config['db_table']) return;
        $cacheFile = Y::config('cache_path').'/db/'.$this->config['db_name'].'.'.$this->config['db_table'].'.php';
        $cacheFile = str_replace('`', '', $cacheFile);
        if (is_file($cacheFile)) {
            return unserialize(str_replace('<?php exit();//', '', file_get_contents($cacheFile)));
        }
        $startTime = microtime(true);
        $Mysqli = $this->_connect();
        $sql = 'DESC '.$this->config['db_table'];
        $query = $Mysqli->query($sql);
        if (!$query) {
            throw new YException(__METHOD__.' [获取表'.$this->config['db_table'].'字段失败: '.$Mysqli->error.']', true);
        }
        $fields = [];
        while ($row = $query->fetch_assoc()) $fields[] = $row['Field'];
        self::$countQuery++;
        $stopTime = microtime(true);
        Y::debug('MYSQLI [用时<font color="red">'.round(($stopTime - $startTime), 4).'</font>秒]: '.$sql, 2);
        Y::makeDir(dirname($cacheFile));
        file_put_contents($cacheFile, '<?php exit();//'.serialize($fields));
        return $fields;
    }

    public function beginTransaction()
    {
        $Mysqli = $this->_connect();
        $Mysqli->autocommit(false);
    }

    public function commit()
    {
        $Mysqli = $this->_connect();
        $Mysqli->commit();
        $Mysqli->autocommit(true);
    }

    public function rollBack()
    {
        $Mysqli = $this->_connect();
        $Mysqli->rollback();
        $Mysqli->autocommit(true);
    }

    public function lastInsertId()
    {
        $Mysqli = $this->_connect();
        return $Mysqli->insert_id;
    }

    public function version()
    {
        $Mysqli = $this->_connect();
        return $Mysqli->server_info;
    }

    public function close()
    {
        $Mysqli = $this->_connect();
        return $Mysqli->close();
    }
}