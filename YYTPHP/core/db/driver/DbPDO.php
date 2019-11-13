<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

class DbPDO extends DB
{
    private $_config;

    public function __construct($config)
    {
        $this->_config = $config;
        if (isset($this->_config['db_table'])) {
            $this->_config['db_table'] = '`'.$this->_config['db_table'].'`';
        }
    }

    public function config($key = '')
    {
        if ($key) return $this->_config[$key];
        return $this->_config;
    }

    private static $_connect = [];

    private function _connect()
    {
        $connectId = $this->connectId;
        if (!$connectId) $connectId = $this->connectId();
        if (isset(self::$_connect[$connectId])) return self::$_connect[$connectId];
        if (!$this->_config['db_type']) {
            throw new YException(__METHOD__.'[未定义数据库类型]');
        }
        $dsn = $this->_config['db_type'].':';
        if ($this->_config['db_type'] == 'sqlite') {
            $file = $this->_config['db_host'].'/'.$this->_config['db_name'];
            if (!is_file($file)) throw new YException(__METHOD__.'[sqlite数据库不存在: '.$file.']');
            $dsn .= $file;
        } else {
            $dsn .= 'host='.$this->_config['db_host'];
            $dsn .= ';dbname='.$this->_config['db_name'];
            $dsn .= ';port='.$this->_config['db_port'];
        }
        try{
            self::$_connect[$connectId] = new PDO($dsn,
                $this->_config['db_user'],
                $this->_config['db_password'],
                [PDO::ATTR_PERSISTENT => $this->_config['db_long_connect']]
            );
            self::$_connect[$connectId]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //让PDO抛出异常
            if ($this->_config['db_type'] == 'mysql') {
                self::$_connect[$connectId]->query('SET NAMES '.$this->_config['db_charset']);
            }
            return self::$_connect[$connectId];
        } catch (PDOException $e) {
            throw new YException(__METHOD__.'[数据库连接失败: '.$e->getMessage().']');
        }
    }

    public function escapeString($string)
    {
        $PDO = $this->_connect();
        return $PDO->quote($string);
    }

    public function query($sql, $method = '')
    {
        $startTime = microtime(true);
        $PDO = $this->_connect();
        $method = strtolower($method);
        try{
            $stmt = $PDO->prepare($sql);
            $result = $stmt->execute();
            if (!$method) return $result;
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $result = [];
            switch ($method) {
                case 'fetch':
                    $result = $stmt->fetch();
                    break;
                case 'fetchall':
                    $result = $stmt->fetchAll();
                    break;
                case 'insert':
                    $result = $PDO->lastInsertId();
                    break;
                case 'update':
                case 'delete':
                    $result = $stmt->rowCount();
                    break;
            }
            self::$countQuery++;
            $stopTime = microtime(true);
            Y::debug('PDO [用时<font color="red">'.round(($stopTime - $startTime), 4).'</font>秒]: '.$sql);
            return $result === false ? [] : $result;
        } catch (PDOException $e) {
            throw new YException(__METHOD__. '[SQL错误: '.$sql.']<br />错误提示: '.$e->getMessage());
        }
    }

    public function fields($table = '')
    {
        $this->_config['db_table'] = $table ? $table : $this->_config['db_table'];
        if (!$this->_config['db_table']) return;

        $cacheFile = Y::config('cache_path').'/db/'.$this->_config['db_name'].'.'.$this->_config['db_table'].'.php';
        $cacheFile = str_replace('`', '', $cacheFile);
        if (is_file($cacheFile)) return unserialize(str_replace('<?php exit();//', '', file_get_contents($cacheFile)));
        $startTime = microtime(true);
        $PDO = $this->_connect();
        try{
            switch ($this->_config['db_type']) {
                case 'mysql':
                    $sql = 'DESC '.$this->_config['db_table'];
                    $fieldName = 'Field';
                    break;
                case 'sqlite':
                    $sql = 'PRAGMA table_info('.$this->_config['db_table'].')';
                    $fieldName = 'name';
                    break;
                default:
                    throw new YException(__METHOD__.'PDO [暂不支持获取'.$this->_config['db_type'].'数据库表结构]');
            }
            $stmt = $PDO->prepare($sql);
            $stmt->execute();
            $fields = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fields[] = strtolower($row[$fieldName]);
            }
            self::$countQuery++;
            $stopTime = microtime(true);
            Y::debug('[PDO 用时<font color="red">'.round(($stopTime - $startTime), 4).'</font>秒]: '.$sql, 2);
            Y::makeDir(dirname($cacheFile));
            file_put_contents($cacheFile, '<?php exit();//'.serialize($fields));
            return $fields;
        } catch (PDOException $e) {
            throw new YException(__METHOD__.'PDO [获取表'.$this->_config['db_table'].'字段失败: '.$e->getMessage().']');
        }
    }

    public function beginTransaction()
    {
        $PDO = $this->_connect();
        $PDO->beginTransaction();
    }

    public function commit()
    {
        $PDO = $this->_connect();
        $PDO->commit();
    }

    function rollBack()
    {
        $PDO = $this->_connect();
        $PDO->rollBack();
    }

    public function lastInsertId()
    {
        $PDO = $this->_connect();
        return $PDO->lastInsertId();
    }

    public function version()
    {
        $PDO = $this->_connect();
        return $PDO->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    public function close()
    {
        self::$_connect[$connectId] = null;
        return true;
    }
}