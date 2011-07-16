<?php
/**
* Copyright 2011 Unirgy LLC
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/

/**
* bucky/app.php
*
* This file is the first bootstrap to initialize BuckyBall PHP Framework
*/

/**
* Sometimes NULL is a value too.
*/
define('BNULL', 'THIS IS A DUMMY VALUE TO DISTINCT BETWEEN LACK OF ARGUMENT/VALUE AND PHP NULL VALUE');

/**
* @see http://j4mie.github.com/idiormandparis/
*/
include_once('lib/idiorm.php');
include_once('lib/paris.php');


/**
* Base class that allows easy singleton/instance creation and method overrides (decorator)
*
* This class is used for all BuckyBall framework base classes
*
* @see BClassRegistry for invokation
*/
class BClass
{
    /**
    * Fallback singleton/instance factory
    *
    * Works correctly only in PHP 5.3.0
    *
    * @param bool $new if true returns a new instance, otherwise singleton
    * @param array $args
    * @return BClass
    */
    public static function i($new=false, array $args=array())
    {
        if (!BApp::compat('PHP5.3')) {
            throw new BException(BApp::t('Implicit instance generation is not supported before PHP 5.3.0. Please add i() method to your class'));
        }
        return BClassRegistry::i()->instance(get_called_class(), $args, !$new);
    }
}

/**
* Main BuckyBall Framework class
*
*/
class BApp extends BClass
{
    /**
    * Registry of supported features
    *
    * @var array
    */
    protected static $_compat = array();

    /**
    * Global app vars registry
    *
    * @var array
    */
    protected $_vars = array();

    /**
    * Verify if a feature is currently supported. Features:
    *
    * - PHP5.3
    *
    * @param mixed $feature
    * @return boolean
    */
    public static function compat($feature)
    {
        if (!empty(static::$_compat[$feature])) {
            return static::$_compat[$feature];
        }
        switch ($feature) {
        case 'PHP5.3':
            $compat = version_compare(phpversion(), '5.3.0', '>=');
            break;

        default:
            throw new BException(BApp::t('Unknown feature: %s', $feature));
        }
        static::$_compat[$feature] = $compat;
        return $compat;
    }

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @todo Run multiple applications within the same script
    *       This requires to decide which registries should be app specific
    *
    * @return BApp
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Application contructor
    *
    * Starts debugging session for timing
    *
    * @return BApp
    */
    public function __construct()
    {
        BDebug::i();
    }

    /**
    * Shortcut to add configuration, used mostly from bootstrap index file
    *
    * @param array|string $config If string will load configuration from file
    */
    public function config($config)
    {
        if (is_array($config)) {
            BConfig::i()->add($config);
        } elseif (is_string($config) && is_file($config)) {
            BConfig::i()->addFile($config);
        } else {
            throw new BException("Invalid configuration argument");
        }
        return $this;
    }

    /**
    * Shortcut to scan folders for module manifest files
    *
    * @param string|array $folders Relative path(s) to manifests. May include wildcards.
    */
    public function load($folders='.')
    {
#echo "<pre>"; print_r(debug_backtrace()); echo "</pre>";
        if (is_string($folders)) {
            $folders = explode(',', $folders);
        }
        $modules = BModuleRegistry::i();
        foreach ($folders as $folder) {
            $modules->scan($folder);
        }
        return $this;
    }

    /**
    * The last method to be ran in bootstrap index file.
    *
    * Performs necessary initializations and dispatches requested action.
    *
    */
    public function run()
    {
        // load session variables
        BSession::i()->open();

        // bootstrap modules
        BModuleRegistry::i()->bootstrap();

        // run module migration scripts if neccessary
        // TODO: only in development mode and on demand
        BDb::i()->runMigrationScripts();

        // dispatch requested controller action
        BFrontController::i()->dispatch();

        // If session variables were changed, update session
        BSession::i()->close();

        return $this;
    }

    /**
    * Shortcut for log facility
    *
    * @param string $message Log message, may include argument placeholders
    * @param string|array $args arguments for message
    * @param array $data event variables
    */
    public static function log($message, $args=array(), $data=array())
    {
        $data['message'] = static::t($message, $args);
        BDebug::i()->log($data);
    }

    /**
    * Shortcut for translation
    *
    * @param string $string Text to be translated
    * @param string|array $args Arguments for the text
    * @return string
    */
    public static function t($string, $args=array())
    {
        return Blocale::i()->t($string, $args);
    }

    /**
    * Shortcut for base URL to use in views and controllers
    *
    * @return string
    */
    public static function baseUrl($full=true)
    {
        static $baseUrl = array();
        if (empty($baseUrl[(int)$full])) {
            /** @var BRequest */
            $r = BRequest::i();
            $baseUrl[(int)$full] = $full ? $r->baseUrl() : $r->webRoot();
        }
        return $baseUrl[(int)$full];
    }

    public function set($key, $val)
    {
        $this->_vars[$key] = $val;
        return $this;
    }

    public function get($key)
    {
        return isset($this->_vars[$key]) ? $this->_vars[$key] : null;
    }
}

/**
* Bucky specialized exception
*/
class BException extends Exception
{
    /**
    * Logs exceptions
    *
    * @param string $message
    * @param int $code
    * @return BException
    */
    public function __construct($message="", $code=0)
    {
        parent::__construct($message, $code);
        BApp::log($message, array(), array('event'=>'exception', 'code'=>$code, 'file'=>$this->getFile(), 'line'=>$this->getLine()));
    }
}

/**
* Facility to log errors and events for development and debugging
*/
class BDebug extends BClass
{
    protected $_startTime;
    protected $_events = array();

    /**
    * Contructor, remember script start time for delta timestamps
    *
    * @return BDebug
    */
    public function __construct()
    {
        $this->_startTime = microtime(true);
    }

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDebug
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Log event for future analysis
    *
    * @param array $event
    * @return BDebug
    */
    public function log($event)
    {
        $event['ts'] = microtime(true);
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $event['module'] = $moduleName;
        }
        if (class_exists('BFireLogger')) {
            BFireLogger::channel('buckyball')->log('debug', $event);
            return $this;
        }
        $this->_events[] = $event;
        return $this;
    }

    /**
    * Delta time from start
    *
    * @return float
    */
    public function delta()
    {
        return microtime(true)-$this->_startTime;
    }

    public static function dump($var)
    {
        if (is_array($var) && current($var) instanceof Model) {
            foreach ($var as $k=>$v) {
                echo '<hr>'.$k.':';
                static::dump($v);
            }
        } elseif ($var instanceof Model) {
            echo '<pre>'; print_r($var->as_array()); echo '</pre>';
        } else {
            echo '<pre>'; print_r($var); echo '</pre>';
        }
    }
}

/**
* Utility class to parse and construct strings and data structures
*/
class BUtil
{
    /**
    * Default hash algorithm
    *
    * @var string default sha512 for strength and slowness
    */
    protected static $_hashAlgo = 'sha256';

    /**
    * Default number of hash iterations
    *
    * @var int
    */
    protected static $_hashIter = 3;

    /**
    * Default full hash string separator
    *
    * @var string
    */
    protected static $_hashSep = '$';

    /**
    * Convert any data to JSON
    *
    * @param mixed $data
    * @return string
    */
    public static function toJson($data)
    {
        return json_encode($data);
    }

    /**
    * Parse JSON into PHP data
    *
    * @param string $json
    * @param bool $asObject if false will attempt to convert to array,
    *                       otherwise standard combination of objects and arrays
    */
    public static function fromJson($json, $asObject=false)
    {
        $obj = json_decode($json);
        return $asObject ? $obj : static::objectToArray($obj);
    }

    /**
    * Convert object to array recursively
    *
    * @param object $d
    * @return array
    */
    public static function objectToArray($d)
    {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }
        if (is_array($d)) {
            return array_map('BUtil::objectToArray', $d);
        }
        return $d;
    }

    /**
    * Convert array to object
    *
    * @param mixed $d
    * @return object
    */
    public static function arrayToObject($d)
    {
        if (is_array($d)) {
            return (object) array_map('BUtil::objectToArray', $d);
        }
        return $d;
    }

    /**
     * version of sprintf for cases where named arguments are desired (php syntax)
     *
     * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
     *
     * with sprintfn: sprintfn('second: %second$s ; first: %first$s', array(
     *  'first' => '1st',
     *  'second'=> '2nd'
     * ));
     *
     * @see http://www.php.net/manual/en/function.sprintf.php#94608
     * @param string $format sprintf format string, with any number of named arguments
     * @param array $args array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    public static function sprintfn($format, $args = array())
    {
        $args = (array)$args;

        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)([a-zA-Z_]\w*)(?=\$)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (! array_key_exists($arg_key, $arg_nums)) {
                user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
                return false;
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key], $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        return vsprintf($format, array_values($args));
    }

    /**
    * Inject vars into string template
    *
    * Ex: echo BUtil::injectVars('One :two :three', array('two'=>2, 'three'=>3))
    * Result: "One 2 3"
    *
    * @param string $str
    * @param array $vars
    * @return string
    */
    public static function injectVars($str, $vars)
    {
        $from = array(); $to = array();
        foreach ($vars as $k=>$v) {
            $from[] = ':'.$k;
            $to[] = $v;
        }
        return str_replace($from, $to, $str);
    }

    /**
     * Merges any number of arrays / parameters recursively, replacing
     * entries with string keys with values from latter arrays.
     * If the entry or the next value to be assigned is an array, then it
     * automagically treats both arguments as an array.
     * Numeric entries are appended, not replaced, but only if they are
     * unique
     *
     * calling: result = array_merge_recursive_distinct(a1, a2, ... aN)
     *
     * @see http://us3.php.net/manual/en/function.array-merge-recursive.php#96201
     **/
     public static function arrayMerge() {
         $arrays = func_get_args();
         $base = array_shift($arrays);
         if(!is_array($base)) $base = empty($base) ? array() : array($base);
         foreach($arrays as $append) {
             if(!is_array($append)) $append = array($append);
             foreach($append as $key => $value) {
                 if(!array_key_exists($key, $base) and !is_numeric($key)) {
                     $base[$key] = $append[$key];
                     continue;
                 }
                 if(is_array($value) or is_array($base[$key])) {
                     $base[$key] = static::arrayMerge($base[$key], $append[$key]);
                 } else if(is_numeric($key)) {
                         if(!in_array($value, $base)) $base[] = $value;
                 } else {
                     $base[$key] = $value;
                 }
             }
         }
         return $base;
     }

    /**
    * Compare 2 arrays recursively
    *
    * @param array $array1
    * @param array $array2
    */
    public static function arrayCompare(array $array1, array $array2)
    {
        $diff = false;
        // Left-to-right
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key,$array2)) {
                $diff[0][$key] = $value;
            } elseif (is_array($value)) {
                if (!is_array($array2[$key])) {
                    $diff[0][$key] = $value;
                    $diff[1][$key] = $array2[$key];
                } else {
                    $new = static::arrayCompare($value, $array2[$key]);
                    if ($new !== false) {
                        if (isset($new[0])) $diff[0][$key] = $new[0];
                        if (isset($new[1])) $diff[1][$key] = $new[1];
                    }
                }
            } elseif ($array2[$key] !== $value) {
                 $diff[0][$key] = $value;
                 $diff[1][$key] = $array2[$key];
            }
        }
        // Right-to-left
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key,$array1)) {
                $diff[1][$key] = $value;
            }
            // No direct comparsion because matching keys were compared in the
            // left-to-right loop earlier, recursively.
        }
        return $diff;
    }

    /**
    * Set or retrieve current hash algorithm
    *
    * @param string $algo
    */
    public static function hashAlgo($algo=null)
    {
        if (is_null($algo)) {
            return static::$_hashAlgo;
        }
        static::$_hashAlgo = $algo;
    }

    public static function hashIter($iter=null)
    {
        if (is_null($iter)) {
            return static::$_hashIter;
        }
        static::$iter = $iter;
    }

    /**
    * Generate random string
    *
    * @param int $strLen length of resulting string
    * @param string $chars allowed characters to be used
    */
    public static function randomString($strLen=8, $chars='abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ23456789')
    {
        $charsLen = strlen($chars)-1;
        $str = '';
        for ($i=0; $i<$strLen; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];
        }
        return $str;
    }

    /**
    * Generate salted hash
    *
    * @param string $string original text
    * @param mixed $salt
    * @param mixed $algo
    * @return string
    */
    public static function saltedHash($string, $salt, $algo=null)
    {
        $algo = !is_null($algo) ? $algo : static::$_hashAlgo;
        return hash($algo, $salt.$string);
    }

    /**
    * Generate fully composed salted hash
    *
    * Ex: $sha512$2$<salt1>$<salt2>$<double-hashed-string-here>
    *
    * @param string $string
    * @param string $salt
    * @param string $algo
    * @param integer $iter
    */
    public static function fullSaltedHash($string, $salt=null, $algo=null, $iter=null)
    {
        $algo = !is_null($algo) ? $algo : static::$_hashAlgo;
        $iter = !is_null($iter) ? $iter : static::$_hashIter;
        $s = static::$_hashSep;
        $hash = $s.$algo.$s.$iter;
        for ($i=0; $i<$iter; $i++) {
            $salt1 = !is_null($salt) ? $salt : static::randomString();
            $hash .= $s.$salt1;
            $string = static::saltedHash($string, $salt1, $algo);
        }
        return $hash.$s.$string;
    }

    /**
    * Validate salted hash against original text
    *
    * @param string $string original text
    * @param string $storedHash fully composed salted hash
    */
    public static function validateSaltedHash($string, $storedHash)
    {
        $sep = $storedHash[0];
        $arr = explode($sep, $storedHash);
        array_shift($arr);
        $algo = array_shift($arr);
        $iter = array_shift($arr);
        $verifyHash = $string;
        for ($i=0; $i<$iter; $i++) {
            $salt = array_shift($arr);
            $verifyHash = static::saltedHash($verifyHash, $salt, $algo);
        }
        $knownHash = array_shift($arr);
        return $verifyHash===$knownHash;
    }

    /**
    * Return only specific fields from source array
    *
    * @param array $source
    * @param array|string $fields
    * @param boolean $inverse if true, will return anything NOT in $fields
    * @result array
    */
    public static function maskFields($source, $fields, $inverse=false)
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        $result = array();
        if (!$inverse) {
            foreach ($fields as $k) $result[$k] = $source[$k];
        } else {
            foreach ($source as $k=>$v) if (!in_array($k, $fields)) $result[$k] = $v;
        }
        return $result;
    }
}

/**
* Global configuration storage class
*/
class BConfig extends BClass
{
    /**
    * Configuration storage
    *
    * @var array
    */
    protected $_config = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BConfig
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Add configuration fragment to global tree
    *
    * @param array $config
    * @return BConfig
    */
    public function add(array $config)
    {
        $this->_config = BUtil::arrayMerge($this->_config, $config);
        return $this;
    }

    /**
    * Add configuration from file, stored as JSON
    *
    * @param string $filename
    */
    public function addFile($filename)
    {
        if (!is_readable($filename)) {
            throw new BException(BApp::t('Invalid configuration file name: %s', $filename));
        }
        $config = BUtil::fromJson(file_get_contents($filename));
        if (!$config) {
            throw new BException(BApp::t('Invalid configuration contents: %s', $filename));
        }
        $this->add($config);
        return $this;
    }

    /**
    * Get configuration data using path
    *
    * Ex: BConfig::i()->get('some/deep/config')
    *
    * @param string $path
    */
    public function get($path)
    {
        $root = $this->_config;
        foreach (explode('/', $path) as $key) {
            if (!isset($root[$key])) {
                return null;
            }
            $root = $root[$key];
        }
        return $root;
    }
}

/**
* Wrapper for idiorm/paris
*
* For multiple connections waiting on: https://github.com/j4mie/idiorm/issues#issue/15
*
* @see http://j4mie.github.com/idiormandparis/
*/
class BDb
{
    /**
    * Collection of cached named DB connections
    *
    * @var array
    */
    protected static $_namedDbs = array();

    /**
    * Necessary configuration for each DB connection name
    *
    * @var array
    */
    protected static $_namedDbConfig = array();

    /**
    * Default DB connection name
    *
    * @var string
    */
    protected static $_defaultDbName = 'DEFAULT';

    /**
    * DB name which is currently referenced in static::$_db
    *
    * @var string
    */
    protected static $_currentDbName;

    /**
    * Current DB configuration
    *
    * @var array
    */
    protected static $_config = array('table_prefix'=>'');

    /**
    * List of tables per connection
    *
    * @var array
    */
    protected static $_tables = array();

    /**
    * List of migration scripts by module
    *
    * @var array
    */
    protected static $_migration = array();

    /**
    * List of uninstall scripts by module
    *
    * @var array
    */
    protected static $_uninstall = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDb
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Connect to DB using default or a named connection from global configuration
    *
    * Connections are cached for reuse when switching.
    *
    * Structure in configuration:
    *
    * {
    *   db: {
    *     dsn: 'mysql:host=127.0.0.1;dbname=buckyball',  - optional: replaces engine, host, dbname
    *     engine: 'mysql',                               - optional if dsn exists, default: mysql
    *     host: '127.0.0.1',                             - optional if dsn exists, default: 127.0.0.1
    *     dbname: 'buckyball',                           - optional if dsn exists, required otherwise
    *     username: 'dbuser',                            - default: root
    *     password: 'password',                          - default: (empty)
    *     logging: false,                                - default: false
    *     named: {
    *       read: {<db-connection-structure>},           - same structure as default connection
    *       write: {
    *         use: 'read'                                - optional, reuse another connection
    *       }
    *     }
    *  }
    *
    * @param string $name
    */
    public static function connect($name=null)
    {
        if (is_null($name)) {
            $name = static::$_defaultDbName;
        }
        if ($name===static::$_currentDbName) {
            return BORM::get_db();
        }
        if (!empty(static::$_namedDbs[$name])) {
            static::$_currentDbName = $name;
            static::$_db = static::$_namedDbs[$name];
            static::$_config = static::$_namedDbConfig[$name];
            return BORM::get_db();
        }
        $config = BConfig::i()->get($name===static::$_defaultDbName ? 'db' : 'db/named/'.$name);
        if (!$config) {
            throw new BException(BApp::t('Invalid or missing DB configuration: %s', $name));
        }
        if (!empty($config['use'])) { //TODO: Prevent circular reference
            static::connect($config['use']);
            return;
        }
        if (!empty($config['dsn'])) {
            $dsn = $config['dsn'];
            if (empty($config['dbname']) && preg_match('#dbname=(.*?)(;|$)#', $dsn, $m)) {
                $config['dbname'] = $m[1];
            }
        } else {
            if (empty($config['dbname'])) {
                throw new BException(BApp::t("dbname configuration value is required for '%s'", $name));
            }
            $engine = !empty($config['engine']) ? $config['engine'] : 'mysql';
            $host = !empty($config['host']) ? $config['host'] : '127.0.0.1';
            switch ($engine) {
                case "mysql":
                    $dsn = "mysql:host={$host};dbname={$config['dbname']}";
                    break;

                default:
                    throw new BException(BApp::t('Invalid DB engine: %s', $engine));
            }
        }
        static::$_currentDbName = $name;

        BORM::configure($dsn);
        BORM::configure('username', !empty($config['username']) ? $config['username'] : 'root');
        BORM::configure('password', !empty($config['password']) ? $config['password'] : '');
        BORM::configure('logging', !empty($config['logging']));
        BORM::set_db(null);
        BORM::setup_db();
        static::$_namedDbs[$name] = BORM::get_db();
        static::$_config = static::$_namedDbConfig[$name] = array(
            'dbname' => !empty($config['dbname']) ? $config['dbname'] : null,
            'table_prefix' => !empty($config['table_prefix']) ? $config['table_prefix'] : '',
        );
        return BORM::get_db();
    }

    /**
    * DB friendly current date/time
    *
    * @return string
    */
    public static function now()
    {
        return gmstrftime('%F %T');
    }

    /**
    * Shortcut to run multiple queries from migrate scripts
    *
    * @param string $sql
    * @param array $params
    */
    public static function run($sql)
    {
        $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $sql);
        $results = array();
        foreach ($queries as $query){
           if (strlen(trim($query)) > 0) {
                try {
                    $results[] = BORM::get_db()->exec($query);
                } catch (Exception $e) {
                    var_dump($e); exit;
                }
           }
        }
        return $results;
    }

    /**
    * Start transaction
    *
    * @param string $connectionName
    */
    public static function transaction($connectionName=null)
    {
        if (!is_null($connectionName)) {
            BDb::connect($connectionName);
        }
        BORM::get_db()->beginTransaction();
    }

    /**
    * Commit transaction
    *
    * @param string $connectionName
    */
    public static function commit($connectionName=null)
    {
        if (!is_null($connectionName)) {
            BDb::connect($connectionName);
        }
        BORM::get_db()->commit();
    }

    /**
    * Rollback transaction
    *
    * @param string $connectionName
    */
    public static function rollback($connectionName=null)
    {
        if (!is_null($connectionName)) {
            BDb::connect($connectionName);
        }
        BORM::get_db()->rollback();
    }

    /**
    * Get db specific table name with pre-configured prefix for current connection
    *
    * Can be used as both BDb::t() and $this->t() within migration script
    * Convenient within strings and heredocs as {$this->t(...)}
    *
    * @param string $tableName
    */
    public static function t($tableName)
    {
        $a = explode('.', $tableName);
        $p = static::$_config['table_prefix'];
        return !empty($a[1]) ? $a[0].'.'.$p.$a[1] : $p.$a[0];
    }

    /**
    * Convert array collection of objects from find_many result to arrays
    *
    * @param array $rows result of ORM::find_many()
    * @param string $method default 'as_array'
    * @return array
    */
    public static function many_as_array($rows, $method='as_array')
    {
        $res = array();
        foreach ((array)$rows as $i=>$r) {
            if (!$r instanceof BModel) {
                echo "<pre>"; print_r($r);
                debug_print_backtrace();
                exit;
            }
            $res[$i] = $r->$method();
        }
        return $res;
    }

    /**
    * Construct where statement (for delete or update)
    *
    * Examples:
    * $w = BDb::where("f1 is null");
    *
    * // (f1='V1') AND (f2='V2')
    * $w = BDb::where(array('f1'=>'V1', 'f2'=>'V2'));
    *
    * // (f1=5) AND (f2 LIKE '%text%'):
    * $w = BDb::where(array('f1'=>5, array('f2 LIKE ?', '%text%')));
    *
    * // (f1!=5) OR f2 BETWEEN 10 AND 20:
    * $w = BDb::where(array('OR'=>array(array('f1!=?', 5), array('f2 BETWEEN ? AND ?', 10, 20))));
    *
    * // (f1 IN (1,2,3)) AND NOT ((f2 IS NULL) OR (f2=10))
    * $w = BDb::where(array('f1'=>array(1,2,3)), 'NOT'=>array('OR'=>array("f2 IS NULL", 'f2'=>10)));
    *
    * @param array $conds
    * @param boolean $or
    * @return array (query, params)
    */
    public static function where($conds, $or=false)
    {
        if (is_string($conds)) {
            return array($conds, array());
        }
        $where = array();
        $params = array();
        if (is_array($conds)) {
            foreach ($conds as $f=>$v) {
                if (is_int($f)) {
                    if (is_string($v)) {
                        $where[] = '('.$v.')';
                    } elseif (is_array($v)) {
                        $where[] = array_shift($v);
                        $params = array_merge($params, $v);
                    } else {
                        throw new BException('Invalid token: '.print_r($v,1));
                    }
                } elseif ('AND'===$f) {
                    list($w, $p) = static::where($v);
                    $where[] = '('.$w.')';
                    $params = array_merge($params, $p);
                } elseif ('OR'===$f) {
                    list($w, $p) = static::where($v, true);
                    $where[] = '('.$w.')';
                    $params = array_merge($params, $p);
                } elseif ('NOT'===$f) {
                    list($w, $p) = static::where($v);
                    $where[] = 'NOT ('.$w.')';
                    $params = array_merge($params, $p);
                } elseif (is_array($v)) {
                    $where[] = "({$f} IN (".str_pad('', sizeof($v)*2-1, '?,')."))";
                    $params = array_merge($params, $v);
                } elseif (is_null($v)) {
                    $where[] = "({$f} IS NULL)";
                } else {
                    $where[] = "({$f}=?)";
                    $params[] = $v;
                }
            }
            return array(join($or ? " OR " : " AND ", $where), $params);
        }
        throw new BException("Invalid where parameter");
    }

    /**
    * Get database name for current connection
    *
    */
    public static function dbName()
    {
        if (!static::$_config) {
            throw new BException('No connection selected');
        }
        return static::$_config['dbname'];
    }

    /**
    * Clear DDL cache
    *
    */
    public static function ddlClearCache()
    {
        static::$_tables = array();
        return $this;
    }

    /**
    * Check whether table exists
    *
    * @param string $fullTableName
    * @return BDb
    */
    public static function ddlTableExists($fullTableName)
    {
        $a = explode('.', $fullTableName);
        $dbName = empty($a[1]) ? static::dbName() : $a[0];
        $tableName = empty($a[1]) ? $fullTableName : $a[1];
        if (!isset(static::$_tables[$dbName])) {
            $tables = BORM::i()->raw_query("SHOW TABLES FROM `{$dbName}`", array())->find_many();
            $field = "Tables_in_{$dbName}";
            foreach ($tables as $t) {
                static::$_tables[$dbName][$t->$field] = array();
            }
        }
        return isset(static::$_tables[$dbName][$tableName]);
    }

    /**
    * Get table field info
    *
    * @param string $fullFieldName
    * @return mixed
    */
    public static function ddlFieldInfo($fullTableName, $fieldName)
    {
        $a = explode('.', $fullTableName);
        $dbName = empty($a[1]) ? static::dbName() : $a[0];
        $tableName = empty($a[1]) ? $fullTableName : $a[1];
        if (!static::ddlTableExists($fullTableName)) {
            throw new BException(BApp::t('Invalid table name: %s.%s', $fullTableName));
        }
        $tableFields =& static::$_tables[$dbName][$tableName]['fields'];
        if (empty($tableFields)) {
            $fields = BORM::i()->raw_query("SHOW FIELDS FROM `{$dbName}`.`{$tableName}`", array())->find_many();
            foreach ($fields as $f) {
                $tableFields[$f->Field] = $f;
            }
        }
        return isset($tableFields[$fieldName]) ? $tableFields[$fieldName] : null;
    }

    /**
    * Clean array or object fields based on table columns and return an array
    *
    * @param array|object $data
    * @return array
    */
    public static function cleanForTable($table, $data)
    {
        $isObject = is_object($data);
        $result = array();
        foreach ($data as $k=>$v) {
            if (BDb::ddlFieldInfo($table, $k)) {
                $result[$k] = $isObject ? $data->$k : $data[$k];
            }
        }
        return $result;
    }

    /**
    * Declare DB Migration script for a module
    *
    * @param string $script callback, script file name or directory
    * @param string|null $moduleName if null, use current module
    */
    public static function migrate($script='migrate.php', $moduleName=null)
    {
        if (is_null($moduleName)) {
            $moduleName = BModuleRegistry::currentModuleName();
        }
        static::$_migration[$moduleName]['script'] = $script;
    }

    /**
    * Declare DB uninstallation script for a module
    *
    * @param mixed $script
    * @param empty $moduleName
    */
    public static function uninstall($script, $moduleName=null)
    {
        if (is_null($moduleName)) {
            $moduleName = BModuleRegistry::currentModuleName();
        }
        static::$_uninstall[$moduleName]['script'] = $script;
    }

    /**
    * Run declared migration scripts to install or upgrade module DB scheme
    *
    */
    public static function runMigrationScripts()
    {
        if (empty(static::$_migration)) {
            return;
        }
        $modReg = BModuleRegistry::i();
        // initialize module tables
        BDbModule::init();
        // find all installed modules
        $dbModules = BDbModule::factory()->find_many();
        // collect module code versions
        foreach (static::$_migration as $modName=>&$m) {
            $m['code_version'] = $modReg->module($modName)->version;
        }
        unset($m);
        // collect module db schema versions
        foreach ($dbModules as $m) {
            static::$_migration[$m->module_name]['schema_version'] = $m->schema_version;
        }
        // run required migration scripts
        foreach (static::$_migration as $moduleName=>$mod) {
            $modReg->currentModule($moduleName);
            $script = $mod['script'];
            /*
            try {
                BDb::transaction();
            */
                if (is_callable($script)) {
                    call_user_func($script);
                } elseif (is_file($script)) {
                    include_once($script);
                } elseif (is_dir($script)) {
                    //TODO: process directory of migration scripts
                }
            /*
                BDb::commit();
            } catch (Exception $e) {
                BDb::rollback();
                throw $e;
            }
            */
        }
        $modReg->currentModule(null);
    }

    /**
    * Run module DB installation scripts and set module db scheme version
    *
    * @param string $version
    * @param mixed $callback SQL string, callback or file name
    */
    public static function install($version, $callback)
    {
        $modName = BModuleRegistry::currentModuleName();
        // if no code version set, return
        if (empty(static::$_migration[$modName]['code_version'])) {
            return false;
        }
        // if schema version exists, skip
        if (!empty(static::$_migration[$modName]['schema_version'])) {
            return true;
        }
        // creating module before running install, so the module configuration values can be created within script
        $mod = BDbModule::create(array(
            'module_name' => $modName,
            'schema_version' => $version,
            'last_upgrade' => BDb::now(),
        ))->save();
        // call install migration script
        try {
            if (is_callable($callback)) {
                call_user_func($callback);
            } elseif (is_file($callback)) {
                include $callback;
            } elseif (is_string($callback)) {
                BDb::run($callback);
            }
        } catch (Exception $e) {
            // delete module schema record if unsuccessful
            $mod->delete();
            throw $e;
        }
        static::$_migration[$modName]['schema_version'] = $version;
        return true;
    }

    /**
    * Run module DB upgrade scripts for specific version difference
    *
    * @param string $fromVersion
    * @param string $toVersion
    * @param mixed $callback SQL string, callback or file name
    */
    public static function upgrade($fromVersion, $toVersion, $callback)
    {
        $modName = BModuleRegistry::currentModuleName();
        // if no code version set, return
        if (empty(static::$_migration[$modName]['code_version'])) {
            return false;
        }
        // if schema doesn't exist, throw exception
        if (empty(static::$_migration[$modName]['schema_version'])) {
            throw new BException(BApp::t("Can't upgrade, module schema doesn't exist yet: %s", BModuleRegistry::currentModuleName()));
        }
        $schemaVersion = static::$_migration[$modName]['schema_version'];
        // if schema is newer than requested target version, skip
        if (version_compare($schemaVersion, $fromVersion, '>=') || version_compare($schemaVersion, $toVersion, '>')) {
            return true;
        }
        // call upgrade migration script
        if (is_callable($callback)) {
            call_user_func($callback);
        } elseif (is_file($callback)) {
            include $callback;
        } elseif (is_string($callback)) {
            BDb::run($callback);
        }
        // update module schema version to new one
        static::$_migration[$modName]['schema_version'] = $toVersion;
        BDbModule::load($modName, 'module_name')->set(array(
            'schema_version' => $toVersion,
            'last_upgrade' => BDb::now(),
        ))->save();
        return true;
    }

    /**
    * Run declared uninstallation scripts on module uninstall
    *
    * @param string $modName
    * @return boolean
    */
    public static function runUninstallScript($modName=null)
    {
        if (is_null($modName)) {
            $modName = BModuleRegistry::currentModuleName();
        }
        // if no code version set, return
        if (empty(static::$_migration[$modName]['code_version'])) {
            return false;
        }
        // if module schema doesn't exist, skip
        if (empty(static::$_migration[$modName]['schema_version'])) {
            return true;
        }
        // call uninstall migration script
        if (is_callable($callback)) {
            call_user_func($callback);
        } elseif (is_file($callback)) {
            include $callback;
        } elseif (is_string($callback)) {
            BDb::run($callback);
        }
        // delete module schema version from db, related configuration entries will be deleted
        BDbModule::load($modName, 'module_name')->delete();
        return true;
    }
}

/**
* Enhanced PDO class to allow for transaction nesting for mysql and postgresql
*
* @see http://www.kennynet.co.uk/2008/12/02/php-pdo-nested-transactions/
*/
class BPDO extends PDO
{
    // Database drivers that support SAVEPOINTs.
    protected static $_savepointTransactions = array("pgsql", "mysql");

    // The current transaction level.
    protected $_transLevel = 0;

    protected function _nestable() {
        return in_array($this->getAttribute(PDO::ATTR_DRIVER_NAME),
                        static::$_savepointTransactions);
    }

    public function beginTransaction() {
        if (!$this->_nestable() || $this->_transLevel == 0) {
            parent::beginTransaction();
        } else {
            $this->exec("SAVEPOINT LEVEL{$this->_transLevel}");
        }

        $this->_transLevel++;
    }

    public function commit() {
        $this->_transLevel--;

        if (!$this->_nestable() || $this->_transLevel == 0) {
            parent::commit();
        } else {
            $this->exec("RELEASE SAVEPOINT LEVEL{$this->_transLevel}");
        }
    }

    public function rollBack() {
        $this->_transLevel--;

        if (!$this->_nestable() || $this->_transLevel == 0) {
            parent::rollBack();
        } else {
            $this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->_transLevel}");
        }
    }
}

/**
* Enhanced ORMWrapper to support multiple database connections
*/
class BORM extends ORMWrapper
{
    /**
    * Singleton instance
    *
    * @var BORM
    */
    protected static $_instance;

    /**
    * Default class name for direct ORM calls
    *
    * @var string
    */
    protected $_class_name = 'BModel';

    /**
    * Read DB connection for selects (replication slave)
    *
    * @var string|null
    */
    protected $_readDbName;

    /**
    * Write DB connection for updates (master)
    *
    * @var string|null
    */
    protected $_writeDbName;

    /**
    * Shortcut factory for generic instance
    *
    * @return BConfig
    */
    public static function i($new=false)
    {
        if ($new) {
            return new static('');
        }
        if (!static::$_instance) {
            static::$_instance = new static('');
        }
        return static::$_instance;
    }

    protected function _quote_identifier($identifier) {
        if ($identifier[0]=='(') {
            return $identifier;
        }
        return parent::_quote_identifier($identifier);
    }

    public static function get_config($key)
    {
        return !empty(static::$_config[$key]) ? static::$_config[$key] : null;
    }

    /**
    * Public alias for _setup_db
    */
    public static function setup_db()
    {
        static::_setup_db();
    }

    /**
     * Set up the database connection used by the class.
     * Use BPDO for nested transactions
     */
    protected static function _setup_db() {
        if (!is_object(static::$_db)) {
            $connection_string = static::$_config['connection_string'];
            $username = static::$_config['username'];
            $password = static::$_config['password'];
            $driver_options = static::$_config['driver_options'];
            $db = new BPDO($connection_string, $username, $password, $driver_options); //UPDATED
            $db->setAttribute(PDO::ATTR_ERRMODE, static::$_config['error_mode']);
            static::set_db($db);
        }
    }

    /**
     * Set the PDO object used by Idiorm to communicate with the database.
     * This is public in case the ORM should use a ready-instantiated
     * PDO object as its database connection.
     */
    public static function set_db($db) {
        static::$_db = $db;
        if (!is_null($db)) {
            static::_setup_identifier_quote_character();
        }
    }

    /**
    * Set read/write DB connection names from model
    *
    * @param string $read
    * @param string $write
    * @return BORMWrapper
    */
    public function set_rw_db_names($read, $write)
    {
        $this->_readDbName = $read;
        $this->_writeDbName = $write;
        return $this;
    }

    /**
    * Execute the SELECT query that has been built up by chaining methods
    * on this class. Return an array of rows as associative arrays.
    *
    * Connection will be switched to read, if set
    *
    * @return array
    */
    protected function _run()
    {
        BDb::connect($this->_readDbName);
        return parent::_run();
    }

    /**
    * Add a column to the list of columns returned by the SELECT
    * query. This defaults to '*'. The second optional argument is
    * the alias to return the column as.
    *
    * @param string|array $column if array, select multiple columns
    * @param string $alias optional alias, if $column is array, used as table name
    * @return BORM
    */
    public function select($column, $alias=null)
    {
        if (is_array($column)) {
            foreach ($column as $k=>$v) {
                $col = (!is_null($alias) ? $alias.'.' : '').$v;
                if (is_int($k)) {
                    $this->select($col);
                } else {
                    $this->select($col, $k);
                }
            }
            return $this;
        }
        return parent::select($column, $alias);
    }

    public function where_complex($conds, $or=false)
    {
        list($where, $params) = BDb::where($conds, $or);
        return $this->where_raw($where, $params);
    }

    public function find_many_assoc($key=null, $labelColumn=null)
    {
        $objects = $this->find_many();
        $array = array();
        $idColumn = !empty($key) ? $key : $this->_get_id_column_name();
        foreach ($objects as $r) {
            $array[$r->$idColumn] = is_null($labelColumn) ? $r : $r->$labelColumn;
        }
        return $array;
    }

    /**
     * Save any fields which have been modified on this object
     * to the database.
     *
     * Connection will be switched to write, if set
     *
     * @return boolean
     */
    public function save()
    {
        BDb::connect($this->_writeDbName);
        $this->_dirty_fields = BDb::cleanForTable($this->_table_name, $this->_dirty_fields);
        return parent::save();
    }

    /**
     * Delete this record from the database
     *
     * Connection will be switched to write, if set
     *
     * @return boolean
     */
    public function delete()
    {
        BDb::connect($this->_writeDbName);
        return parent::delete();
    }

    /**
     * Perform a raw query. The query should contain placeholders,
     * in either named or question mark style, and the parameters
     * should be an array of values which will be bound to the
     * placeholders in the query. If this method is called, all
     * other query building methods will be ignored.
     *
     * Connection will be set to write, if query is not SELECT or SHOW
     *
     * @return BORMWrapper
     */
    public function raw_query($query, $parameters)
    {
        if (preg_match('#^\s*(SELECT|SHOW)#i', $query)) {
            BDb::connect($this->_readDbName);
        } else {
            BDb::connect($this->_writeDbName);
        }
        return parent::raw_query($query, $parameters);
    }

    /**
    * Get table name with prefix, if configured
    *
    * @param string $class_name
    * @return string
    */
    protected static function _get_table_name($class_name) {
        return BDb::t(parent::_get_table_name($class_name));
    }

}

/**
* ORM model base class
*/
class BModel extends Model
{
    /**
    * DB name for reads. Set in class declaration
    *
    * @var string|null
    */
    protected static $_readDbName = 'DEFAULT';

    /**
    * DB name for writes. Set in class declaration
    *
    * @var string|null
    */
    protected static $_writeDbName = 'DEFAULT';

    /**
    * Cache of instance level data values (related models)
    *
    * @var array
    */
    protected $_instanceCache = array();

    /**
    * PDO object of read DB connection
    *
    * @return BPDO
    */
    public static function readDb()
    {
        return BDb::connect(static::$_readDbName);
    }

    /**
    * PDO object of write DB connection
    *
    * @return BPDO
    */
    public static function writeDb()
    {
        return BDb::connect(static::$_writeDbName);
    }

    /**
    * Model instance factory
    *
    * @param string|null $class_name automatic class name (null) works only in PHP 5.3.0
    * @return BORM
    */
    public static function factory($class_name=null)
    {
        if (is_null($class_name)) { // ADDED
            if (!BApp::compat('PHP5.3')) {
                throw new BException(BApp::t('Empty class name supported only for PHP 5.3.0'));
            }
            $class_name = get_called_class();
        }
        $class_name = BClassRegistry::i()->className($class_name); // ADDED

        $table_name = static::_get_table_name($class_name);
        $wrapper = BORM::for_table($table_name); // CHANGED
        $wrapper->set_class_name($class_name);
        $wrapper->use_id_column(static::_get_id_column_name($class_name));
        $wrapper->set_rw_db_names(static::$_readDbName, static::$_writeDbName); // ADDED
        return $wrapper;
    }

    /**
    * Fallback singleton/instance factory
    *
    * Works correctly only in PHP 5.3.0
    *
    * @param bool $new if true returns a new instance, otherwise singleton
    * @param array $args
    * @return BClass
    */
    public static function i($new=false, array $args=array())
    {
        if (!BApp::compat('PHP5.3')) {
            throw new BException(BApp::t('Implicit instance generation is not supported before PHP 5.3.0. Please add i() method to your class'));
        }
        return BClassRegistry::instance(get_called_class(), $args, !$new);
    }

    /**
    * Enhanced set method, allowing to set multiple values, and returning $this for chaining
    *
    * @param string|array $key
    * @param mixed $value
    * @return BModel
    */
    public function set($key, $value=null, $add=false)
    {
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                parent::set($k, $v);
            }
        } else {
            if ($add) {
                $oldValue = $this->get($key);
                if (is_array($oldValue)) {
                    $oldValue[] = $value;
                    $value = $oldValue;
                } else {
                    $value += $oldValue;
                }
            }
            parent::set($key, $value);
        }
        return $this;
    }

    /**
    * Create a new instance of the model
    *
    * @param null|array $data
    */
    public static function create($data=null)
    {
        return static::factory()->create($data);
    }

    /**
    * Load a model object based on ID, another field or multiple fields
    *
    * @param int|string|array $id
    * @param string $field
    * @return BModel
    */
    public static function load($id, $field=null)
    {
        $orm = static::factory();
        if (is_array($id)) {
            foreach ($id as $k=>$v) {
                $orm->where($k, $v);
            }
            return $orm->find_one();
        } elseif (is_null($field)) {
            return $orm->find_one($id);
        } else {
            return $orm->where($field, $id)->find_one();
        }
    }

    /**
    * Save method returns the model object for chaining
    *
    * @return BModel
    */
    public function save()
    {
        parent::save();
        return $this;
    }

    /**
    * Update one or many records of the class
    *
    * @param array $data
    * @param string|array $cond where conditions (@see BDb::where)
    * @return boolean
    */
    public static function update_many(array $data, $cond)
    {
        $db = BDb::connect(static::$_writeDbName);
        $table = BDb::t(static::_get_table_name(get_called_class()));
        $update = array();
        $params = array();
        foreach ($data as $k=>$v) {
            $update[] = "`{$k}`=?";
            $params[] = $v;
        }
        list($where, $p) = BDb::where($conds);
        $params = array_merge($params, $p);
        $query = "UPDATE {$table} SET ".join(', ', $update)." WHERE {$where}";
        return $db->prepare($query)->execute($params);
    }

    /**
    * Delete one or many records of the class
    *
    * @param string|array $conds where conditions (@see BDb::where)
    * @return boolean
    */
    public static function delete_many($conds)
    {
        $db = BDb::connect(static::$_writeDbName);
        $table = BDb::t(static::_get_table_name(get_called_class()));
        list($where, $params) = BDb::where($conds);
        $query = "DELETE FROM {$table} WHERE {$where}";
        return $db->prepare($query)->execute($params);
    }

    /**
    * Model data as array, recursively
    *
    * @param array $objHashes cache of object hashes to check for infinite recursion
    * @return array
    */
    public function as_array(array $objHashes=array())
    {
        $objHash = spl_object_hash($this);
        if (!empty($objHashes[$objHash])) {
            return "*** RECURSION: ".get_class($this);
        }
        $objHashes[$objHash] = 1;

        $data = parent::as_array();
        foreach ($data as $k=>$v) {
            if ($v instanceof Model) {
                $data[$k] = $v->as_array();
            } elseif (is_array($v) && current($v) instanceof Model) {
                foreach ($v as $k1=>$v1) {
                    $data[$k][$k1] = $v1->as_array($objHashes);
                }
            }
        }
        return $data;
    }

    /**
    * Store instance data cache, such as related models
    *
    * @param string $key
    * @param mixed $value
    * @return mixed
    */
    public function instanceCache($key, $value=BNULL)
    {
        if (BNULL===$value) {
            return isset($this->_instanceCache[$key]) ? $this->_instanceCache[$key] : null;
        }
        $this->_instanceCache[$key] = $value;
        return $this;
    }

    public function relatedModel($modelClass, $idValue, $foreignIdField='id')
    {
        $cacheKey = $modelClass;
        $model = $this->instanceCache($cacheKey);
        if (is_null($model)) {
            if (is_array($idValue)) {
                $model = $modelClass::factory()->where_complex($idValue)->find_one();
            } else {
                $model = $modelClass::load($idValue, $foreignIdField);
            }
            $this->instanceCache($cacheKey, $model);
        }
        return $model;
    }

    public function __destruct()
    {
        unset($this->_instanceCache);
    }
}

class BDbModule extends BModel
{
    protected static $_table = 'buckyball_module';

    public static function init()
    {
        $table = BDb::t(static::$_table);
        BDb::connect();
        if (!BDb::ddlTableExists($table)) {
            BDb::run("
CREATE TABLE {$table} (
id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
module_name VARCHAR(100) NOT NULL,
schema_version VARCHAR(20),
last_upgrade DATETIME,
UNIQUE (module_name)
) ENGINE=INNODB;
            ");
        }
        BDbModuleConfig::init();
    }
}

class BDbModuleConfig extends BModel
{
    protected static $_table = 'buckyball_module_config';

    public static function init()
    {
        $table = BDb::t(static::$_table);
        $modTable = BDb::t('buckyball_module');
        if (!BDb::ddlTableExists($table)) {
            BDb::run("
CREATE TABLE {$table} (
id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
module_id INT UNSIGNED NOT NULL,
`key` VARCHAR(100),
`value` TEXT,
UNIQUE (module_id, `key`),
CONSTRAINT `FK_{$modTable}` FOREIGN KEY (`module_id`) REFERENCES `{$modTable}` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=INNODB;
            ");
        }
    }
}

/**
* Registry of classes, class overrides and method overrides
*/
class BClassRegistry extends BClass
{
    /**
    * Self instance for singleton
    *
    * @var BClassRegistry
    */
    static protected $_instance;

    /**
    * Class overrides
    *
    * @var array
    */
    protected $_classes = array();

    /**
    * Method overrides and augmentations
    *
    * @var array
    */
    protected $_methods = array();

    /**
    * Property setter/getter overrides and augmentations
    *
    * @var array
    */
    protected $_properties = array();

    /**
    * Registry of singletons
    *
    * @var array
    */
    protected $_singletons = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @param bool $new
    * @param array $args
    * @param bool $forceRefresh force the recreation of singleton
    * @return BClassRegistry
    */
    public static function i($new=false, array $args=array(), $forceRefresh=false)
    {
        if (!static::$_instance) {
            static::$_instance = new BClassRegistry;
        }
        if (!$new && !$forceRefresh) {
            return static::$_instance;
        }
        $class = BApp::compat('PHP5.3') ? get_called_class() : __CLASS__;
        return static::$_instance->instance($class, $args, !$new);
    }

    /**
    * Override a class
    *
    * Usage: BClassRegistry::i()->overrideClass('BaseClass', 'MyClass');
    *
    * Remembering the module that overrode the class for debugging
    *
    * @param string $class Class to be overridden
    * @param string $newClass New class
    * @param bool $replaceSingleton If there's already singleton of overridden class, replace with new one
    * @return BClassRegistry
    */
    public function overrideClass($class, $newClass, $replaceSingleton=false)
    {
        $this->_classes[$class] = array(
            'class_name' => $newClass,
            'module_name' => BModuleRegistry::currentModuleName(),
        );
        if ($replaceSingleton && !empty($this->_singletons[$class]) && get_class($this->_singletons[$class])!==$newClass) {
            $this->_singletons[$class] = $this->instance($newClass);
        }
        return $this;
    }

    /**
    * Dynamically override a class method (decorator pattern)
    *
    * Already existing instances of the class will not be affected.
    *
    * Usage: BClassRegistry::i()->overrideMethod('BaseClass', 'someMethod', array('MyClass', 'someMethod'));
    *
    * Overridden class should be called one of the following ways:
    * - BClassRegistry::i()->instance('BaseClass')
    * - BaseClass:i() -- if it extends BClass or has the shortcut defined
    *
    * Callback method example (original method had 2 arguments):
    *
    * class MyClass {
    *   static public function someMethod($origObject, $arg1, $arg2)
    *   {
    *       // do some custom stuff before call to original method here
    *
    *       $origObject->someMethod($arg1, $arg2);
    *
    *       // do some custom stuff after call to original method here
    *
    *       return $origObject;
    *   }
    * }
    *
    * Remembering the module that overrode the method for debugging
    *
    * @todo decide whether static overrides are needed
    *
    * @param string $class Class to be overridden
    * @param string $method Method to be overridden
    * @param mixed $callback Callback to invoke on method call
    * @param bool $static Whether the static method call should be overridden
    * @return BClassRegistry
    */
    public function overrideMethod($class, $method, $callback, $static=false)
    {
        $this->_methods[$class][$static ? 1 : 0][$method]['override'] = array(
            'module_name' => BModuleRegistry::currentModuleName(),
            'callback' => $callback,
        );
        return $this;
    }

    /**
    * Dynamically augment class method result
    *
    * Allows to change result of a method for every invokation.
    * Syntax similar to overrideMethod()
    *
    * Callback method example (original method had 2 arguments):
    *
    * class MyClass {
    *   static public function someMethod($result, $origObject, $arg1, $arg2)
    *   {
    *       // augment $result of previous object method call
    *       $result['additional_info'] = 'foo';
    *
    *       return $result;
    *   }
    * }
    *
    * A difference between overrideModule and augmentModule is that
    * you can override only with one another method, but augment multiple times.
    *
    * If augmented multiple times, each consequetive callback will receive result
    * changed by previous callback.
    *
    * @param string $class
    * @param string $method
    * @param mixed $callback
    * @param boolean $static
    * @return BClassRegistry
    */
    public function augmentMethod($class, $method, $callback, $static=false)
    {
        $this->_methods[$class][$static ? 1 : 0][$method]['augment'][] = array(
            'module_name' => BModuleRegistry::currentModuleName(),
            'callback' => $callback,
        );
        return $this;
    }

    /**
    * Augment class property setter/getter
    *
    * BClassRegistry::i()->augmentProperty('SomeClass', 'foo', 'set', 'override', 'MyClass::newSetter');
    * BClassRegistry::i()->augmentProperty('SomeClass', 'foo', 'get', 'after', 'MyClass::newGetter');
    *
    * class MyClass {
    *   static public function newSetter($object, $property, $value)
    *   {
    *     $object->$property = myCustomProcess($value);
    *   }
    *
    *   static public function newGetter($object, $property, $prevResult)
    *   {
    *     return $prevResult+5;
    *   }
    * }
    *
    * @param string $class
    * @param string $property
    * @param string $op {set|get}
    * @param string $type {override|before|after} get_before is not implemented
    * @param mixed $callback
    * @return BClassRegistry
    */
    public function augmentProperty($class, $property, $op, $type, $callback)
    {
        if ($op!=='set' && $op!=='get') {
            throw new BException(BApp::t('Invalid property augmentation operator: %s', $op));
        }
        if ($type!=='override' && $type!=='before' && $type!=='after') {
            throw new BException(BApp::t('Invalid property augmentation type: %s', $type));
        }
        $entry = array(
            'module_name' => BModuleRegistry::currentModuleName(),
            'callback' => $callback,
        );
        if ($type==='override') {
            $this->_properties[$class][$property][$op.'_'.$type] = $entry;
        } else {
            $this->_properties[$class][$property][$op.'_'.$type][] = $entry;
        }
        return $this;
    }

    /**
    * Call overridden method
    *
    * @param object $origObject
    * @param string $method
    * @param mixed $args
    * @return mixed
    */
    public function callMethod($origObject, $method, array $args=array())
    {
        $class = get_class($origObject);

        if (!empty($this->_methods[$class][0][$method]['override'])) {
            $overridden = true;
            $callback = $this->_methods[$class][0][$method]['override']['callback'];
            array_unshift($args, $origObject);
        } else {
            $overridden = false;
            $callback = array($origObject, $method);
        }

        $result = call_user_func_array($callback, $args);

        if (!empty($this->_methods[$class][0][$method]['augment'])) {
            if (!$overridden) {
                array_unshift($args, $origObject);
            }
            array_unshift($args, $result);
            foreach ($this->_methods[$class][0][$method]['augment'] as $augment) {
                $result = call_user_func_array($augment['callback'], $args);
                $args[0] = $result;
            }
        }

        return $result;
    }

    /**
    * Call static overridden method
    *
    * Static class properties will not be available to callbacks
    *
    * @todo decide if this is needed
    *
    * @param string $class
    * @param string $method
    * @param array $args
    */
    public function callStaticMethod($class, $method, array $args=array())
    {
        $callback = !empty($this->_methods[$class][1][$method])
            ? $this->_methods[$class][1][$method]['override']['callback']
            : array($class, $method);

        $result = call_user_func_array($callback, $args);

        if (!empty($this->_methods[$class][1][$method]['augment'])) {
            array_unshift($args, $result);
            foreach ($this->_methods[$class][1][$method]['augment'] as $augment) {
                $result = call_user_func_array($augment['callback'], $args);
                $args[0] = $result;
            }
        }

        return $result;
    }

    /**
    * Call augmented property setter
    *
    * @param object $origObject
    * @param string $property
    * @param mixed $value
    */
    public function callSetter($origObject, $property, $value)
    {
        $class = get_class($origObject);

        if (!empty($this->_properties[$class][$method]['set_before'])) {
            foreach ($this->_properties[$class][$method]['set_before'] as $entry) {
                call_user_func($entry['callback'], $origObject, $property, $value);
            }
        }

        if (!empty($this->_properties[$class][$method]['set_override'])) {
            $callback = $this->_properties[$class][$method]['set_override']['callback'];
            call_user_func($callback, $origObject, $property, $value);
        } else {
            $origObject->$property = $value;
        }

        if (!empty($this->_properties[$class][$method]['set_after'])) {
            foreach ($this->_properties[$class][$method]['set_after'] as $entry) {
                call_user_func($entry['callback'], $origObject, $property, $value);
            }
        }
    }

    /**
    * Call augmented property getter
    *
    * @param object $origObject
    * @param string $property
    * @return mixed
    */
    public function callGetter($origObject, $property)
    {
        $class = get_class($origObject);

        // get_before does not make much sense, so is not implemented

        if (!empty($this->_properties[$class][$method]['get_override'])) {
            $callback = $this->_properties[$class][$method]['get_override']['callback'];
            $result = call_user_func($callback, $origObject, $property);
        } else {
            $result = $origObject->$property;
        }

        if (!empty($this->_properties[$class][$method]['get_after'])) {
            foreach ($this->_properties[$class][$method]['get_after'] as $entry) {
                $result = call_user_func($entry['callback'], $origObject, $property, $result);
            }
        }

        return $result;
    }

    /**
    * Get actual class name for potentially overridden class
    *
    * @param mixed $class
    * @return mixed
    */
    public function className($class)
    {
        return !empty($this->_classes[$class]) ? $this->_classes[$class]['class_name'] : $class;
    }

    /**
    * Get a new instance or a singleton of a class
    *
    * If at least one method of the class if overridden, returns decorator
    *
    * @param string $class
    * @param mixed $args
    * @param bool $singleton
    * @return object
    */
    public function instance($class, array $args=array(), $singleton=false)
    {
        // if singleton is requested and already exists, return the singleton
        if ($singleton && !empty($this->_singletons[$class])) {
            return $this->_singletons[$class];
        }

        // get original or overridden class instance
        $className = $this->className($class);
        $instance = new $className($args);

        // if any methods are overridden or augmented, get decorator
        if (!empty($this->_methods[$class])) {
            $instance = $this->instance('BClassDecorator', array($instance));
        }

        // if singleton is requested, save
        if ($singleton) {
            $this->_singletons[$class] = $instance;
        }

        return $instance;
    }
}


/**
* Decorator class to allow easy method overrides
*
*/
class BClassDecorator
{
    /**
    * Contains the decorated (original) object
    *
    * @var object
    */
    protected $_decoratedComponent;

    /**
    * Decorator constructor, creates an instance of decorated class
    *
    * @param object|string $class
    * @return BClassDecorator
    */
    public function __construct($args)
    {
//echo '1: '; print_r($class);
        $class = array_shift($args);
        $this->_decoratedComponent = is_string($class) ? BClassRegistry::i()->instance($class, $args) : $class;
    }

    /**
    * Method override facility
    *
    * @param string $name
    * @param array $args
    * @return mixed Result of callback
    */
    public function __call($name, array $args)
    {
        return BClassRegistry::i()->callMethod($this->_decoratedComponent, $name, $args);
    }

    /**
    * Static method override facility
    *
    * Depends on PHP 5.3.0
    *
    * @param mixed $name
    * @param mixed $args
    * @return mixed Result of callback
    */
    public static function __callStatic($name, array $args)
    {
        return BClassRegistry::i()->callStaticMethod(get_called_class(), $name, $args);
    }

    /**
    * Proxy to set decorated component property or a setter
    *
    * @param string $name
    * @param mixed $value
    */
    public function __set($name, $value)
    {
        //$this->_decoratedComponent->$name = $value;
        BClassRegistry::i()->callSetter($this->_decoratedComponent, $name, $value);
    }

    /**
    * Proxy to get decorated component property or a getter
    *
    * @param string $name
    * @return mixed
    */
    public function __get($name)
    {
        //return $this->_decoratedComponent->$name;
        return BClassRegistry::i()->callGetter($this->_decoratedComponent, $name);
    }

    /**
    * Proxy to unset decorated component property
    *
    * @param string $name
    */
    public function __unset($name)
    {
        unset($this->_decoratedComponent->$name);
    }

    /**
    * Proxy to check whether decorated component property is set
    *
    * @param string $name
    * @return boolean
    */
    public function __isset($name)
    {
        return isset($this->_decoratedComponent->$name);
    }

    /**
    * Proxy to return decorated component as string
    *
    * @return string
    */
    public function __toString()
    {
        return (string)$this->_decoratedComponent;
    }

    /**
    * Proxy method to serialize decorated component
    *
    */
    public function __sleep()
    {
        if (method_exists($this->_decoratedComponent, '__sleep')) {
            return $this->_decoratedComponent->__sleep();
        }
        return array();
    }

    /**
    * Proxy method to perform for decorated component on unserializing
    *
    */
    public function __wakeup()
    {
        if (method_exists($this->_decoratedComponent, '__wakeup')) {
            $this->_decoratedComponent->__wakeup();
        }
    }

    /**
    * Proxy method to invoke decorated component as a method if it is callable
    *
    */
    public function __invoke()
    {
        if (is_callable($this->_decoratedComponent)) {
            return $this->_decoratedComponent(func_get_args());
        }
        return null;
    }
}

/**
* Events and observers registry
*/
class BEventRegistry extends BClass
{
    /**
    * Stores events and observers
    *
    * @var array
    */
    protected $_events = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BEventRegistry
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Declare event with default arguments in bootstrap function
    *
    * @param string|array $eventName accepts multiple events in form of non-associative array
    * @param array $args
    * @return BEventRegistry
    */
    public function event($eventName, $args=array())
    {
        if (is_array($eventName)) {
            foreach ($eventName as $event) {
                $this->event($event[0], !empty($event[1]) ? $event[1] : array());
            }
            return $this;
        }
        $this->_events[$eventName] = array(
            'observers' => array(),
            'args' => $args,
        );
        return $this;
    }

    /**
    * Declare observers in bootstrap function
    *
    * @param string|array $eventName accepts multiple observers in form of non-associative array
    * @param mixed $callback
    * @param array $args
    * @return BEventRegistry
    */
    public function observe($eventName, $callback=null, $args=array())
    {
        if (is_array($eventName)) {
            foreach ($eventName as $obs) {
                $this->observe($obs[0], $obs[1], !empty($obs[2]) ? $obs[2] : array());
            }
            return $this;
        }
        $observer = array('callback'=>$callback, 'args'=>$args);
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $observer['module_name'] = $moduleName;
        }
        $this->_events[$eventName]['observers'][] = $observer;
        return $this;
    }

    /**
    * Alias for observe()
    *
    * @param string|array $eventName
    * @param mixed $callback
    * @param array $args
    * @return BEventRegistry
    */
    public function watch($eventName, $callback=null, $args=array())
    {
        return $this->observe($eventName, $callback, $args);
    }

    /**
    * Dispatch event observers
    *
    * @param string $eventName
    * @param array $args
    * @return array Collection of results from observers
    */
    public function dispatch($eventName, $args=array())
    {
        $result = array();
        if (!empty($this->_events[$eventName])) {
            foreach ($this->_events[$eventName]['observers'] as $observer) {

                if (!empty($this->_events[$eventName]['args'])) {
                    $args = array_merge($this->_events[$eventName]['args'], $args);
                }
                if (!empty($observer['args'])) {
                    $args = array_merge($observer['args'], $args);
                }
                if (is_array($observer['callback']) && is_string($observer['callback'][0])) {
                    $observer['callback'][0] = BClassRegistry::i()->instance($observer['callback'][0], array(), true);
                }
                // Set current module to be used in observer callback
                BModuleRegistry::i()->currentModule(!empty($observer['module_name']) ? $observer['module_name'] : null);

                // Invoke observer
                $result[] = call_user_func($observer['callback'], $args);
            }
            // Unset current module
            BModuleRegistry::i()->currentModule(null);
        }
        return $result;
    }
}

/**
* Registry of modules, their manifests and dependencies
*/
class BModuleRegistry extends BClass
{
    /**
    * Module manifests
    *
    * @var array
    */
    protected $_modules = array();

    /**
    * Current module name, not BNULL when:
    * - In module bootstrap
    * - In observer
    * - In view
    *
    * @var string
    */
    protected static $_currentModuleName = BNULL;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BModuleRegistry
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Scan for module manifests in a folder
    *
    * Scan can be performed multiple times on different locations, order doesn't matter for dependencies
    * Wildcards are accepted.
    *
    * @see BApp::load() for examples
    *
    * @param string $source
    */
    public function scan($source)
    {
        // if $source does not end with .json, assume it is a folder
        if (substr($source, -5)!=='.json') {
            $source .= '/manifest.json';
        }
        $manifests = glob($source);
        if (!$manifests) {
            return $this;
        }
        foreach ($manifests as $file) {
            $json = file_get_contents($file);
            $manifest = BUtil::fromJson($json);
            if (empty($manifest['modules'])) {
                throw new BException(BApp::t("Could not read manifest file: %s", $file));
            }
            $rootDir = dirname(realpath($file));
            foreach ($manifest['modules'] as $modName=>$params) {
                if (!empty($this->_modules[$modName])) {
                    throw new BException(BApp::t('Module is already registered: %s (%s)', array($modName, $rootDir.'/'.$file)));
                }
                if (empty($params['bootstrap']['file']) || empty($params['bootstrap']['callback'])) {
                    BApp::log('Missing vital information, skipping module: %s', $modName);
                    continue;
                }
                $params['name'] = $modName;
                $params['root_dir'] = $rootDir;
                $params['view_root_dir'] = $rootDir;
                $params['base_url'] = BApp::baseUrl().'/'.(!empty($manifest['base_path']) ? $manifest['base_path'] : dirname($file));
                $this->_modules[$modName] = BModule::i(true, $params);
            }
        }
        return $this;
    }

    /**
    * Check module dependencies
    *
    * @return BModuleRegistry
    */
    public function checkDepends()
    {
        // scan for dependencies
        foreach ($this->_modules as $modName=>$mod) {
            if (!empty($mod->depends)) {
                foreach ($mod->depends as &$dep) {
                    if (is_string($dep)) {
                        $dep = array('name'=>$dep);
                    }
                    if (!empty($this->_modules[$dep['name']])) {
                        if (!empty($dep['version'])) {
                            $depVer = $dep['version'];
                            if (!empty($depVer['from']) && version_compare($depMod->version, $depVer['from'], '<')
                                || !empty($depVer['to']) && version_compare($depMod->version, $depVer['to'], '>')
                                || !empty($depVer['exclude']) && in_array($depMod->version, (array)$depVer['exclude'])
                            ) {
                                $dep['error'] = array('type'=>'version');
                            }
                        }
                        $mod->parents[] = $dep['name'];
                        $this->_modules[$dep['name']]->children[] = $modName;
                    } else {
                        $dep['error'] = array('type'=>'missing');
                    }
                }
                unset($dep);
            }
        }
        // propagate dependencies into subdependent modules
        foreach ($this->_modules as $modName=>$mod) {
            foreach ($mod->depends as &$dep) {
                if (!empty($dep['error']) && empty($dep['error']['propagated'])) {
                    $this->propagateDepends($modName, $dep);
                }
            }
            unset($dep);
        }
        return $this;
    }

    /**
    * Propagate dependencies into submodules recursively
    *
    * @param string $modName
    * @param BModule $dep
    * @return BModuleRegistry
    */
    public function propagateDepends($modName, &$dep)
    {
        $this->_modules[$modName]->error = 'depends';
        $dep['error']['propagated'] = true;
        if (!empty($this->_modules[$modName]->depends)) {
            foreach ($this->_modules[$modName]->depends as &$subDep) {
                if (empty($subDep['error'])) {
                    $subDep['error'] = array('type'=>'parent');
                    $this->propagateDepends($dep['name'], $subDep);
                }
            }
            unset($subDep);
        }
        return $this;
    }

    /**
    * Perform topological sorting for module dependencies
    *
    * @return BModuleRegistry
    */
    public function sortDepends()
    {
        $modules = $this->_modules;
        // get modules without dependencies
        $rootModules = array();
        foreach ($modules as $modName=>$mod) {
            if (empty($mod->parents)) {
                $rootModules[] = $mod;
            }
        }
        // begin algorithm
        $sorted = array();
        while ($modules) {
            // check for circular reference
            if (!$rootModules) return false;
            // remove this node from root modules and add it to the output
            $n = array_pop($rootModules);
            $sorted[$n->name] = $n;
            // for each of its children: queue the new node, finally remove the original
            for ($i = count($n->children)-1; $i>=0; $i--) {
                // get child module
                $childModule = $modules[$n->children[$i]];
                // remove child modules from parent
                unset($n->children[$i]);
                // remove parent from child module
                unset($childModule->parents[array_search($n->name, $childModule->parents)]);
                // check if this child has other parents. if not, add it to the root modules list
                if (!$childModule->parents) array_push($rootModules, $childModule);
            }
            // removed processed module from list
            unset($modules[$n->name]);
        }
        $this->_modules = $sorted;
        return $this;
    }

    /**
    * Run modules bootstrap callbacks
    *
    * @return BModuleRegistry
    */
    public function bootstrap()
    {
        $this->checkDepends();
        $this->sortDepends();
        foreach ($this->_modules as $mod) {
            if (!empty($mod->error)) {
                continue;
            }
            $this->currentModule($mod->name);
            include_once ($mod->root_dir.'/'.$mod->bootstrap['file']);
            BApp::log('Start bootstrap for %s', array($mod->name));
            call_user_func($mod->bootstrap['callback']);
            BApp::log('End bootstrap for %s', array($mod->name));
        }
        BModuleRegistry::i()->currentModule(null);
        return $this;
    }

    /**
    * Return module object based on module name
    *
    * @param string $name
    * @return BModule
    */
    public function module($name)
    {
        return isset($this->_modules[$name]) ? $this->_modules[$name] : null;
    }

    /**
    * Set or return current module context
    *
    * If $name is specified, set current module, otherwise retrieve one
    *
    * Used in context of bootstrap, event observer, view
    *
    * @param string|empty $name
    * @return BModule|BModuleRegistry
    */
    public function currentModule($name=BNULL)
    {
        if (BNULL===$name) {
            return static::$_currentModuleName ? $this->module(static::$_currentModuleName) : false;
        }
        static::$_currentModuleName = $name;
        return $this;
    }

    static public function currentModuleName()
    {
        return static::$_currentModuleName;
    }
}

/**
* Module object to store module manifest and other properties
*/
class BModule extends BClass
{
    public $name;
    public $bootstrap;
    public $version;
    public $root_dir;
    public $view_root_dir;
    public $base_url;
    public $depends = array();
    public $parents = array();
    public $children = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BModule
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Assign arguments as module parameters
    *
    * @param array $args
    * @return BModule
    */
    public function __construct(array $args)
    {
        foreach ($args as $k=>$v) {
            $this->$k = $v;
        }
    }
}

/**
* Front controller class to register and dispatch routes
*/
class BFrontController extends BClass
{
    /**
    * Array of routes
    *
    * @var array
    */
    protected $_routes = array();

    /**
    * Default routes if route not found in tree
    *
    * @var array
    */
    protected $_defaultRoutes = array('default'=>array('callback'=>array('BActionController', 'noroute')));

    /**
    * Tree of routes
    *
    * @var array
    */
    protected $_routeTree = array();

    /**
    * Templates to generate URLs based on routes
    *
    * @var array
    */
    protected $_urlTemplates = array();

    /**
    * Current controller name
    *
    * @var string
    */
    protected $_controllerName;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BFrontController
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Save RESTful route in tree
    *
    * @param string $route "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
    * @param mixed $callback PHP callback
    * @param array $args Route arguments
    * @param mixed $multiple Allow multiple callbacks for the same route
    */
    public function saveRoute($route, $callback=null, $args=null, $multiple=false)
    {
        list($method, $route) = explode(' ', $route, 2);
        $route = ltrim($route, '/');

        $node =& $this->_routeTree[$method];
        $routeArr = explode('/', $route);
        foreach ($routeArr as $r) {
            if ($r!=='' && $r[0]===':') {
                $node =& $node['/:'][$r];
            } else {
                $node =& $node['/'][$r==='' ? '__EMPTY__' : $r];
            }
        }
        $observer = array('callback'=>$callback);
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $observer['module_name'] = $moduleName;
        }
        if (!empty($args)) {
            $observer['args'] = $args;
        }
        if ($multiple || empty($node['observers'])) {
            $node['observers'][] = $observer;
        } else {
            $node['observers'][0] = BUtil::arrayMerge($node['observers'][0], $observer);
        }
        unset($node);

        return $this;
    }

    /**
    * Find a route in the tree
    *
    * @param string $route RESTful route
    * @return array|null Route node or null if not found
    */
    public function findRoute($route=null)
    {
        if (is_null($route)) {
            $route = BRequest::i()->rawPath();
        }
        if (strpos($route, ' ')===false) {
            $method = BRequest::i()->method();
        } else {
            list($method, $route) = explode(' ', $route, 2);
        }
        if (empty($this->_routeTree[$method])) {
            return null;
        }
        $requestArr = $route=='' ? array('') : explode('/', ltrim($route, '/'));
        $routeNode = $this->_routeTree[$method];
        $routeName = array($method.' ');
        $params = array();
        foreach ($requestArr as $i=>$r) {
            $r1 = $r==='' ? '__EMPTY__' : $r;
            $nextR = isset($requestArr[$i+1]) ? $requestArr[$i+1] : null;
            $nextR = $nextR==='' ? '__EMPTY__' : $nextR;
            if ($r1==='__EMPTY__' && !empty($routeNode['/'][$r1]) && is_null($nextR)) {
                $routeNode = $routeNode['/'][$r1];
                $routeName[] = $r;
                break;
            }
            if (!empty($routeNode['/:'])) {
                foreach ($routeNode['/:'] as $k=>$n) {
                    if (!is_null($nextR) && !empty($n['/'][$nextR]) || is_null($nextR) && !empty($n['observers'])) {
                        $params[substr($k, 1)] = $r;
                        $routeNode = $n;
                        continue 2;
                    }
                }
            }
            if (!empty($routeNode['/'][$r1])) {
                $routeNode = $routeNode['/'][$r1];
                $routeName[] = $r;
                continue;
            }
            return null;
        }
        $routeNode['route_name'] = join('/', $routeName);
        $routeNode['params_values'] = $params;
        return $routeNode;
    }

    /**
    * Declare RESTful route
    *
    * @param string $route "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
    * @param mixed $callback PHP callback
    * @param array $args Route arguments
    * @param string $name optional name for the route for URL templating
    * @return BFrontController for chain linking
    */
    public function route($route, $callback=null, $args=null, $name=null)
    {
        if (is_array($route)) {
            foreach ($route as $a) {
                $this->route($a[0], $a[1], isset($a[2])?$a[2]:null, isset($a[3])?$a[3]:null);
            }
            return;
        }

        $this->saveRoute($route, $callback, $args, false);

        $this->_routes[$route] = $callback;
        if (!is_null($name)) {
            $this->_urlTemplates[$name] = $route;
        }
        return $this;
    }

    /**
    * Set default route
    *
    * @param mixed $callback PHP callback
    * @param mixed $args Route arguments
    * @param mixed $name optional route name
    * @return BFrontController
    */
    public function defaultRoute($callback, $args=null, $name='default')
    {
        $route = array('callback'=>$callback, 'args'=>$args);
        if ($name) {
            $this->_defaultRoutes[$name] = $route;
        } else {
            $this->_defaultRoutes[] = $route;
        }
        return $this;
    }

    /**
    * Retrieve current route node
    *
    */
    public function currentRoute()
    {
        return $this->_currentRoute;
    }

    /**
    * Dispatch current route
    *
    * @param string $route optional route for explicit route dispatch
    * @return BFrontController
    */
    public function dispatch($route=null)
    {
        $routeNode = $this->findRoute($route);

        $this->_currentRoute = $routeNode;

        if (!$routeNode || empty($routeNode['observers'])) {
            $params = array();
            $callback = $this->_defaultRoutes['default']['callback'];
        } else {
            $callback = $routeNode['observers'][0]['callback'];
            $params = isset($routeNode['params_values']) ? $routeNode['params_values'] : array();
        }
        $controllerName = $callback[0];
        $actionName = $callback[1];
        $args = !empty($routeNode['args']) ? $routeNode['args'] : array();
        $controller = null;
        $attempts = 0;
        $request = BRequest::i();
        if (!empty($routeNode['observers'][0]['module_name'])) {
            BModuleRegistry::i()->currentModule($routeNode['observers'][0]['module_name']);
        }
        do {
            if (!empty($controller)) {
                list($actionName, $forwardControllerName, $params) = $controller->forward();
                if ($forwardControllerName) {
                    $controllerName = $forwardControllerName;
                }
            }
            $request->initParams($params);
            $controller = BClassRegistry::i()->instance($controllerName, array(), true);
            $controller->dispatch($actionName, $args);
        } while ((++$attempts<100) && $controller->forward());

        if ($attempts==100) {
            throw new BException(BApp::t('Reached 100 route iterations: %s', print_r($callback,1)));
        }
    }

    /**
    * Generate URL based on route template
    *
    * @todo implement whenever needed
    * @param string $name
    * @param array $params
    */
    public function url($name, $params = array())
    {

    }
}

/**
* Action controller class for route action declarations
*/
class BActionController extends BClass
{
    /**
    * Action parameters
    *
    * @var array
    */
    public $params = array();

    /**
    * Current action name
    *
    * @var string
    */
    protected $_action;

    /**
    * Forward location. If set the dispatch will loop and forward to next action
    *
    * @var string|null
    */
    protected $_forward;

    /**
    * Prefix for action methods
    *
    * @var string
    */
    protected $_actionMethodPrefix = 'action_';

    /**
    * Shortcut for fetching layout views
    *
    * @param string $viewname
    * @return BView
    */
    public function view($viewname)
    {
        return BLayout::i()->view($viewname);
    }

    /**
    * Dispatch action within the action controller class
    *
    * @param string $actionName
    * @param array $args Action arguments
    */
    public function dispatch($actionName, $args=array())
    {
        $this->_action = $actionName;
        $this->_forward = null;
        if (!$this->beforeDispatch($args)) {
            return $this;
        } elseif (!$this->authorize($args) && $actionName!=='unauthorized') {
            $this->forward('unauthorized');
            return $this;
        }
        $this->tryDispatch($actionName, $args);
        if (!$this->forward()) {
            $this->afterDispatch($args);
        }
        return $this;
    }

    /**
    * Try to dispatch action and catch exception if any
    *
    * @param string $actionName
    * @param array $args
    */
    public function tryDispatch($actionName, $args)
    {
        $actionMethod = $this->_actionMethodPrefix.$actionName;
        if (!is_callable(array($this, $actionMethod))) {
            $this->forward('noroute');
            return $this;
        }
        try {
            $this->$actionMethod($args);
        } catch (DActionException $e) {
            $this->sendError($e->getMessage());
        } catch (Exception $e) {
echo "<pre>"; print_r($e); echo "</pre>";
        }
        return $this;
    }

    /**
    * Forward to another action or retrieve current forward
    *
    * @param string $actionName
    * @param string $controllerName
    * @param array $params
    * @return string|null|BActionController
    */
    public function forward($actionName=BNULL, $controllerName=null, array $params=array())
    {
        if (BNULL===$actionName) {
            return $this->_forward;
        }
        $this->_forward = array($actionName, $controllerName, $params);
        return $this;
    }

    /**
    * Authorize logic for current action controller, based on arguments
    *
    * Use $this->_action to fetch current action
    *
    * @param array $args
    */
    public function authorize($args=array())
    {
        return true;
    }

    /**
    * Execute before dispatch and return resutl
    * If false, do not dispatch action, and either forward or default
    *
    * @return bool
    */
    public function beforeDispatch()
    {
        return true;
    }

    /**
    * Execute after dispatch
    *
    */
    public function afterDispatch()
    {

    }

    /**
    * Send error to the browser
    *
    * @param string $message to be in response
    * @return exit
    */
    public function sendError($message)
    {
        BResponse::i()->set($message)->status(503);
    }

    /**
    * Default unauthorized action
    *
    */
    public function action_unauthorized()
    {
        BResponse::i()->set("Unauthorized")->status(401);
    }

    /**
    * Default not found action
    *
    */
    public function action_noroute()
    {
        BResponse::i()->set("Route not found")->status(404);
    }

    /**
    * Render output
    *
    * Final method to be called in standard action method
    */
    public function renderOutput()
    {
        BResponse::i()->output();
    }
}

/**
* Action controller for JSON API actions
*/
class BJsonActionController extends BActionController
{
    /**
    * Holds object from JSON POST request
    *
    * @var object|array
    */
    public $request = false;

    /**
    * Response to be returned to the client as JSON
    *
    * @var object|array
    */
    public $result = array();

    /**
    * Before dispatch set content type to JSON, fetch request
    * and set default status success
    *
    */
    public function beforeDispatch()
    {
        BResponse::i()->contentType('json');
        $this->request = BRequest::i()->rawPost(true, true);
        $this->result = array('status'=>'success');
        return true;
    }

    /**
    * After dispatch output result as JSON
    *
    */
    public function afterDispatch()
    {
        BResponse::i()->set($this->result)->output();
    }

    /**
    * On error return status error and message
    *
    * @param string $message
    */
    public function sendError($message)
    {
        #header("HTTP/1.0 503 Service Unavailable");
        #header("Status: 503 Service Unavailable");
        $this->result['status'] = 'error';
        $this->result['error'] = array('message'=>$message);
        return false;
    }

    /**
    * On no route return JSON error
    *
    */
    public function action_noroute()
    {
        //header("HTTP/1.0 404 Not Found");
        //header("Status: 404 Not Found");
        $this->result['status'] = 'error';
        $this->result['error'] = array('message'=>'Route not found');
        BResponse::i()->output();
    }
}

/**
* Facility to handle request input
*/
class BRequest extends BClass
{
    /**
    * Route parameters
    *
    * Taken from route, ex:
    * Route: /part1/:param1/part2/:param2
    * Request: /part1/test1/param2/test2
    * $_params: array('param1'=>'test1', 'param2'=>'test2')
    *
    * @var array
    */
    protected $_params = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BRequest
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * On first invokation strip magic quotes in case magic_quotes_gpc = on
    *
    * @return BRequest
    */
    public function __construct()
    {
        $this->stripMagicQuotes();
    }

    /**
    * Client remote IP
    *
    * @return string
    */
    public function ip()
    {
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
    * Server local IP
    *
    * @return string
    */
    public function serverIp()
    {
        return !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
    }

    /**
    * Server host name
    *
    * @return string
    */
    public function serverName()
    {
        return !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    /**
    * Whether request is SSL
    *
    * @return bool
    */
    public function https()
    {
        return !empty($_SERVER['HTTPS']);
    }

    /**
    * Whether request is AJAX
    *
    * @return bool
    */
    public function xhr()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest';
    }

    /**
    * Request method:
    *
    * @return string GET|POST|HEAD|PUT|DELETE
    */
    public function method()
    {
        return !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    /**
    * Web root path for current application
    *
    * If request is /folder1/folder2/index.php, return /folder1/folder2/
    *
    * @return string
    */
    public function webRoot()
    {
        return !empty($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : null;
    }

    /**
    * Full base URL, including scheme and domain name
    *
    * @return string
    */
    public function baseUrl()
    {
        return ($this->https() ? 'https' : 'http').'://'.$this->serverName().$this->webRoot();
    }

    /**
    * Full request path, one part or slice of path
    *
    * @param int $offset
    * @param int $length
    * @return string
    */
    public function path($offset, $length=BNULL)
    {
        if (empty($_SERVER['PATH_INFO'])) {
            return null;
        }
        $path = explode('/', ltrim($_SERVER['PATH_INFO'], '/'));
        if (BNULL===$length) {
            return isset($path[$offset]) ? $path[$offset] : null;
        }
        return join('/', array_slice($path, $offset, true===$length ? null : $length));
    }

    /**
    * Raw path string
    *
    * @return string
    */
    public function rawPath()
    {
        return !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
    }

    /**
    * Request query variables
    *
    * @param string $key
    * @return array|string|null
    */
    public function get($key=null)
    {
        return is_null($key) ? $_GET : (isset($_GET[$key]) ? $_GET[$key] : null);
    }

    /**
    * Request query as string
    *
    * @return string
    */
    public function rawGet()
    {
        return !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    /**
    * Request POST variables
    *
    * @param string|null $key
    * @return array|string|null
    */
    public function post($key=null)
    {
        return is_null($key) ? $_POST : (isset($_POST[$key]) ? $_POST[$key] : null);
    }

    /**
    * Request raw POST text
    *
    * @param bool $json Receive request as JSON
    * @param bool $asObject Return as object vs array
    * @return object|array|string
    */
    public function rawPost($json=false, $asObject=false)
    {
        $post = file_get_contents('php://input');
        if ($post && $json) {
            $post = BUtil::fromJson($post, $asObject);
        }
        return $post;
    }

    /**
    * Request variable (GET|POST|COOKIE)
    *
    * @param string|null $key
    * @return array|string|null
    */
    public function request($key=null)
    {
        return is_null($key) ? $_REQUEST : (isset($_REQUEST[$key]) ? $_REQUEST[$key] : null);
    }

    /**
    * Set or retrieve cookie value
    *
    * @param string $name Cookie name
    * @param string $value Cookie value to be set
    * @param int $lifespan Optional lifespan, default from config
    * @param string $path Optional cookie path, default from config
    * @param string $domain Optional cookie domain, default from config
    */
    public function cookie($name, $value=BNULL, $lifespan=null, $path=null, $domain=null)
    {
        if (BNULL===$value) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
        if (is_null($value) || false===$value) {
            return $this->cookie($name, '', -1000);
        }

        $config = BConfig::i()->get('cookie');
        $lifespan = !is_null($lifespan) ? $lifespan : $config['timeout'];
        $path = !is_null($path) ? $path : $config['path'];
        $domain = !is_null($domain) ? $domain : $config['domain'];

        setcookie($name, $value, time()+$lifespan, $path, $domain);
        return $this;
    }

    /**
    * Get request referrer
    *
    * @see http://en.wikipedia.org/wiki/HTTP_referrer#Origin_of_the_term_referer
    * @param string $default default value to use in case there is no referrer available
    * @return string|null
    */
    public function referrer($default=null)
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $default;
    }

    /**
    * Initialize route parameters
    *
    * @param array $params
    */
    public function initParams(array $params)
    {
        $this->_params = $params;
        return $this;
    }

    /**
    * Return route parameter by name or all parameters as array
    *
    * @param string $key
    * @return array|string|null
    */
    public function params($key=BNULL)
    {
        return BNULL===$key ? $this->_params : (isset($this->_params[$key]) ? $this->_params[$key] : null);
    }

    /**
    * Sanitize input and assign default values
    *
    * Syntax: BRequest::i()->sanitize($post, array(
    *   'var1' => 'alnum', // return only alphanumeric components, default null
    *   'var2' => array('trim|ucwords', 'default'), // trim and capitalize, default 'default'
    *   'var3' => array('regex:/[^0-9.]/', '0'), // remove anything not number or .
    * ));
    *
    * @param array|object $data Array to be sanitized
    * @param array $config Configuration for sanitizing
    * @param bool $trim Whether to return only variables specified in config
    * @return array Sanitized result
    */
    public function sanitize($data, $config, $trim=true)
    {
        $data = (array)$data;
        if ($trim) {
            $data = array_intersect_key($data, $config);
        }
        foreach ($data as $k=>&$v) {
            $filter = is_array($config[$k]) ? $config[$k][0] : $config[$k];
            $v = $this->sanitizeOne($v, $filter);
        }
        unset($v);
        foreach ($config as $k=>$c) {
            if (!isset($data[$k])) {
                $data[$k] = is_array($c) ? $c[1] : null;
            }
        }
        return $data;
    }

    /**
    * Sanitize one variable based on specified filter(s)
    *
    * Filters:
    * - int
    * - positive
    * - float
    * - trim
    * - nohtml
    * - plain
    * - upper
    * - lower
    * - ucwords
    * - ucfirst
    * - urle
    * - urld
    * - alnum
    * - regex
    * - date
    * - datetime
    * - gmdate
    * - gmdatetime
    *
    * @param string $v Value to be sanitized
    * @param array|string $filter Filters as array or string separated by |
    * @return string Sanitized value
    */
    public function sanitizeOne($v, $filter)
    {
        if (is_array($v)) {
            foreach ($v as $k=>&$v1) {
                $v1 = $this->sanitizeOne($v1, $filter);
            }
            unset($v1);
            return $v;
        }
        if (!is_array($filter)) {
            $filter = explode('|', $filter);
        }
        foreach ($filter as $f) {
            if (strpos($f, ':')) {
                list($f, $p) = explode(':', $f, 2);
            } else {
                $p = null;
            }
            switch ($f) {
                case 'int': $v = (int)$v; break;
                case 'positive': $v = $v>0 ? $v : null; break;
                case 'float': $v = (float)$v; break;
                case 'trim': $v = trim($v); break;
                case 'nohtml': $v = htmlentities($v, ENT_QUOTES); break;
                case 'plain': $v = htmlentities($v, ENT_NOQUOTES); break;
                case 'upper': $v = strtoupper($v); break;
                case 'lower': $v = strtolower($v); break;
                case 'ucwords': $v = ucwords($v); break;
                case 'ucfirst': $v = ucfirst($v); break;
                case 'urle': $v = urlencode($v); break;
                case 'urld': $v = urldecode($v); break;
                case 'alnum': $p = !empty($p)?$p:'_'; $v = preg_replace('#[^a-z0-9'.$p.']#i', '', $v); break;
                case 'regex': case 'regexp': $v = preg_replace($p, '', $v); break;
                case 'date': $v = date('Y-m-d', strtotime($v)); break;
                case 'datetime': $v = date('Y-m-d H:i:s', strtotime($v)); break;
                case 'gmdate': $v = gmdate('Y-m-d', strtotime($v)); break;
                case 'gmdatetime': $v = gmdate('Y-m-d H:i:s', strtotime($v)); break;
            }
        }
        return $v;
    }

    /**
    * String magic quotes in case magic_quotes_gpc = on
    *
    * @return BRequest
    */
    public function stripMagicQuotes()
    {
        static $alreadyRan = false;
        if (get_magic_quotes_gpc() && !$alreadyRan) {
            $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
            while (list($key, $val) = each($process)) {
                foreach ($val as $k => $v) {
                    unset($process[$key][$k]);
                    if (is_array($v)) {
                        $process[$key][stripslashes($k)] = $v;
                        $process[] = &$process[$key][stripslashes($k)];
                    } else {
                        $process[$key][stripslashes($k)] = stripslashes($v);
                    }
                }
            }
            unset($process);
            $alreadyRan = true;
        }
        return $this;
    }
}

/**
* Facility to handle response to client
*/
class BResponse extends BClass
{
    /**
    * Response content MIME type
    *
    * @var string
    */
    protected $_contentType = 'text/html';

    protected $_contentPrefix;

    protected $_contentSuffix;

    /**
    * Content to be returned to client
    *
    * @var mixed
    */
    protected $_content;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BResponse
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Escape HTML
    *
    * @param string $str
    * @return string
    */
    public static function q($str)
    {
        if (is_null($str)) {
            return '';
        }
        if (!is_scalar($str)) {
            var_dump($str);
            return ' ** ERROR ** ';
        }
        return htmlspecialchars($str);
    }

    /**
    * Alias for BRequest::i()->cookie()
    *
    * @param string $name
    * @param string $value
    * @param int $lifespan
    * @param string $path
    * @param string $domain
    * @return BResponse
    */
    public function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        BRequest::i()->cookie($name, $value, $lifespan, $path, $domain);
        return $this;
    }

    /**
    * Set response content
    *
    * @param mixed $content
    */
    public function set($content)
    {
        $this->_content = $content;
        return $this;
    }

    /**
    * Add content to response
    *
    * @param mixed $content
    */
    public function add($content)
    {
        $this->_content = (array)$this->_content+(array)$content;
        return $this;
    }

    /**
    * Set or retrieve response content MIME type
    *
    * @param string $type 'json' will expand to 'application/json'
    * @return BResponse|string
    */
    public function contentType($type=BNULL)
    {
        if (BNULL===$type) {
            return $this->_contentType;
        }
        if ($type=='json') {
            $type = 'application/json';
        }
        $this->_contentType = $type;
        return $this;
    }

    /**
    * Set or retrieve response content prefix string
    *
    * @param string $string
    * @return BResponse|string
    */
    public function contentPrefix($string=BNULL)
    {
        if (BNULL===$string) {
            return $this->_contentPrefix;
        }
        $this->_contentPrefix = $string;
        return $this;
    }

    /**
    * Set or retrieve response content suffix string
    *
    * @param string $string
    * @return BResponse|string
    */
    public function contentSuffix($string=BNULL)
    {
        if (BNULL===$string) {
            return $this->_contentSuffix;
        }
        $this->_contentSuffix = $string;
        return $this;
    }

    /**
    * Send file download to client
    *
    * @param string $filename
    * @return exit
    */
    public function sendFile($filename)
    {
        BSession::i()->close();
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($filename));
        header('Last-Modified: ' . date('r'));
        header('Content-Disposition: attachment; filename=' . basename($filename));
        $fs = fopen($filename, 'rb');
        $fd = fopen('php://output', 'wb');
        while (!feof($fs)) fwrite($fd, fread($fs, 8192));
        fclose($fs);
        fclose($fd);
        $this->shutdown(__METHOD__);
    }

    /**
    * Send text content as a file download to client
    *
    * @param string $content
    * @return exit
    */
    public function sendContent($content, $filename='download.txt')
    {
        BSession::i()->close();
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . strlen($content));
        header('Last-Modified: ' . date('r'));
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $content;
        $this->shutdown(__METHOD__);
    }

    /**
    * Send status response to client
    *
    * @param int $status Status code number
    * @param string $message Message to be sent to client
    * @param bool $output Proceed to output content and exit
    * @return BResponse|exit
    */
    public function status($status, $message=null, $output=true)
    {
        if (is_null($message)) {
            switch ((int)$status) {
                case 301: $message = 'Moved Permanently'; break;
                case 302: $message = 'Moved Temporarily'; break;
                case 303: $message = 'See Other'; break;
                case 401: $message = 'Unauthorized'; break;
                case 404: $message = 'Not Found'; break;
                case 503: $message = 'Service Unavailable'; break;
                default: $message = 'Unknown';
            }
        }
        header("HTTP/1.0 {$status} {$message}");
        header("Status: {$status} {$message}");
        if ($output) {
            $this->output();
        }
        return $this;
    }

    /**
    * Output the response to client
    *
    * @param string $type Optional content type
    * @return exit
    */
    public function output($type=null)
    {
        BEventRegistry::i()->dispatch('BResponse::output.before');
        if (!is_null($type)) {
            $this->contentType($type);
        }
        BSession::i()->close();
        header('Content-Type: '.$this->_contentType);
        if ($this->_contentType=='application/json') {
            $this->_content = is_string($this->_content) ? $this->_content : BUtil::toJson($this->_content);
        } elseif (is_null($this->_content)) {
            $this->_content = BLayout::i()->render();
        }

        echo $this->_contentPrefix;
        print_r($this->_content);
        echo $this->_contentSuffix;

        if ($this->_contentType=='text/html' && !BRequest::i()->xhr()) {
            echo "<hr>DELTA: ".BDebug::i()->delta().', PEAK: '.memory_get_peak_usage(true).', EXIT: '.memory_get_usage(true);
        }
        $this->shutdown(__METHOD__);
    }

    /**
    * Alias for output
    *
    */
    public function render()
    {
        $this->output();
    }

    /**
    * Redirect browser to another URL
    *
    * @param string $url URL to redirect
    * @param int $status Default 302, another possible value 301
    */
    public function redirect($url, $status=302)
    {
        BSession::i()->close();
        $this->status($status, null, false);
        header("Location: {$url}");
        $this->shutdown(__METHOD__);
    }

    public function shutdown($lastMethod=null)
    {
        BEventRegistry::i()->dispatch('BResponse::shutdown', array('last_method'=>$lastMethod));
        exit;
    }
}

/**
* Layout facility to register views and render output from views
*/
class BLayout extends BClass
{
    /**
    * View objects registry
    *
    * @var array
    */
    protected $_views = array();

    /**
    * Main (root) view to be rendered first
    *
    * @var BView
    */
    protected $_mainViewName = 'main';

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BLayout
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Set root dir for view templates, relative to current module root
    *
    * @param string $rootDir
    * @return BLayout
    */
    public function viewRootDir($rootDir)
    {
        $module = BModuleRegistry::i()->currentModule();
        $isAbsPath = strpos($rootDir, '/')===0 || strpos($rootDir, ':')===1;
        $module->view_root_dir = $isAbsPath ? $rootDir : $module->root_dir.'/'.$rootDir;
        return $this;
    }

    /**
    * Find and register all templates within a folder as view objects
    *
    * View objects will be named by template file paths, stripped of extension (.php)
    *
    * @param string $rootDir Folder with view templates, relative to current module root
    * @param string $prefix Optional: add prefix to view names
    * @return BLayout
    */
    public function allViews($rootDir, $prefix='')
    {
        $rootDir = BModuleRegistry::i()->currentModule()->root_dir.'/'.$rootDir;
        $this->viewRootDir($rootDir);
        $files = glob($rootDir.'/*');
        if (!$files) {
            return $this;
        }
        for ($i=0; $i<count($files); $i++) {
            if (is_dir($files[$i])) {
                $add = glob($files[$i] . '/*');
                $files = array_merge($files, $add);
            }
        }

        foreach ($files as $file) {
            if (preg_match('#^('.preg_quote($rootDir.'/', '#').')(.*)(\.php)$#', $file, $m)) {
                $this->view($prefix.$m[2], array('template'=>$m[2].$m[3]));
            }
        }
        return $this;
    }

    /**
    * Register or retrieve a view object
    *
    * @param string $viewname
    * @param array $params View parameters
    *   - template: optional, for templated views
    *   - view_class: optional, for custom views
    *   - module_name: optional, to use template from a specific module
    * @return BView|BLayout
    */
    public function view($viewname, $params=BNULL)
    {
        if (is_array($viewname)) {
            foreach ($viewname as $i=>$view) {
                if (!is_numeric($i)) {
                    throw new BException(BApp::t('Invalid argument: %s', print_r($viewname,1)));
                }
                $this->view($view[0], $view[1]);
            }
            return $this;
        }
        if (BNULL===$params) {
            if (!isset($this->_views[$viewname])) {
                return null;
            }
            return $this->_views[$viewname];
        }
        if (empty($params['module_name']) && ($moduleName = BModuleRegistry::currentModuleName())) {
            $params['module_name'] = $moduleName;
        }
        if (!isset($this->_views[$viewname]) || !empty($params['view_class'])) {
            $this->_views[$viewname] = BView::factory($viewname, $params);
        } else {
            $this->_views[$viewname]->param($params);
        }
        return $this;
    }

    /**
    * Set or retrieve main (root) view object
    *
    * @param string $viewname
    * @return BView|BLayout
    */
    public function mainView($viewname=BNULL)
    {
        if (BNULL===$viewname) {
            return $this->_mainViewName ? $this->view($this->_mainViewName) : null;
        }
        if (empty($this->_views[$viewname])) {
            throw new BException(BApp::t('Invalid view name for main view: %s', $viewname));
        }
        $this->_mainViewName = $viewname;
        return $this;
    }

    /**
    * Dispatch layout event, for both general observers and route specific observers
    *
    * Observers should watch for these events:
    * - BLayout::{event}
    * - BLayout::{event}: GET {route}
    *
    * @param mixed $eventName
    * @param mixed $routeName
    * @param mixed $args
    */
    public function dispatch($eventName, $routeName=null, $args=array())
    {
        if (is_null($routeName)) {
            $route = BFrontController::i()->currentRoute();
            if (!$route) {
                return array();
            }
            $routeName = $route['route_name'];
            $args['route_name'] = $route['route_name'];
            $result = BEventRegistry::i()->dispatch("BLayout::{$eventName}", $args);
        } else {
            $result = array();
        }
        $routes = is_string($routeName) ? explode(',', $routeName) : $routeName;
        foreach ($routes as $route) {
            $args['route_name'] = $route;
            $r2 = BEventRegistry::i()->dispatch("BLayout::{$eventName}: {$route}", $args);
            $result = BUtil::arrayMerge($result, $r2);
        }
        return $result;
    }

    /**
    * Render layout starting with main (root) view
    *
    * @param string $routeName Optional: render a specific route, default current route
    * @param BView|BLayout $args Render arguments
    * @return mixed
    */
    public function render($routeName=null, $args=array())
    {
        $this->dispatch('render.before', $routeName, $args);

        $mainView = $this->mainView();
        if (!$mainView) {
            throw new BException(BApp::t('Main view not found: %s', $this->_mainViewName));
        }
        $result = $mainView->render($args);

        $args['output'] =& $result;
        $this->dispatch('render.after', $routeName, $args);

        return $result;
    }

    public function debugPrintViews()
    {
        foreach ($this->_views as $viewname=>$view) {
            echo $viewname.':<pre>'; print_r($view); echo '</pre><hr>';
        }
    }
}

/**
* First parent view class
*/
class BView extends BClass
{
    /**
    * Default class for the view, overridden in child classes
    *
    * @var string
    */
    static protected $_defaultClass = __CLASS__;

    /**
    * View parameters
    * - view_class
    * - template
    * - module_name
    * - args
    *
    * @var array
    */
    protected $_params;

    /**
    * Factory to generate view instances
    *
    * @param string $viewname
    * @param array $params
    */
    static public function factory($viewname, array $params)
    {
        $params['viewname'] = $viewname;
        $className = !empty($params['view_class']) ? $params['view_class'] : static::$_defaultClass;
        $view = BClassRegistry::i()->instance($className, $params);
        return $view;
    }

    /**
    * Constructor, set initial view parameters
    *
    * @param array $params
    * @return BView
    */
    public function __construct(array $params)
    {
        $this->_params = $params;
    }

    /**
    * Set or retrieve view parameters
    *
    *
    *
    * @param string $key
    * @param string $value
    * @return mixed|BView
    */
    public function param($key=BNULL, $value=BNULL)
    {
        if (BNULL===$key) {
            return $this->_params;
        }
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->param($k, $v);
            }
            return $this;
        }
        if (BNULL===$value) {
            return isset($this->_params[$key]) ? $this->_params[$key] : null;
        }
        $this->_params[$key] = $value;
        return $this;
    }

    /**
    * Magic method to retrieve argument, accessible from view/template as $this->var
    *
    * @param string $name
    * @return mixed
    */
    public function __get($name)
    {
        return isset($this->_params['args'][$name]) ? $this->_params['args'][$name] : null;
    }

    /**
    * Magic method to set argument, stored in params['args']
    *
    * @param string $name
    * @param mixed $value
    */
    public function __set($name, $value)
    {
        $this->_params['args'][$name] = $value;
    }

    /**
    * Magic method to check if argument is set
    *
    * @param string $name
    */
    public function __isset($name)
    {
        return isset($this->_params['args'][$name]);
    }

    /**
    * Magic method to unset argument
    *
    * @param string $name
    */
    public function __unset($name)
    {
        unset($this->_pararms['args'][$name]);
    }

    /**
    * Retrieve view object
    *
    * @todo detect multi-level circular references
    * @param string $viewname
    * @return BModule
    */
    public function view($viewname)
    {
        if ($viewname===$this->param('name')) {
            throw new BException(BApp::t('Circular reference detected: %s', $viewname));
        }

        return BLayout::i()->view($viewname);
    }

    /**
    * View class specific rendering
    *
    * @return string
    */
    protected function _render()
    {
        $module = BModuleRegistry::i()->currentModule();
        $template = ($module ? $module->view_root_dir.'/' : '');
        $template .= ($tpl = $this->param('template')) ? $tpl : ($this->param('name').'.php');
        ob_start();
        include $template;
        return ob_get_clean();
    }

    /**
    * General render public method
    *
    * @param array $args
    * @return string
    */
    public function render(array $args=array())
    {
        if ($this->param('raw_text')!==null) {
            return $this->param('raw_text');
        }
        foreach ($args as $k=>$v) {
            $this->_params['args'][$k] = $v;
        }
        if (($modName = $this->param('module_name'))) {
            BModuleRegistry::i()->currentModule($modName);
        }
        $result = $this->_render();
        if ($modName) {
            BModuleRegistry::i()->currentModule(null);
        }
        return $result;
    }

    /**
    * Clear parameters to avoid circular reference memory leaks
    *
    */
    public function clear()
    {
        unset($this->_params);
    }

    /**
    * Clear params on destruct
    *
    */
    public function __destruct()
    {
        $this->clear();
    }

    /**
    * Render as string
    *
    * If there's exception during render, output as string as well
    *
    * @return string
    */
    public function __toString()
    {
        try {
            $result = $this->render();
        } catch (PDOException $e) {
            $result = '<hr>'.get_class($e).': '.$e->getMessage().'<hr>'.ORM::get_last_query().'<hr>';
        } catch (Exception $e) {
            $result = '<hr>'.get_class($e).': '.$e->getMessage().'<hr>';
        }
        return $result;
    }

    /**
    * Escape HTML
    *
    * @param string $str
    * @return string
    */
    public function q($str)
    {
        if (is_null($str)) {
            return '';
        }
        if (!is_string($str)) {
            var_dump($str);
            return ' ** ERROR ** ';
        }
        return htmlspecialchars($str);
    }
}

/**
* View dedicated for rendering HTML HEAD tags
*/
class BViewHead extends BView
{
    /**
    * Default view class name
    *
    * @var string
    */
    static protected $_defaultClass = __CLASS__;

    /**
    * Meta tags
    *
    * @var array
    */
    protected $_meta = array();

    /**
    * External resources (JS and CSS)
    *
    * @var array
    */
    protected $_elements = array();

    /**
    * Default tag templates for JS and CSS resources
    *
    * @var array
    */
    protected $_defaultTag = array(
        'js' => '<script type="text/javascript" src="%s"></script>',
        'css' => '<link rel="stylesheet" type="text/css" href="%s"/>',
    );

    /**
    * Current IE <!--[if]--> context
    *
    * @var string
    */
    protected $_currentIfContext = null;

    /**
    * Add meta tag, or return meta tag(s)
    *
    * @param string $name If not specified, will return all meta tags as string
    * @param string $content If not specified, will return meta tag by name
    * @param bool $httpEquiv Whether the tag is http-equiv
    * @return BViewHead
    */
    public function meta($name=BNULL, $content=BNULL, $httpEquiv=false)
    {
        if (BNULL===$name) {
            return join("\n", $this->_meta);
        }
        if (BNULL===$content) {
            return !empty($this->_meta[$name]) ? $this->_meta[$name] : null;
        }
        if ($httpEquiv) {
            $this->_meta[$name] = '<meta http-equiv="'.$name.'" content="'.htmlspecialchars($content).'" />';
        } else {
            $this->_meta[$name] = '<meta name="'.$name.'" content="'.htmlspecialchars($content).'" />';
        }
        return $this;
    }

    /**
    * Add external resource (JS or CSS), or return tag(s)
    *
    * @param string $type 'js' or 'css'
    * @param string $name name of the resource, if ommited, return all tags
    * @param array $args Resource arguments, if ommited, return tag by name
    *   - tag: Optional, tag template
    *   - file: resource file src or href
    *   - module_name: Optional: module where the resource is declared
    *   - if: IE <!--[if]--> context
    * @return BViewHead|array|string
    */
    protected function _externalResource($type, $name=BNULL, $args=BNULL)
    {
        if (BNULL===$name) {
            if (empty($this->_elements[$type])) {
                return '';
            }
            $result = '';
            foreach ($this->_elements[$type] as $name=>$args) {
                $result .= $this->_externalResource($type, $name, BNULL)."\n";
            }
            return $result;
        }
        if (BNULL===$args) {
            if (empty($this->_elements[$type][$name])) {
                return null;
            }
            $args = $this->_elements[$type][$name];
            $tag = !empty($args['tag']) ? $args['tag'] : $this->_defaultTag[$type];
            $file = !empty($args['file']) ? $args['file'] : $name;
            if (strpos($file, 'http:')===false && strpos($file, 'https:')===false) {
                $module = !empty($args['module_name']) ? BModuleRegistry::i()->module($args['module_name']) : null;
                $baseUrl = $module ? $module->base_url : BApp::baseUrl();
                $file = $baseUrl.'/'.$file;
            }
            $result = str_replace('%s', htmlspecialchars($file), $tag);
            if (!empty($args['if'])) {
                $result = '<!--[if '.$args['if'].']>'.$result.'<![endif]-->';
            }
            return $result;
        } elseif (!is_array($args)) {
            throw new BException(BApp::t('Invalid %s args: %s', array(strtoupper($type), print_r($args, 1))));
        }
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $args['module_name'] = $moduleName;
        }
        if ($this->_currentIfContext) {
            $args['if'] = $this->_currentIfContext;
        }
        $this->_elements[$type][$name] = $args;
        return $this;
    }

    /**
    * Add or return JS resources
    *
    * @param string $name If ommited, return all JS tags
    * @param array $args If ommited, return tag by $name
    * @return BViewHead|array|string
    */
    public function js($name=BNULL, $args=BNULL)
    {
        return $this->_externalResource('js', $name, $args);
    }

    /**
    * Add or return CSS resources
    *
    * @param string $name If ommited, return all CSS tags
    * @param array $args If ommited, return tag by $name
    * @return BViewHead|array|string
    */
    public function css($name=BNULL, $args=BNULL)
    {
        return $this->_externalResource('css', $name, $args);
    }

    /**
    * Start/Stop IE if context
    *
    * @param mixed $context
    */
    public function ifContext($context=null)
    {
        $this->_currentIfContext = $context;
        return $this;
    }

    /**
    * Render the view
    *
    * If param['template'] is not specified, return meta+css+js tags
    *
    * @param array $args
    * @return string
    */
    public function render(array $args=array())
    {
        if (!$this->param('template')) {
            return $this->meta()."\n".$this->css()."\n".$this->js();
        }
        return parent::render($args);
    }
}

/**
* View subclass to store and render lists of views
*/
class BViewList extends BView
{
    /**
    * Default view class name
    *
    * @var mixed
    */
    static protected $_defaultClass = __CLASS__;

    /**
    * Child blocks
    *
    * @var array
    */
    protected $_children = array();

    /**
    * Last registered position to sort children
    *
    * @var int
    */
    protected $_lastPosition = 0;

    /**
    * Append block to the list
    *
    * @param string|array $viewname array or comma separated list of view names
    * @param array $params
    * @return BViewList
    */
    public function append($viewname, array $params=array())
    {
        if (is_string($viewname)) {
            $viewname = explode(',', $viewname);
        }
        if (isset($params['position'])) {
            $this->_lastPosition = $params['position'];
        }
        foreach ($viewname as $v) {
            $params['name'] = $v;
            $params['position'] = $this->_lastPosition++;
            $this->_children[] = $params;
        }
        return $this;
    }

    /**
    * Append plain text to the list
    *
    * A new view object will be created for each text entry with random name
    *
    * @param string $text
    * @return BViewList
    */
    public function appendText($text)
    {
        $layout = BLayout::i();
        for ($viewname = md5(mt_rand()); $layout->view($viewname); );
        $layout->view($viewname, array('raw_text'=>(string)$text));
        $this->append($viewname);
        return $this;
    }

    /**
    * Find child view by its content
    *
    * May be slow, use sparringly
    *
    * @param string $content
    * @return BView|null
    */
    public function find($content)
    {
        foreach ($this->_children as $i=>$child) {
            $view = $this->view($child['name']);
            if (strpos($view->render(), $content)!==false) {
                return $view;
            }
        }
        return null;
    }

    /**
    * Remove child view from the list
    *
    * @param string $viewname
    * @return BViewList
    */
    public function remove($viewname)
    {
        if (true===$viewname) {
            $this->_children = array();
            return $this;
        }
        foreach ($this->_children as $i=>$child) {
            if ($child['name']==$viewname) {
                unset($this->_children[$i]);
                break;
            }
        }
        return $this;
    }

    /**
    * Render the children views
    *
    * @param array $args
    * @return string
    */
    public function render(array $args=array())
    {
        $output = array();
        uasort($this->_children, array($this, 'sortChildren'));
        $layout = BLayout::i();
        foreach ($this->_children as $child) {
            $childView = $layout->view($child['name']);
            if (!$childView) {
                throw new BException(BApp::t('Invalid view name: %s', $child['name']));
            }
            $output[] = $childView->render($args);
        }
        return join('', $output);
    }

    /**
    * Sort child views by their position
    *
    * @param mixed $a
    * @param mixed $b
    */
    public function sortChildren($a, $b)
    {
        return $a['position']<$b['position'] ? -1 : ($a['position']>$b['position'] ? 1 : 0);
    }
}

/**
* Facility to handle session state
*/
class BSession extends BClass
{
    /**
    * Session data, specific to the application namespace
    *
    * @var array
    */
    public $data = null;

    /**
    * Current sesison ID
    *
    * @var string
    */
    protected $_sessionId;

    /**
    * Whether PHP session is currently open
    *
    * @var bool
    */
    protected $_phpSessionOpen = false;

    /**
    * Whether any session variable was changed since last session save
    *
    * @var bool
    */
    protected $_dirty = false;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BSession
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Open session
    *
    * @param string|null $id Optional session ID
    * @param bool $close Close and unlock PHP session immediately
    */
    public function open($id=null, $autoClose=true)
    {
        if (!is_null($this->data)) {
            return $this;
        }
        $config = BConfig::i()->get('cookie');
        session_set_cookie_params($config['timeout'], $config['path'], $config['domain']);
        session_name($config['name']);
        if (!empty($id) || ($id = BRequest::i()->get('SID'))) {
            session_id($id);
        }
        @session_start();
        $this->_phpSessionOpen = true;
        $this->_sessionId = session_id();

        $namespace = $config['session_namespace'];
        $this->data = !empty($_SESSION[$namespace]) ? $_SESSION[$namespace] : array();

        if (!empty($this->data['_locale'])) {
            if (is_array($this->data['_locale'])) {
                foreach ($this->data['_locale'] as $c=>$l) {
                    setlocale($c, $l);
                }
            } elseif (is_string($this->data['_locale'])) {
                setlocale(LC_ALL, $this->data['_locale']);
            }
        }

        if (!empty($this->data['_timezone'])) {
            date_default_timezone_set($this->data['_timezone']);
        }

        if ($autoClose) {
            session_write_close();
            $this->_phpSessionOpen = false;
        }
        return $this;
    }

    /**
    * Set or retrieve dirty session flag
    *
    * @param bool $flag
    * @return bool
    */
    public function dirty($flag=BNULL)
    {
        if (BNULL===$flag) {
            return $this->_dirty;
        }
        $this->_dirty = $flag;
        return $this;
    }

    /**
    * Set or retrieve session variable
    *
    * @param string $key If ommited, return all session data
    * @param mixed $value If ommited, return data by $key
    * @return mixed|BSession
    */
    public function data($key=BNULL, $value=BNULL)
    {
        if (BNULL===$key) {
            return $this->data;
        }
        if (BNULL===$value) {
            return isset($this->data[$key]) ? $this->data[$key] : null;
        }
        if (!isset($this->data[$key]) || $this->data[$key]!==$value) {
            $this->dirty(true);
        }
        $this->data[$key] = $value;
        return $this;
    }

    /**
    * Get reference to session data and set dirty flag true
    *
    * @return array
    */
    public function &dataToUpdate()
    {
        $this->dirty(true);
        return $this->data;
    }

    /**
    * Write session variable changes and close PHP session
    *
    * @return BSession
    */
    public function close()
    {
        if (!$this->dirty()) {
            return;
        }
        if (!$this->_phpSessionOpen) {
            session_start();
        }
        $namespace = BConfig::i()->get('cookie/session_namespace');
        $_SESSION[$namespace] = $this->data;
        session_write_close();
        $this->_phpSessionOpen = false;
        $this->dirty(false);
        return $this;
    }

    /**
    * Get session ID
    *
    * @return string
    */
    public function sessionId()
    {
        return $this->_sessionId;
    }
}

/**
* Stub for cache class
*/
class BCache extends BClass
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BCache
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Stub
    *
    */
    public function init()
    {

    }
}

/**
* Facility to handle l10n and i18n
*/
class BLocale extends BClass
{
    /**
    * Default timezone
    *
    * @var string
    */
    protected $_defaultTz = 'America/Los_Angeles';

    /**
    * Default locale
    *
    * @var string
    */
    protected $_defaultLocale = 'en_US';

    /**
    * Cache for DateTimeZone objects
    *
    * @var DateTimeZone
    */
    protected $_tzCache = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BLocale
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Constructor, set default timezone and locale
    *
    */
    public function __construct()
    {
        date_default_timezone_set($this->_defaultTz);
        setlocale(LC_ALL, $this->_defaultLocale);
        $this->_tzCache['GMT'] = new DateTimeZone('GMT');
    }

    /**
    * Translate a string and inject optionally named arguments
    *
    * @param string $string
    * @param array $args
    * @return string|false
    */
    public function t($string, $args=array())
    {
        return BUtil::sprintfn($string, $args);
    }

    /**
    * Get server timezone
    *
    * @return string
    */
    public function serverTz()
    {
        return date('e'); // Examples: UTC, GMT, Atlantic/Azores
    }

    /**
    * Get timezone offset in seconds
    *
    * @param stirng|null $tz If null, return server timezone offset
    * @return int
    */
    public function tzOffset($tz=null)
    {
        if (is_null($tz)) { // Server timezone
            return date('O') * 36; //  x/100*60*60; // Seconds from GMT
        }
        if (empty($this->_tzCache[$tz])) {
            $this->_tzCache[$tz] = new DateTimeZone($tz);
        }
        return $this->_tzCache[$tz]->getOffset($this->_tzCache['GMT']);
    }

    /**
    * Convert local datetime to DB (GMT)
    *
    * @param string $value
    * @return string
    */
    public function datetimeLocalToDb($value)
    {
        return gmstrftime('%F %T', strtotime($value));
    }

    /**
    * Convert DB datetime (GMT) to local
    *
    * @param string $value
    * @param bool $full Full format or short
    * @return string
    */
    public function datetimeDbToLocal($value, $full=false)
    {
        return strftime($full ? '%c' : '%x', strtotime($value));
    }
}

class BUnit extends BClass
{
    protected $_currentTest;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BUnit
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function test($methods)
    {

    }
}
