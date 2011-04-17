<?php
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
    * Create new singleton or instance of the class
    *
    * @param bool $new
    * @param string $class
    */
    static public function instance($new=false, array $args=array(), $class=__CLASS__)
    {
        $registry = BClassRegistry::i();
        return $new ? $registry->getInstance($class, $args) : $registry->getSingleton($class, $args);
    }

    /**
    * Fallback singleton/instance factory
    *
    * Works correctly only in PHP 5.3.0
    * With PHP 5.2.0 will always return instance of BClass and should be overridden in child classes
    *
    * @param bool $new if true returns a new instance, otherwise singleton
    * @param array $args
    * @return BClass
    */
    public static function i($new=false, array $args=array())
    {
        if (function_exists('get_called_class')) {
            $class = function_exists('get_called_class') ? get_called_class() : __CLASS__;
        }
        return self::instance($new, $args, $class);
    }
}

/**
* Main BuckyBall Framework class
*
*/
class BApp extends BClass
{
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
        return self::instance($new, $args, __CLASS__);
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
        // initialize database connections
        BDb::i()->init();

        // load session variables
        BSession::i()->open();

        // bootstrap modules
        BModuleRegistry::i()->bootstrap();

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
    public static function log($message, $args, $data=array())
    {
        $data['message'] = self::t($message, $args);
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
        return self::instance($new, $args, __CLASS__);
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
        if (($module = BModuleRegistry::i()->currentModule())) {
            $event['module'] = $module->name;
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
}

/**
* Utility class to parse and construct strings and data structures
*/
class BParser extends BClass
{
    /**
    * Default hash algorithm
    *
    * @var string default sha512 for strength and slowness
    */
    protected $_hashAlgo = 'sha512';

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BParser
    */
    public static function i($new=false, array $args=array())
    {
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Convert any data to JSON
    *
    * @param mixed $data
    * @return string
    */
    public function toJson($data)
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
    public function fromJson($json, $asObject=false)
    {
        $obj = json_decode($json);
        return $asObject ? $obj : $this->objectToArray($obj);
    }

    /**
    * Convert object to array recursively
    *
    * @param object $d
    * @return array
    */
    public function objectToArray($d)
    {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }
        if (is_array($d)) {
            return array_map(array($this, 'objectToArray'), $d);
        }
        return $d;
    }

    /**
    * Convert array to object
    *
    * @param mixed $d
    * @return object
    */
    public function arrayToObject($d)
    {
        if (is_array($d)) {
            return (object) array_map(array($this, 'arrayToObject'), $d);
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
    public function sprintfn($format, $args = array())
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
    * Ex: echo BParser::i()->injectVars('One :two :three', array('two'=>2, 'three'=>3))
    * Result: "One 2 3"
    *
    * @param string $str
    * @param array $vars
    * @return string
    */
    public function injectVars($str, $vars)
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
     public function arrayMerge() {
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
                     $base[$key] = $this->arrayMerge($base[$key], $append[$key]);
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
    public function arrayCompare(array $array1, array $array2)
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
                    $new = $this->arrayCompare($value, $array2[$key]);
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
    * @param string$algo
    * @return BParser|string
    */
    public function hashAlgo($algo=null)
    {
        if (is_null($algo)) {
            return $this->_hashAlgo;
        }
        $this->_hashAlgo = $algo;
        return $this;
    }

    /**
    * Generate random string
    *
    * @param int $strLen length of resulting string
    * @param string $chars allowed characters to be used
    */
    public function randomString($strLen=8, $chars='abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ23456789')
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
    public function saltedHash($string, $salt, $algo=null)
    {
        return hash($algo ? $algo : $this->_hashAlgo, $salt.$string);
    }

    /**
    * Generate fully composed salted hash
    *
    * Ex: sha512:<hashed-string-here>:<salt>
    *
    * @param string $string
    * @param string $salt
    * @param string $algo
    */
    public function fullSaltedHash($string, $salt=null, $algo=null)
    {
        $salt = !is_null($salt) ? $salt : $this->randomString();
        $algo = !is_null($algo) ? $algo : $this->_hashAlgo;
        return $algo.':'.$this->saltedHash($string, $salt).':'.$salt;
    }

    /**
    * Validate salted hash against original text
    *
    * @param string $string original text
    * @param string $storedHash fully composed salted hash
    */
    public function validateSaltedHash($string, $storedHash)
    {
        list($algo, $hash, $salt) = explode(':', $storedHash);
        return $hash===$this->saltedHash($string, $salt, $algo);
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
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Add configuration fragment to global tree
    *
    * @param array $config
    * @return BConfig
    */
    public function add(array $config)
    {
        $this->_config = BParser::i()->arrayMerge($this->_config, $config);
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
        $config = BParser::i()->fromJson(file_get_contents($filename));
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
class BDb extends BClass
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDb
    */
    public static function i($new=false, array $args=array())
    {
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Initialize main DB connection
    *
    */
    public function init()
    {
        $config = BConfig::i();
        if (($dsn = $config->get('db/dsn'))) {
            ORM::configure($dsn);
            ORM::configure('username', $config->get('db/username'));
            ORM::configure('password', $config->get('db/password'));
            ORM::configure('logging', $config->get('db/logging'));
        }
    }

    /**
    * DB friendly current date/time
    *
    * @return string
    */
    static public function now()
    {
        return gmstrftime('%F %T');
    }
}

/**
* ORM model base class
*/
class BModel extends Model
{
    /**
    * Model instance factory
    *
    * @param string $class_name
    * @return ORMWrapper
    */
    public static function factory($class_name)
    {
        $class_name = BClassRegistry::i()->className($class_name);
        return parent::factory($class_name);
    }

    /**
    * Optionally set model properties and save
    *
    * @param array $arr
    * @return bool
    */
    public function save(array $arr=array())
    {
        foreach ($arr as $k=>$v) {
            $this->set($k, $v);
        }
        return parent::save();
    }
    
    /**
    * Create new singleton or instance of the class
    *
    * @param bool $new
    * @param string $class
    */
    static public function instance($new=false, array $args=array(), $class=__CLASS__)
    {
        $registry = BClassRegistry::i();
        return $new ? $registry->getInstance($class, $args) : $registry->getSingleton($class, $args);
    }

    /**
    * Fallback singleton/instance factory
    *
    * Works correctly only in PHP 5.3.0
    * With PHP 5.2.0 will always return instance of BModel and should be overridden in child classes
    *
    * @param bool $new if true returns a new instance, otherwise singleton
    * @param array $args
    * @return BClass
    */
    public static function i($new=false, array $args=array())
    {
        if (function_exists('get_called_class')) {
            $class = function_exists('get_called_class') ? get_called_class() : __CLASS__;
        }
        return self::instance($new, $args, $class);
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
    * Method overrides
    *
    * @var array
    */
    protected $_methods = array();

    /**
    * Registry of singletons
    *
    * @var array
    */
    protected $_singletons = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BClassRegistry
    */
    public static function i($new=false, array $args=array())
    {
        if (!self::$_instance) {
            self::$_instance = new BClassRegistry;
        }
        $class = function_exists('get_called_class') ? get_called_class() : __CLASS__;
        return $new ? self::$_instance->getInstance($class) : self::$_instance->getSingleton($class);
    }

    /**
    * Override a class
    *
    * Usage: BClassRegistry::i()->override('BaseClass', 'MyClass');
    *
    * Remembering the module that overrode the class for debugging
    *
    * @param string $class Class to be overridden
    * @param string $newClass New class
    * @param bool $replaceSingleton If there's already singleton of overridden class, replace with new one
    * @return BClassRegistry
    */
    public function override($class, $newClass, $replaceSingleton=false)
    {
        $this->_classes[$class] = array(
            'class_name' => $newClass,
            'module_name' => BModuleRegistry::i()->currentModule(),
        );
        if ($replaceSingleton && !empty($this->_singletons[$class]) && get_class($this->_singletons[$class])!==$newClass) {
            $this->_singletons[$class] = $this->getInstance($newClass);
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
    * - BClassRegistry::i()->getInstance('BaseClass')
    * - BClassRegistry::i()->getSingleton('BaseClass')
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
            'module_name' => BModuleRegistry::i()->currentModule(),
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
    * @param mixed $class
    * @param mixed $method
    * @param mixed $callback
    * @param mixed $static
    */
    public function augmentMethod($class, $method, $callback, $static=false)
    {
        $this->_methods[$class][$static ? 1 : 0][$method]['augment'][] = array(
            'module_name' => BModuleRegistry::i()->currentModule(),
            'callback' => $callback,
        );
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

        $callback = !empty($this->_methods[$class][0][$method]['override'])
            ? $this->_methods[$class][0][$method]['override']['callback']
            : array($origObject, $method);

        array_unshift($origObject, $args);

        $result = call_user_func_array($callback, $args);
        
        if (!empty($this->_methods[$class][0][$method]['augment'])) {
            array_unshift($result, $args);
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
            array_unshift($result, $args);
            foreach ($this->_methods[$class][1][$method]['augment'] as $augment) {
                $result = call_user_func_array($augment['callback'], $args);
                $args[0] = $result;
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
    * Get a new instance of a class
    *
    * If at least one method of the class if overridden, returns decorator
    *
    * @param string $class
    * @param array $args
    * @return object
    */
    public function getInstance($class, array $args=array())
    {
        $className = $this->className($class);
        $instance = new $className($args);

        // if no methods are overridden, just return the instance
        if (empty($this->_methods[$class])) {
            return $instance;
        }

        // otherwise return decorator
        return $this->getInstance('BClassDecorator', array($instance));
    }

    /**
    * Get a class singleton
    *
    * @param string $class
    * @param array $args
    * @return object
    */
    public function getSingleton($class, array $args=array())
    {
        if (empty($this->_singletons[$class])) {
            $this->_singletons[$class] = $class===__CLASS__ ? self::$_instance : $this->getInstance($class, $args);
        }
        return $this->_singletons[$class];
    }
}


/**
* Decorator class to allow easy method overrides
*
*/
class BClassDecorator extends BClass
{
    /**
    * Contains the decorated (original) object
    *
    * @var object
    */
    protected $_decoratedComponent;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BClassDecorator
    */
    public static function i($new=false, array $args=array())
    {
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Decorator constructor, creates an instance of decorated class
    *
    * @param object|string $class
    * @return BClassDecorator
    */
    public function __construct($class)
    {
        $this->_decoratedComponent = is_string($class) ? BClassRegistry::instance($class) : $class;
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
        return self::instance($new, $args, __CLASS__);
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
        if (($module = BModuleRegistry::i()->currentModule())) {
            $observer['module_name'] = $module->name;
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
                    $observer['callback'][0] = BClassRegistry::i()->getSingleton($observer['callback'][0]);
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
    * Module dependencies tree
    *
    * @var array
    */
    protected $_moduleDepends = array();

    /**
    * Current module name, not BNULL when:
    * - In module bootstrap
    * - In observer
    * - In view
    *
    * @var string
    */
    protected $_currentModuleName = BNULL;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BModuleRegistry
    */
    public static function i($new=false, array $args=array())
    {
        return self::instance($new, $args, __CLASS__);
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
        $parser = BParser::i();
        foreach ($manifests as $file) {
            $json = file_get_contents($file);
            $manifest = $parser->fromJson($json);
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
            if (!empty($mod->depends['module'])) {
                $depends = $mod->depends;
                foreach ($depends['module'] as &$dep) {
                    if (is_string($dep)) {
                        $dep = array('name'=>$dep);
                    }
                    $this->_moduleDepends[$dep['name']][$modName] = $dep;
                }
                unset($dep);
                $mod->depends = $depends;
            }
        }
        // validate dependencies
        foreach ($this->_moduleDepends as $depName=>&$depends) {
            if (empty($this->_modules[$depName])) {
                foreach ($depends as $modName=>&$dep) {
                    if (empty($dep['action']) || $dep['action']!='ignore') {
                        $dep['error'] = array('type'=>'missing');
                    }
                }
                unset($dep);
                continue;
            }
            $depMod = $this->_modules[$depName];
            foreach ($depends as $modName=>&$dep) {
                if (!empty($dep['version'])) {
                    $depVer = $dep['version'];
                    if (!empty($depVer['from']) && version_compare($depMod->version, $depVer['from'], '<')
                        || !empty($depVer['to']) && version_compare($depMod->version, $depVer['to'], '>')
                        || !empty($depVer['exclude']) && in_array($depMod->version, (array)$depVer['exclude'])
                    ) {
                        $dep['error'] = array('type'=>'version');
                    }
                }
            }
            unset($dep);
        }
        unset($depends);
        // propagate dependencies into subdependent modules
        foreach ($this->_moduleDepends as $depName=>$depends) {
            foreach ($depends as $modName=>$dep) {
                if (!empty($dep['error']) && empty($dep['error']['propagated'])) {
                    $this->propagateDepends($modName, $dep);
                }
            }
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
    public function propagateDepends($modName, $dep)
    {
        $this->_modules[$modName]->error = 'depends';
        $dep['error']['propagated'] = true;
        if (!empty($this->_moduleDepends[$modName])) {
            foreach ($this->_moduleDepends[$modName] as $depName=>&$subDep) {
                $subDep['error'] = $dep['error'];
                $this->propagateDepends($depName, $error);
            }
            unset($dep);
        }
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
        uasort($this->_modules, array($this, 'sortCallback'));
        foreach ($this->_modules as $mod) {
            if (!empty($mod->errors)) {
                continue;
            }
            $this->currentModule($mod->name);
            include_once ($mod->root_dir.'/'.$mod->bootstrap['file']);
            call_user_func($mod->bootstrap['callback']);
        }
        BModuleRegistry::i()->currentModule(null);
        return $this;
    }

    /**
    * Sort modules by dependencies
    * 
    * @param BModule $mod
    * @param BModule $dep
    * @return int
    */
    public function sortCallback($mod, $dep)
    {
        if (!$mod->name || !$dep->name) {
            return 0;
            var_dump($mod); var_dump($dep);
        }
        if (!empty($this->_moduleDepends[$mod->name][$dep->name])) return -1;
        elseif (!empty($this->_moduleDepends[$dep->name][$mod->name])) return 1;
        return 0;
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
            return $this->_currentModuleName ? $this->module($this->_currentModuleName) : false;
        }
        $this->_currentModuleName = $name;
        return $this;
    }
}

/**
* Module object to store module manifest and other properties
*/
class BModule extends BClass
{
    /**
    * Module manifest and properties
    * 
    * @var mixed
    */
    protected $_params;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BModule
    */
    public static function i($new=false, array $args=array())
    {
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Constructor
    * 
    * @param array $params
    * @return BModule
    */
    public function __construct($params)
    {
        $this->_params = $params;
    }

    /**
    * Magic getter
    * 
    * @param string $name
    * @return mixed
    */
    public function __get($name)
    {
        return isset($this->_params[$name]) ? $this->_params[$name] : null;
    }

    /**
    * Magic setter
    * 
    * @param string $name
    * @param mixed $value
    */
    public function __set($name, $value)
    {
        $this->_params[$name] = $value;
    }

    /**
    * Retrieve or set parameters
    * 
    * If $key is not specified, return all parameters as array
    * If $value is not specified, return $key value
    * If $value specified set $key=$value and return BModule for chaining
    * 
    * @param string $key
    * @param mixed $value
    * @return mixed
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
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Save RESTful route in tree
    * 
    * @param array $tree reference to tree where to save the route
    * @param string $route "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
    * @param mixed $callback PHP callback
    * @param array $args Route arguments
    * @param mixed $multiple Allow multiple callbacks for the same route
    */
    public function saveRoute(&$tree, $route, $callback=null, $args=null, $multiple=false)
    {
        list($method, $route) = explode(' ', $route, 2);
        $route = ltrim($route, '/');

        $node =& $tree[$method];
        $routeArr = explode('/', $route);
        foreach ($routeArr as $r) {
            if ($r!=='' && $r[0]===':') {
                $node =& $node['/:'][$r];
            } else {
                $node =& $node['/'][$r==='' ? '__EMPTY__' : $r];
            }
        }
        $observer = array('callback'=>$callback);
        if (($module = BModuleRegistry::i()->currentModule())) {
            $observer['module_name'] = $module->name;
        }
        if (!empty($args)) {
            $observer['args'] = $args;
        }
        if ($multiple || empty($node['observers'])) {
            $node['observers'][] = $observer;
        } else {
            $node['observers'][0] = BParser::i()->arrayMerge($node['observers'][0], $observer);
        }
        unset($node);

        return $this;
    }

    /**
    * Find a route in the tree
    * 
    * @param array $tree Reference to the route tree
    * @param string $route RESTful route
    * @return array|null Route node or null if not found
    */
    public function findRoute(&$tree, $route=null)
    {
        if (is_null($route)) {
            $route = BRequest::i()->rawPath();
        }
        if (strpos($route, ' ')===false) {
            $method = BRequest::i()->method();
        } else {
            list($method, $route) = explode(' ', $route, 2);
        }
        if (empty($tree[$method])) {
            return null;
        }
        $requestArr = $route=='' ? array('') : explode('/', ltrim($route, '/'));
        $routeNode = $tree[$method];
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

        $this->saveRoute($this->_routeTree, $route, $callback, $args, false);

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
        $routeNode = $this->findRoute($this->_routeTree, $route);

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
            $controller = BClassRegistry::i()->getSingleton($controllerName);
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
        return self::instance($new, $args, __CLASS__);
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
            $post = BParser::i()->fromJson($post, $asObject);
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
    * @param array $data Array to be sanitized
    * @param array $config Configuration for sanitizing
    * @param bool $trim Whether to return only variables specified in config
    * @return array Sanitized result
    */
    public function sanitize($data, $config, $trim=true)
    {
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
        return self::instance($new, $args, __CLASS__);
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
    public function contentType($type=null)
    {
        if (is_null($type)) {
            return $this->_contentType;
        }
        if ($type=='json') {
            $type = 'application/json';
        }
        $this->_contentType = $type;
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
        exit;
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
        if (!is_null($type)) {
            $this->contentType($type);
        }
        BSession::i()->close();
        header('Content-Type: '.$this->_contentType);
        if ($this->_contentType=='application/json') {
            $this->_content = is_string($this->_content) ? $this->_content : BParser::i()->toJson($this->_content);
        } elseif (is_null($this->_content)) {
            $this->_content = BLayout::i()->render();
        }

        print_r($this->_content);
        if ($this->_contentType=='text/html') {
            echo "<hr>DELTA: ".BDebug::i()->delta().', PEAK: '.memory_get_peak_usage(true).', EXIT: '.memory_get_usage(true);
        }
        exit;
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
        return self::instance($new, $args, __CLASS__);
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
    * @return BModule|BModuleRegistry
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
        if (empty($params['module_name']) && ($module = BModuleRegistry::i()->currentModule())) {
            $params['module_name'] = $module->name;
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
            $result = BParser::i()->arrayMerge($result, $r2);
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
        $className = !empty($params['view_class']) ? $params['view_class'] : self::$_defaultClass;
        $view = BClassRegistry::i()->getInstance($className, $params);
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
                $baseUrl = $module ? $module->param('base_url') : BApp::baseUrl();
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
        if (($module = BModuleRegistry::i()->currentModule())) {
            $args['module_name'] = $module->name;
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
        $layout->view($viewname, array('raw_text'=>$text));
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
    protected $_open = false;
    
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
        return self::instance($new, $args, __CLASS__);
    }

    /**
    * Open session
    * 
    * @param string|null $id Optional session ID
    * @param bool $close Close and unlock PHP session immediately
    */
    public function open($id=null, $close=true)
    {
        if ($this->data) {
            return $this;
        }
        $config = BConfig::i()->get('cookie');
        session_set_cookie_params($config['timeout'], $config['path'], $config['domain']);
        session_name($config['name']);
        if (!empty($id) || ($id = BRequest::i()->get('SID'))) {
            session_id($id);
        }
        @session_start();
        $this->_open = true;
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

        if ($close) {
            session_write_close();
            $this->_open = false;
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
        $this->open();
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
        $this->open();
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
        $this->open();
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
        if (!$this->_open) {
            session_start();
        }
        $namespace = BConfig::i()->get('cookie/session_namespace');
        $_SESSION[$namespace] = $this->data;
        session_write_close();
        $this->_open = false;
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
        return self::instance($new, $args, __CLASS__);
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
        return self::instance($new, $args, __CLASS__);
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
        return BParser::i()->sprintfn($string, $args);
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
        return self::instance($new, $args, __CLASS__);
    }

    public function test($methods)
    {

    }
}
