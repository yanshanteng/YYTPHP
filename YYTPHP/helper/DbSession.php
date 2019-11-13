<?php
/**
 * 用数据库保存SESSION(基于YYTPHP)
 * 需要的字段 session_id[pk varchar(255)] session_expires[int(10)] session_data[text]
 * @param 数据库对象
 * eg:
    ini_set('session.save_handler', 'user');
    $DbSession = new DbSession(dbConfigFilename);
    session_set_save_handler(
        [$DbSession, 'open'],
        [$DbSession, 'close'],
        [$DbSession, 'read'],
        [$DbSession, 'write'],
        [$DbSession, 'destroy'],
        [$DbSession, 'gc']
    );
 */
class DbSession
{
    const TABLE = 'session'; //默认的表名

    private $_db;

    private $_lifeTime;

    public function __construct($dbConfig)
    {
        $this->_db = DB::$dbConfig(self::TABLE);
        $this->_lifeTime = get_cfg_var('session.gc_maxlifetime');
    }

    public function open()
    {
        return true;
    }

    public function read($sessID)
    {
        $where['session_id'] = $sessID;
        $where['session_expires'] = ['>', time()];
        $session = $this->_db->where($where)->field('session_data')->fetch();
        if ($session) return $session['session_data'];
        return '';
    }

    public function write($sessID, $sessData)
    {
        $newExp = time() + $this->_lifeTime;
        $session = $this->_db->where('session_id', $sessID)->fetch();
        $data['session_expires'] = $newExp;
        $data['session_data'] = $sessData;
        if ($session) {
            $this->_db->where('session_id', $sessID)->update($data);
            return true;
        } else {
            $data['session_id'] = $sessID;
            $this->_db->insert($data);
            return true;
        }
        return false;
    }

    public function destroy($sessID)
    {
        $del = $this->_db->where('session_id', $sessID)->delete();
        if ($del) return true;
        return false;
    }

    public function gc($sessMaxLifeTime)
    {
        $where['session_expires'] = ['<', time()];
        $this->_db->where($where)->delete();
        return true;
    }

    public function close()
    {
        return $this->gc(ini_get('session.gc_maxlifetime'));
    }
}