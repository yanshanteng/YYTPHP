<?php
/**
 *------------------------------------------------
 * Author: YYT[QQ:375776626]
 *------------------------------------------------
 */

abstract class DB
{
    private static $_config = [
        'db_driver'        => 'PDO',
        'db_table'         => '',
        //connect
        'db_type'          => 'mysql',
        'db_host'          => 'localhost',
        'db_port'          => 3306,
        'db_name'          => '',
        'db_user'          => '',
        'db_password'      => '',
        'db_charset'       => 'UTF8MB4',
        'db_long_connect'  => true,
    ];

    public static function __callStatic($configName, $table = '')
    {
        $dbConfig = Y::config('db');
        if (!isset($dbConfig[$configName])) {
            throw new YException('数据库配置有误');
        }
        $config = $dbConfig[$configName];
        $config['db_table'] = reset($table);
        self::$_config = array_merge(self::$_config, $config);
        $driver = 'Db'.ucwords(self::$_config['db_driver']);
        $flag = md5(serialize(self::$_config));
        static $instance = [];
        if (isset($instance[$flag])) return $instance[$flag];
        $instance[$flag] = new $driver(self::$_config);
        return $instance[$flag];
    }

    private $_call = [];

    protected function resetCall()
    {
        $call = $this->_call;
        unset($call['multi']);
        $this->_call = [];
        return $call;
    }

    public function __call($method, $args)
    {
        $method = strtolower($method);
        $methods = ['cache', 'join', 'field', 'unfield', 'group', 'having', 'order', 'limit', 'alias', 'fetchsql'];
        if (in_array($method, $methods)) {
            if ($method == 'join') {
                $this->_call['join'][] = $args;
            } else {
                $this->_call[$method] = $args;
            }
        } else {
            throw new YException(__METHOD__.'调用'.get_class($this).'::'.$method.'不存在');
        }
        return $this;
    }

    public function where($field, $op = null, $condition = null)
    {
        $args = func_get_args();
        array_shift($args);
        $this->_callWhere('AND', $field, $op, $condition, $args);
        return $this;
    }

    public function whereOr($field, $op = null, $condition = null)
    {
        $args = func_get_args();
        array_shift($args);
        $this->_callWhere('OR', $field, $op, $condition, $args);
        return $this;
    }

    private function _callWhere($logic, $field, $op, $condition, $param = [])
    {
        $logic = strtoupper($logic);
        if (!isset($this->_call['where'][$logic])) {
            $this->_call['where'][$logic] = [];
        }

        if (!is_array($field)) {
            $where[$field] = !is_array($condition) ? [$op, $condition] : $param;
            $this->_call['multi'][$logic][$field][] = $where[$field];
        } else {
            foreach ($field as $_field => $value) {
                $where[$_field] = $value;
                if (is_array($where[$_field]) && !is_array($where[$_field][1])) {
                    unset($where[$_field][2]);
                }
                $this->_call['multi'][$logic][$_field][] = $where[$_field];
            }
        }

        if (!is_array($field) && $this->_checkMultiField($field, $logic)) {
            $where[$field] = $this->_call['multi'][$logic][$field];
        } else if (is_array($field)) {
            foreach ($field as $key => $val) {
                if ($this->_checkMultiField($key, $logic)) {
                    $where[$key] = $this->_call['multi'][$logic][$key];
                }
            }
        }
        $this->_call['where'][$logic] = array_merge($this->_call['where'][$logic], $where);
    }

    private function _checkMultiField($field, $logic)
    {
        return isset($this->_call['multi'][$logic][$field]) && count($this->_call['multi'][$logic][$field]) > 1;
    }

    private function _parseWhereItem($logic, $field, $item)
    {
        if (is_array($item[0])) {
            foreach ($item as $k => $v) {
                $items[] = $this->_parseWhereItem($logic, $field, $item[$k]);
            }
            return '('.join(' '.$logic.' ', $items).')';
        }

        $param['op'] = isset($item[1]) ? strtoupper($item[0]) : $item[0];
        if ($param['op'] === 0) $param['op'] = (string)$param['op']; //强制转为字符，否则in_array判断0失效
        $param['condition'] = isset($item[1]) ? $item[1] : $item[0];

        if (is_array($param['condition'])) {
            $param['logic'] = isset($item[2]) ? strtoupper($item[2]) : 'AND';
        }
        $result = '';

        //IN
        if (in_array($param['op'], ['IN', 'NOT IN'])) {

            if (!is_array($param['condition'])) {
                $param['condition'] = explode(',', $param['condition']);
            }
            $param['condition'] = join(',', array_map([$this, 'escapeString'], $param['condition']));
            $param['condition'] = trim($param['condition'], ',');
            $result .= $field.' '.$param['op'].'('.$param['condition'].')';

        //LIKE
        } else if (in_array($param['op'], ['LIKE', 'NOT LIKE'])) {
            $conditions = is_array($param['condition']) ? $param['condition'] : [$param['condition']];

            foreach ($conditions as $condition) {
                preg_match_all('/\[(.*)\]/iD', $condition, $match);
                if (!empty($match[1])) {
                    $replace = str_replace('%', '/%', str_replace('_', '/_', $match[1][0]));
                    $condition = preg_replace("/\[(.*)\]/iD", $replace, $condition);
                    if (strpos($condition, '/')) $ESCAPE = true;
                }
                $result .= $field.' '.$param['op'].' '.$this->escapeString($condition);
                if (isset($param['logic'])) $result .= ' '.$param['logic'].' ';
            }
            if (isset($param['logic'])) $result = rtrim($result, $param['logic'].' ');
            if (isset($ESCAPE)) $result .= " ESCAPE '/'";
            $result = '('.$result.')';

        //运算符
        } else if (in_array($param['op'], ['<>', '>', '<', '=', '>=', '<=', '!='])) {
            $result .= $field.' '.$param['op'].' '.$this->escapeString($param['condition']);

        //原生表达
        } else if (strtoupper($param['op']) == 'EXP') {
            $result .= $field.' '.$param['condition'];
        } else {
            //NULL
            if (is_null($param['op'])) {
                $result .= $field.' IS NULL';
            } else if (in_array(strtoupper($param['op']), ['NULL', 'NOT NULL'])) {
                $result .= $field.' IS '.strtoupper($param['op']);
            } else {
                $result .= $field.' = '.$this->escapeString($param['op']);
            }
        }
        return $result;
    }

    protected function parse($call)
    {
        if (isset($call['where'])) {
            $result = '';
            foreach (['AND', 'OR'] as $logic) {
                if (isset($call['where'][$logic])) {
                    $items = [];
                    foreach ($call['where'][$logic] as $field => $val) {
                        if (!is_array($val)) $val = ['=', $val];
                        $items[] = $this->_parseWhereItem($logic, $field, $val);
                    }
                    $result .= ' '.$logic.' '.join(' '.$logic.' ', $items);
                }
                $result = ltrim($result, ' '.$logic.' ');
            }
            $call['where'] = 'WHERE '.$result;
        }

        /**
         * INNER JOIN: 等同于 JOIN（默认的JOIN类型）,如果表中有至少一个匹配，则返回行
         * LEFT JOIN: 即使右表中没有匹配，也从左表返回所有的行
         * RIGHT JOIN: 即使左表中没有匹配，也从右表返回所有的行
         * FULL JOIN: 只要其中一个表中存在匹配，就返回行
         */
        if (isset($call['join'])) {
            $result = '';
            foreach ($call['join'] as $join) {
                $field = $join[0];
                $on = $join[1];
                $type = 'INNER';
                if (isset($join[2])) {
                    $join[2] = strtoupper($join[2]);
                    if (in_array($join[2], ['LEFT', 'RIGHT', 'FULL'])) $type = $join[2];
                }
                $result .= $type.' JOIN '.$field.' ON '.$on.' ';
            }
            $call['join'] = trim($result);
        }

        if (isset($call['alias'])) {
            $call['alias'] = trim($call['alias'][0]);
        } else {
            $call['alias'] = '';
        }

        if (isset($call['field'])) {
            $callFields = $call['field'][0];
            if (!is_array($callFields)) $callFields = explode(',', $callFields);
            $call['field'] = join(',', $callFields);
        } else {
            $call['field'] = !empty($call['alias']) ? $call['alias'].'.*' : '*';
        }

        if (isset($call['unfield'])) {
            $call['unfield'] = trim($call['unfield'][0]);
        } else {
            $call['unfield'] = '';
        }

        if (isset($call['group'])) {
            $groupBy = $call['group'][0];
            $call['group'] = 'GROUP BY '.$groupBy;
        }

        if (isset($call['having'])) {
            $call['having'] = 'HAVING '.$call['having'][0];
        }

        if (isset($call['order'])) {
            $orderBy = trim($call['order'][0]);
            $orderBy = 'ORDER BY '.$orderBy;
            if (isset($call['order'][1])) {
                $orderBy .= ' '.strtoupper($call['order'][1]);
            }
            $call['order'] = $orderBy;
        }

        if (isset($call['limit'])) {
            if (isset($call['limit'][1])) {
                $result = 'LIMIT '.intval($call['limit'][0]);
                $result .= ', '.intval($call['limit'][1]);
            } else {
                $result = 'LIMIT '.trim($call['limit'][0]);
            }
            $call['limit'] = $result;
        }

        return $call;
    }

    private function _table($alias = '', $debug = true)
    {
        if ($debug) {
            $fields = $this->fields();
            if ($fields) Y::debug('表<b>'.self::$_config['db_name'].'.'.self::$_config['db_table'].'</b>结构: '.join(',', $fields), 2);
        }
        $table = '`'.self::$_config['db_table'].'`';
        if ($alias) $table .= ' '.trim($alias);
        return $table;
    }

    private function _cacheFile($sql, $expires, $filename = '')
    {
        $filename = trim($filename);
        if (!$filename) {
            $filename = md5(self::$_config['db_name'].'.'.str_replace('`', '', $this->_table('', false)));
        }
        if ($expires > 0) {
            $filename = 'temp_'.$filename;
        } else {
            $filename = 'long_'.$filename;
        }
        return Y::config('cache_path').'/db/'.$filename.'.php';
    }

    //假为到期
    private function _checkCache($cache)
    {
        if (!$cache) return false;
        if ($cache['expires_time'] == 0) return true;
        return $cache['expires_time'] > time();
    }

    private function _setCache($sql, $data, $expires, $filename = '')
    {
        $file = $this->_cacheFile($sql, $expires, $filename);
        $cache['expires_time'] = $expires > 0 ? time() + $expires : 0;
        $cache['data'] = $data;
        Y::makeDir(dirname($file));
        return file_put_contents($file, '<?php exit();//'.serialize($cache));
    }

    private function _readCache($sql, $expires, $filename = '')
    {
        $file = $this->_cacheFile($sql, $expires, $filename);
        if (is_file($file)) {
            return unserialize(str_replace('<?php exit();//', '', file_get_contents($file)));
        }
        return [];
    }

    //留空删除临时缓存(有过期时间的) *为所有
    public static function clearCache($filename = '')
    {
        if (!$filename) $filename = 'temp_*';
        $cachePath = Y::config('cache_path').'/db';
        $files = glob($cachePath.'/'.$filename.'.php');
        if (empty($files)) return;
        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $count++;
                unlink($file);
            }
        }
        return $count;
    }

    public function parseUnfield($unfield, $field, $alias)
    {
        if (!$unfield) return $field;

        $fields = explode(',', $field);
        $unfields = explode(',', $unfield);
        $resultFields = [];
        foreach ($fields as $val) {
            $check = !empty($alias) ? $alias.'.*' : '*';
            if ($val == $check) {
                $val = $this->fields();
                $val = $alias ? $alias.'.'.'`'.join('`,'.$alias.'.`', $val).'`' : '`'.join('`,`', $val).'`';
                $val = $this->parseUnfield($unfield, $val, $alias);
            }
            if (!in_array(str_replace('`', '', $val), $unfields)) {
                $resultFields[] = $val;
            }
        }
        return join(',', $resultFields);
    }

    private function _cache($call, $sql, $__FUNCTION__)
    {
        if (isset($call['cache'][0]) && is_numeric($call['cache'][0])) {
            $startTime = microtime(true);

            $expires = $call['cache'][0];
            $filename = !empty($call['cache'][1]) ? $call['cache'][1] : '';
            $cache = $this->_readCache($sql, $expires, $filename);
            if ($this->_checkCache($cache)) {
                $stopTime = microtime(true);
                $type = $expires > 0 ? '临时' : '永久';
                Y::debug($type.'缓存 [用时<font color="red">'.round(($stopTime - $startTime), 4).'</font>秒]: '.$sql);
                return $cache['data'];
            }
            $data = $this->query($sql, $__FUNCTION__);
            $this->_setCache($sql, $data, $expires, $filename);
            return $data;
        }
    }

    public function sql()
    {
        $call = $this->resetCall();
        return $this->createSql($call);
    }

    protected function createSql($call)
    {
        $call = $this->parse($call);
        $table = $this->_table($call['alias']);
        $field = $call['field'];
        $field = $this->parseUnfield($call['unfield'], $field, $call['alias']);
        $where = isset($call['where']) ? ' '.$call['where'] : '';
        $join = isset($call['join']) ? ' '.$call['join'] : '';
        $order = isset($call['order']) ? ' '.$call['order'] : '';
        $limit = isset($call['limit']) ? ' '.$call['limit'] : '';
        $group = isset($call['group']) ? ' '.$call['group'] : '';
        $having = isset($call['having']) ? ' '.$call['having'] : '';
        $sql = 'SELECT '.$field.' FROM '.$table.$join.$where;
        if ($group) {
            $_table = trim($call['alias']) ? $call['alias'] : $table;
            $sql = 'SELECT * FROM (SELECT '.$field.' FROM '.$table.$join.$where.$order.') '.$_table.$group;
        }
        $sql .= $having.$order.$limit;
        return $sql;
    }

    public function fetch($field = '')
    {
        if ($field) $this->_call['field'][0] = $field;
        $call = $this->resetCall();
        if (!isset($call['limit'])) $call['limit'] = [1];
        $sql = $this->createSql($call);
        if (!empty($call['fetchsql']) && $call['fetchsql'][0] == true) {
            return $sql;
        }
        $result = $this->_cache($call, $sql, __FUNCTION__);
        if (!$result) $result = $this->query($sql, __FUNCTION__);
        if ($field) return isset($result[$field]) ? $result[$field] : null;
        return $result;
    }

    public function fetchAll()
    {
        $call = $this->resetCall();
        $sql = $this->createSql($call);
        if (!empty($call['fetchsql']) && $call['fetchsql'][0] == true) {
            return $sql;
        }
        $cache = $this->_cache($call, $sql, __FUNCTION__);
        if ($cache) return $cache;
        return $this->query($sql, __FUNCTION__);
    }

    private function _poly($type, $polyField)
    {
        $call = $this->resetCall();
        $call = $this->parse($call);
        $table = $this->_table($call['alias']);
        $field = $call['field'];
        $field = $this->parseUnfield($call['unfield'], $field, $call['alias']);
        $where = isset($call['where']) ? ' '.$call['where'] : '';
        $join = isset($call['join']) ? ' '.$call['join'] : '';
        $group = isset($call['group']) ? ' '.$call['group'] : '';
        $having = isset($call['having']) ? ' '.$call['having'] : '';
        $sql = 'SELECT '.strtoupper($type).'('.$polyField.') FROM';
        if ($group) {
            $sql .= ' (SELECT '.$field.' FROM '.$table.$join.$where.$group.$having.') ';
            $sql .= 'YYTPHP_'.strtoupper($type);
        } else {
            $sql .= ' '.$table.$join.$where.$having;
        }
        if (!empty($call['fetchsql']) && $call['fetchsql'][0] == true) {
            return $sql;
        }
        $result = $this->_cache($call, $sql, 'fetch');
        if (!$result) $result = $this->query($sql, 'fetch');
        return reset($result);
    }

    public function count($field = '*')
    {
        return $this->_poly('count', $field);
    }

    public function max($field)
    {
        return $this->_poly('max', $field);
    }

    public function min($field)
    {
        return $this->_poly('min', $field);
    }

    public function avg($field)
    {
        return $this->_poly('avg', $field);
    }

    public function sum($field)
    {
        return $this->_poly('sum', $field);
    }

    public function filterData($data)
    {
        $result = [];
        if (is_array($data)) {
            $fields = $this->fields();
            foreach ($data as $field => $value) {
                if (in_array(strtolower($field), $fields)) $result[$field] = $value;
            }
        }
        return $result;
    }

    public function insert($data)
    {
        $call = $this->resetCall();
        $table = $this->_table();
        $data = $this->filterData($data);
        $fields = $values = [];
        foreach ($data as $field => $value) {
            $fields[] = '`'.$field.'`';
            if (is_null($value)) {
                $values[] = 'null';
            } else {
                $values[] = $this->escapeString($value);
            }
        }
        $field = $fields ? join(',', $fields) : '';
        $value = $values ? join(',', $values) : '';
        $sql = 'INSERT INTO '.$table.' ('.$field.') VALUES ('.$value.')';
        if (!empty($call['fetchsql']) && $call['fetchsql'][0] == true) {
            return $sql;
        }
        if (!$field) return false; //如果字段全部为空就直接返回
        return $this->query($sql, __FUNCTION__);
    }

    //$all = true 无条件情况更新全部 (防止误操作)
    public function update($data, $all = '')
    {
        $call = $this->resetCall();
        $call = $this->parse($call);
        $table = $this->_table($call['alias']);
        $where = isset($call['where']) ? ' '.$call['where'] : '';
        $order = isset($call['order']) ? ' '.$call['order'] : '';
        $limit = isset($call['limit']) ? ' '.$call['limit'] : '';
        $value = '';
        if (is_array($data)) {
            $data = $this->filterData($data);
            $values = [];
            foreach ($data as $field => $value) {
                if (is_null($value)) {
                    $values[] = '`'.$field.'` = null';
                } else {
                    $values[] = '`'.$field.'` = '.$this->escapeString($value);
                }
            }
            if ($values) $value = join(',', $values);
        } else {
            $value = $data;
        }
        $sql = 'UPDATE '.$table.' SET '.$value.$where.$order.$limit;
        if (!empty($call['fetchsql']) && $call['fetchsql'][0] == true) {
            return $sql;
        }
        if ($all !== true) {
            if (!$where && !$limit) return 0; //如果没有任何条件直接返回0
        }
        if (!$value) return 0;
        return $this->query($sql, __FUNCTION__);
    }

    //$all = true 无条件情况删除全部 (防止误操作)
    public function delete($all = '')
    {
        $call = $this->resetCall();
        $call = $this->parse($call);
        $table = $this->_table($call['alias']);
        $where = isset($call['where']) ? ' '.$call['where'] : '';
        $order = isset($call['order']) ? ' '.$call['order'] : '';
        $limit = isset($call['limit']) ? ' '.$call['limit'] : '';
        $sql = 'DELETE FROM '.$table.$where.$order.$limit;
        if (!empty($call['fetchsql']) && $call['fetchsql'][0] == true) {
            return $sql;
        }
        if ($all !== true) {
            if (!$where && !$limit) return 0; //如果没有任何条件直接返回0
        }
        return $this->query($sql, __FUNCTION__);
    }

    public function type()
    {
        return $this->config('db_type');
    }

    protected static $countQuery = 0;

    public static function countQuery()
    {
        return self::$countQuery;
    }

    protected $connectId;

    public function createConnect($connectId)
    {
        $this->connectId = $connectId;
    }

    protected function connectId()
    {
        $config = $this->config();
        unset(
            $config['db_driver'],
            $config['db_table']
        );
        return md5(serialize($config));
    }

    abstract public function config($key = '');
    abstract public function escapeString($string);
    abstract public function query($sql, $method = '');
    abstract public function fields();
    abstract public function beginTransaction();
    abstract public function commit();
    abstract public function rollBack();
    abstract public function lastInsertId();
    abstract public function version();
    abstract public function close();
}