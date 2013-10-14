<?php

class BCache extends BClass
{
    protected $_backends = array();
    protected $_backendStatus = array();
    protected $_defaultBackend;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BCache
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function __construct()
    {
        foreach (array('File','Shmop','Apc','Memcache','Db') as $type) {
            $this->addBackend($type, 'BCache_Backend_'.$type);
        }
    }

    public function addBackend($type, $backend)
    {
        $type = strtolower($type);
        if (is_string($backend)) {
            if (!class_exists($backend)) {
                throw new BException('Invalid cache backend class name: '.$backend.' ('.$type.')');
            }
            $backend = $backend::i();
        }
        if (!is_object($backend)) {
            throw new BException('Invalid backend for type: '.$type);
        }
        if (!$backend instanceof BCache_Backend_Interface) {
            throw new BException('Invalid cache backend class interface: '.$type);
        }
        $this->_backends[$type] = $backend;
        return $this;
    }

    public function getBackend($type=null)
    {
        if (is_null($type)) { // type not specified
            if (empty($this->_defaultBackend)) { // no default backend yet
                $minRank = 1000;
                $fastest = null;
                foreach ($this->_backends as $t => $backend) { // find fastest backend from available
                    $info = $backend->info();
                    if (empty($info['available'])) {
                        continue;
                    }
                    if ($info['rank'] < $minRank) {
                        $minRank = $info['rank'];
                        $fastest = $t;
                    }
                }
                $this->_defaultBackend = $fastest;
            }
            $type = $this->_defaultBackend;
        }
        $type = strtolower($type);
        $backend = $this->_backends[$type];
        if (empty($this->_backendStatus[$type])) {
            $info = $backend->info();
            if (empty($info['available'])) {
                throw new BException('Cache backend is not available: '.$type);
            }
            $config = (array)BConfig::i()->get('cache/'.$type);
            $backend->init($config);
            $this->_backendsStatus[$type] = true;
        }
        return $this->_backends[$type];
    }

    public function load($key)
    {
        return $this->getBackend()->load($key);
    }

    public function loadMany($pattern)
    {
        return $this->getBackend()->loadMany($pattern);
    }

    public function save($key, $data, $ttl=null)
    {
        return $this->getBackend()->save($key, $data, $ttl);
    }

    public function delete($key)
    {
        return $this->getBackend()->delete($key);
    }

    public function gc()
    {
        return $this->getBackend()->gc();
    }
}

interface BCache_Backend_Interface
{
    public function info();

    public function init($config = array());

    public function load($key);

    public function save($key, $data, $ttl = null);

    public function delete($key);

    public function loadMany($pattern);

    public function deleteMany($pattern);

    public function gc();
}

class BCache_Backend_File extends BClass implements BCache_Backend_Interface
{
    protected $_config = array();

    public function info()
    {
        return array('available' => true, 'rank' => 70);
    }

    public function init($config = array())
    {
        if (empty($config['dir'])) {
            $config['dir'] = BConfig::i()->get('fs/cache_dir');
        }
        if (!is_writable($config['dir'])) {
            $config['dir'] = sys_get_temp_dir().'/fulleron/'.md5(__DIR__).'/cache';
        }
        if (empty($config['default_ttl'])) {
            $config['default_ttl'] = 3600;
        }
        $this->_config = $config;
        return true;
    }

    protected function _filename($key)
    {
        $md5 = md5($key);
        return $this->_config['dir'].'/'.substr($md5, 0, 2).'/'.BUtil::simplifyString($key).'.'.substr($md5, 0, 10).'.dat';
    }

    public function load($key)
    {
        $filename = $this->_filename($key);
        if (!file_exists($filename)) {
            return null;
        }
        $fp = fopen($filename, 'r');
        $meta = @unserialize(fgets($fp, 1024));
        if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()) {
            fclose($fp);
            @unlink($filename);
            return null;
        }
        for ($data = ''; $chunk = fread($fp, 4096); $data .= $chunk);
        fclose($fp);
        $data = unserialize($data);
        return $data;

    }

    public function save($key, $data, $ttl = null)
    {
        $filename = $this->_filename($key);
        $dir = dirname($filename);
        BUtil::ensureDir($dir);
        $meta = array(
            'ts' => time(),
            'ttl' => !is_null($ttl) ? $ttl : $this->_config['default_ttl'],
            'key' => $key,
        );
        file_put_contents($filename, serialize($meta)."\n".serialize($data));
        return true;
    }

    public function delete($key)
    {
        $filename = $this->_filename($key);
        if (!file_exists($filename)) {
            return false;
        }
        @unlink($filename);
        return true;
    }

    /**
    * Load many items found by pattern
    *
    * @todo implement regexp pattern
    *
    * @param mixed $pattern
    */
    public function loadMany($pattern)
    {
        $files = glob($this->_config['dir'].'/*/*'.BUtil::simplifyString($pattern).'*');
        if (!$files) {
            return array();
        }
        $result = array();
        foreach ($files as $filename) {
            $fp = fopen($filename, 'r');
            $meta = unserialize(fgets($fp, 1024));
            if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()) {
                fclose($fp);
                @unlink($filename);
                continue;
            }
            if (strpos($meta['key'], $pattern)!==false) { // TODO: regexp search without iterating all files
                for ($data = ''; $chunk = fread($fp, 4096); $data .= $chunk);
                $result[$meta['key']] = $data;
            }
            fclose($fp);
        }
        return $result;
    }

    public function deleteMany($pattern)
    {
        if ($pattern===true || $pattern===false) { // true: remove ALL cache, false: remove EXPIRED cache
            $files = glob($this->_config['dir'].'/*/*');
        } else {
            $files = glob($this->_config['dir'].'/*/*'.BUtil::simplifyString($pattern).'*');
        }
        if (!$files) {
            return false;
        }
        $result = array();
        foreach ($files as $filename) {
            if ($pattern===true) {
                @unlink($filename);
                continue;
            }
            $fp = fopen($filename, 'r');
            $meta = unserialize(fgets($fp, 1024));
            fclose($fp);
            if (!$meta || $meta['ttl'] !== false && $meta['ts'] + $meta['ttl'] < time()
                || $pattern===false || strpos($meta['key'], $pattern)!==false // TODO: regexp search without iterating all files
            ) {
                @unlink($filename);
            }
        }
        return true;
    }

    public function gc()
    {
        $this->deleteMany(false);
        return true;
    }
}

class BCache_Backend_Apc extends BClass implements BCache_Backend_Interface
{
    protected $_config;

    public function info()
    {
        return array('available' => function_exists('apc_fetch'), 'rank' => 10);
    }

    public function init($config = array())
    {
        if (empty($config['prefix'])) {
            $config['prefix'] = substr(md5(__DIR__), 0, 16).'/';
        }
        if (empty($config['default_ttl'])) {
            $config['default_ttl'] = 3600;
        }
        $this->_config = $config;
        return true;
    }

    public function load($key)
    {
        $fullKey = $this->_config['prefix'].$key;
        return apc_fetch($fullKey);
    }

    public function save($key, $data, $ttl = null)
    {
        $ttl = !is_null($ttl) ? $ttl : $this->_config['default_ttl'];
        $cacheKey = $this->_config['prefix'].$key;
        /** @see http://stackoverflow.com/questions/10494744/deadlock-with-apc-exists-apc-add-apc-php */
        #if (apc_exists($cacheKey)) {
        #    apc_delete($cacheKey);
        #}
        return apc_store($cacheKey, $data, (int)$ttl);
    }

    public function delete($key)
    {
        return apc_delete($this->_config['prefix'].$key);
    }

    public function loadMany($pattern)
    {
        //TODO: regexp: new APCIterator('user', '/^MY_APC_TESTA/', APC_ITER_VALUE);
        $items = new APCIterator('user');
        $prefix = $this->_config['prefix'];
        $result = array();
        foreach ($items as $item) {
            $key = $item['key'];
            if (strpos($key, $prefix)!==0) {
                continue;
            }
            if ($pattern===true || strpos($key, $pattern)!==false) {
                $result[$key] = apc_fetch($key);
            }
        }
        return $result;
    }

    public function deleteMany($pattern)
    {
        if ($pattern===false) {
            return false; // not implemented for APC, has internal expiration
        }
        $items = new APCIterator('user');
        $prefix = $this->_config['prefix'];
        foreach ($items as $item) {
            $key = $item['key'];
            if (strpos($key, $prefix)!==0) {
                continue;
            }
            if ($pattern===true || strpos($key, $pattern)!==false) {
                apc_delete($key);
            }
        }
        return true;
    }

    public function gc()
    {
        return true;
    }
}

class BCache_Backend_Memcache extends BClass implements BCache_Backend_Interface
{
    protected $_config;
    protected $_conn;

    public function info()
    {
        return array('available' => class_exists('Memcache', false), 'rank' => 10);
    }

    public function init($config = array())
    {
        if (empty($config['prefix'])) {
            $config['prefix'] = substr(md5(__DIR__), 0, 16).'/';
        }
        if (empty($config['host'])) {
            $config['host'] = 'localhost';
        }
        if (empty($config['port'])) {
            $config['port'] = 11211;
        }
        $this->_config = $config;
        $this->_flags = !empty($config['compress']) ? MEMCACHE_COMPRESSED : 0;
        $this->_conn = new Memcache;
        return $this->_conn->pconnect($config['host'], $config['port']);
    }

    public function load($key)
    {
        return $this->_conn->get($this->_config['prefix'].$key);
    }

    public function save($key, $data, $ttl = null)
    {
        $flag = !empty($this->_config['compress']) ? MEMCACHE_COMPRESSED : 0;
        $ttl1 = is_null($ttl) ? 0 : time()+$ttl;
        return $this->_conn->set($this->_config['prefix'].$key, $data, $flag, $ttl1);
    }

    public function delete($key)
    {
        return $this->_conn->delete($this->_config['prefix'].$key);
    }

    public function loadMany($pattern)
    {
        return false; // not implemented
    }

    public function deleteMany($pattern)
    {
        return false; // not implemented
    }

    public function gc()
    {
        return false; // not implemented
    }
}

class BCache_Backend_Db extends BClass implements BCache_Backend_Interface
{
    public function info()
    {
#echo "<pre>"; debug_print_backtrace(); exit;
        $avail = (boolean)BConfig::i()->get('db/dbname');
        return array('available' => $avail, 'rank' => 90);
    }

    public function init($config = array())
    {
        $this->migrate();
    }

    public function load($key)
    {
        $cache = BCache_Backend_Db_Model_Cache::i()->load($key, 'cache_key');
        if (!$cache) {
            return null;
        }
        if ($cache->get('expires_at')<time()) {
            $cache->delete();
            return null;
        }
        return unserialize($cache->get('cache_value'));
    }

    public function save($key, $data, $ttl = null)
    {
        $hlp = BCache_Backend_Db_Model_Cache::i();
        $cache = $hlp->load($key, 'cache_key');
        if (!$cache) {
            $cache = $hlp->create(array('cache_key' => $key));
        }
        $cache->set(array(
            'expires_at' => is_null($ttl) ? null : time()+$ttl,
            'cache_value' => serialize($data),
        ))->save();
        return true;
    }

    public function delete($key)
    {
        BCache_Backend_Db_Model_Cache::i()->delete_many(array('cache_key' => $key));
        return true;
    }

    public function loadMany($pattern)
    {
        return false; //TODO: not implemented
    }

    public function deleteMany($pattern)
    {
        return false; //TODO: not implemented
    }

    public function gc()
    {
        BCache_Backend_Db_Model_Cache::i()->delete_many('expires_at<'.time());
        return true;
    }

    public function migrate()
    {
        $t = BCache_Backend_Db_Model_Cache::table();
        if (!BDb::ddlTableExists($t)) {
            BDb::ddlTableDef($t, array(
                'COLUMNS' => array(
                    'id' => 'int unsigned not null auto_increment',
                    'cache_key' => 'varchar(255) not null',
                    'cache_value' => 'mediumtext not null',
                    'expires_at' => 'int unsigned null',
                ),
                'PRIMARY' => '(id)',
                'KEYS' => array(
                    'UNQ_cache_key' => '(cache_key)',
                    'IDX_expires_at' => '(expires_at)',
                ),
                'OPTIONS' => array(
                    'engine' => 'MyISAM',
                ),
            ));
        }
    }
}

class BCache_Backend_Db_Model_Cache extends BModel
{
    static protected $_table = 'buckyball_cache';
    static protected $_origClass = __CLASS__;
}

class BCache_Backend_Shmop extends BClass implements BCache_Backend_Interface
{
    public function info()
    {
        return array('available' => false/*function_exists('shmop_open')*/, 'rank' => 10);
    }

    public function init($config = array())
    {

    }

    public function load($key)
    {

    }

    public function save($key, $data, $ttl = null)
    {

    }

    public function delete($key)
    {

    }

    public function loadMany($pattern)
    {

    }

    public function deleteMany($pattern)
    {

    }

    public function gc()
    {

    }
}

