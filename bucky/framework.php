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
        return $new ? $registry->getInstance($class) : $registry->getSingleton($class);
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
    * The first method to be ran in bootstrap index file.
    *
    * @return BApp
    */
    public static function init()
    {
        BDebug::i();
        return self::i();
    }

    /**
    * The last method to be ran in bootstrap index file.
    *
    * Performs necessary initializations and dispatches requested action.
    *
    */
    public static function run()
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
    * Shortcut to add configuration, used mostly from bootstrap index file
    *
    * @param array|string $config If string will load configuration from file
    */
    public static function config($config)
    {
        if (is_array($config)) {
            BConfig::i()->add($config);
        } elseif (is_string($config) && is_file($config)) {
            BConfig::i()->addFile($config);
        } else {
            throw new BException("Invalid configuration argument");
        }
    }

    /**
    * Shortcut to scan folders for module manifest files
    *
    * @param string|array $folders Relative path(s) to manifests. May include wildcards.
    */
    public static function load($folders='.')
    {
#echo "<pre>"; print_r(debug_backtrace()); echo "</pre>";
        if (is_string($folders)) {
            $folders = explode(',', $folders);
        }
        $modules = BModuleRegistry::i();
        foreach ($folders as $folder) {
            $modules->scan($folder);
        }
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
        foreach ($vars as $k=>$v) {
            $str = str_replace(':'.$k, $v, $str);
        }
        return $str;
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
        return $hash==$this->saltedHash($string, $salt, $algo);
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
    * Set few object properties in the same call
    *
    * @param array $arr
    * @return BModel
    */
    public function setFew(array $arr)
    {
        foreach ($arr as $k=>$v) {
            $this->set($k, $v);
        }
        return $this;
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
        if ($replaceExisting && !empty($this->_singletons[$class]) && get_class($this->_singletons[$class])!==$newClass) {
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
        $this->_methods[$class][$static ? 1 : 0][$method] = array(
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

        $callback = !empty($this->_methods[$class][0][$method])
            ? $this->_methods[$class][0][$method]['callback']
            : array($origObject, $method);

        array_unshift($origObject, $args);

        return call_user_func_array($callback, $args);
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
            ? $this->_methods[$class][1][$method]['callback']
            : array($class, $method);

        return call_user_func_array($callback, $args);
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
    * @param object $class
    * @return BClassDecorator
    */
    public function __construct($class)
    {
        $this->_decoratedComponent = BClassRegistry::instance($class);
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
    public function __callStatic($name, array $args)
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
                $this->_modules[$modName] = BModule::factory($params);
            }
        }
        return $this;
    }

    public function checkDepends()
    {
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
        foreach ($this->_moduleDepends as $depName=>&$depends) {
            if (empty($this->_modules[$depName])) {
                foreach ($depends as $modName=>&$dep) {
                    if ($dep['action']!='ignore') {
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
        foreach ($this->_moduleDepends as $depName=>$depends) {
            foreach ($depends as $modName=>$dep) {
                if (!empty($dep['error']) && empty($dep['error']['propagated'])) {
                    $this->propagateDepends($modName, $dep);
                }
            }
        }
        return $this;
    }

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

    public function module($name)
    {
        return isset($this->_modules[$name]) ? $this->_modules[$name] : null;
    }

    public function currentModule($name=BNULL)
    {
        if (BNULL===$name) {
            return $this->_currentModuleName ? $this->module($this->_currentModuleName) : false;
        }
        $this->_currentModuleName = $name;
        return $this;
    }
}

class BModule
{
    static protected $_defaultClass = __CLASS__;
    protected $_params;

    static public function factory($params)
    {
        $className = !empty($params['registry_class']) ? $params['registry_class'] : self::$_defaultClass;
        $module = BClassRegistry::i()->getInstance($className, $params);
        return $module;
    }

    public function __construct($params)
    {
        $this->_params = $params;
    }

    public function __get($name)
    {
        return isset($this->_params[$name]) ? $this->_params[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->_params[$name] = $value;
    }

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

class BFrontController extends BClass
{
    protected $_routes = array();
    protected $_defaultRoutes = array('default'=>array('callback'=>array('BActionController', 'noroute')));
    protected $_routeTree = array();
    protected $_urlTemplates = array();

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

    public function currentRoute()
    {
        return $this->_currentRoute;
    }

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

    public function url($name, $params = array())
    {
// not implemented because not needed yet
    }
}

class BActionController extends BClass
{
    public $params = array();

    protected $_action;
    protected $_forward;
    protected $_actionMethodPrefix = 'action_';

    public function view($viewname)
    {
        return BLayout::i()->view($viewname);
    }

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

    public function forward($actionName=null, $controllerName=null, $params=array())
    {
        if (is_null($actionName)) {
            return $this->_forward;
        }
        $this->_forward = array($actionName, $controllerName, $params);
        return $this;
    }

    public function authorize($args=array())
    {
        return true;
    }

    public function beforeDispatch()
    {
        return true;
    }

    public function afterDispatch()
    {

    }

    public function sendError($message)
    {
        BResponse::i()->set($message)->status(503);
    }

    public function action_unauthorized()
    {
        BResponse::i()->set("Unauthorized")->status(401);
    }

    public function action_noroute()
    {
        BResponse::i()->set("Route not found")->status(404);
    }

    public function renderOutput()
    {
        BResponse::i()->output();
    }
}

class BJsonActionController extends BActionController
{
    public $request = false;
    public $result = array();

    public function beforeDispatch()
    {
        BResponse::i()->contentType('json');
        $this->request = BRequest::i()->rawPost(true, true);
        $this->result = array('status'=>'success');
        return true;
    }

    public function afterDispatch()
    {
        BResponse::i()->set($this->result)->output();
    }

    public function sendError($message)
    {
        #header("HTTP/1.0 503 Service Unavailable");
        #header("Status: 503 Service Unavailable");
        $this->result['status'] = 'error';
        $this->result['error'] = array('message'=>$message);
        return false;
    }

    public function action_noroute()
    {
        //header("HTTP/1.0 404 Not Found");
        //header("Status: 404 Not Found");
        $this->result['status'] = 'error';
        $this->result['error'] = array('message'=>'Route not found');
        BResponse::i()->output();
    }
}

class BRequest extends BClass
{
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

    public function ip()
    {
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    public function serverIp()
    {
        return !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;

    }

    public function serverName()
    {
        return !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    public function https()
    {
        return !empty($_SERVER['HTTPS']);
    }

    public function xhr()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest';
    }

    public function method()
    {
        return !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    public function webRoot()
    {
        return !empty($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : null;
    }

    public function baseUrl()
    {
        return ($this->https() ? 'https' : 'http').'://'.$this->serverName().$this->webRoot();
    }

    public function path($offset, $length=BNULL)
    {
        if (empty($_SERVER['PATH_INFO'])) {
            return null;
        }
        $path = explode('/', ltrim($_SERVER['PATH_INFO'], '/'));
        if (BNULL===$toIdx) {
            return isset($path[$offset]) ? $path[$offset] : null;
        }
        return join('/', array_slice($path, $offset, true===$length ? null : $length));
    }

    public function rawPath()
    {
        return !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
    }

    public function get($key=null)
    {
        return is_null($key) ? $_GET : (isset($_GET[$key]) ? $_GET[$key] : null);
    }

    public function rawGet()
    {
        return !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    public function post($key=null)
    {
        return is_null($key) ? $_POST : (isset($_POST[$key]) ? $_POST[$key] : null);
    }

    public function rawPost($json=false, $asObject=false)
    {
        $post = file_get_contents('php://input');
        if ($post && $json) {
            $post = BParser::i()->fromJson($post, $asObject);
        }
        return $post;
    }

    public function request($key=null)
    {
        return is_null($key) ? $_REQUEST : (isset($_REQUEST[$key]) ? $_REQUEST[$key] : null);
    }

    public function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        if (is_null($value)) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
        if (false===$value) {
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

    public function initParams($params)
    {
        $this->_params = $params;
        return $this;
    }

    public function params($key=null)
    {
        return is_null($key) ? $this->_params : (isset($this->_params[$key]) ? $this->_params[$key] : null);
    }

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
}

class BResponse extends BClass
{
    protected $_contentType = 'text/html';
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

    public function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        BRequest::i()->cookie($name, $value, $lifespan, $path, $domain);
        return $this;
    }

    public function set($content)
    {
        $this->_content = $content;
        return $this;
    }

    public function add($content)
    {
        $this->_content = (array)$this->_content+(array)$content;
        return $this;
    }

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

    public function output($type=null)
    {
        if (!is_null($type)) {
            $this->contentType($type);
        }
        BSession::i()->close();
        header('Content-Type: '.$this->_contentType);
        if ($this->_contentType=='application/json') {
            $this->_content = BParser::i()->toJson($this->_content);
        } elseif (is_null($this->_content)) {
            $this->_content = BLayout::i()->render();
        }

        print_r($this->_content);
        if ($this->_contentType=='text/html') {
            echo "<hr>DELTA: ".BDebug::i()->delta().', PEAK: '.memory_get_peak_usage(true).', EXIT: '.memory_get_usage(true);
        }
        exit;
    }

    public function render()
    {
        $this->output();
    }

    public function redirect($url, $status=302)
    {
        BSession::i()->close();
        $this->status($status, null, false);
        header("Location: {$url}");
        exit;
    }
}

class BLayout extends BClass
{
    protected $_routeTree = array();
    protected $_singletons = array();
    protected $_views = array();
    protected $_mainViewName = 'main';
    protected $_currentStage;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BLayout
    */
    public static function i($new=false, array $args=array())
    {
        return self::instance($new, $args, __CLASS__);
    }

    public function allViews($rootDir)
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
                $this->view($m[2], array('template'=>$m[2].$m[3]));
            }
        }
        return $this;
    }

    public function viewRootDir($rootDir)
    {
        $module = BModuleRegistry::i()->currentModule();
        $module->view_root_dir = strpos($rootDir, '/')===0 ? $rootDir : $module->root_dir.'/'.$rootDir;
        return $this;
    }

    public function view($viewname, $params=null)
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
        if (is_null($params)) {
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

    public function mainView($viewname=null)
    {
        if (is_null($viewname)) {
            return $this->_mainViewName ? $this->view($this->_mainViewName) : null;
        }
        if (empty($this->_views[$viewname])) {
            throw new BException(BApp::t('Invalid view name for main view: %s', $viewname));
        }
        $this->_mainViewName = $viewname;
        return $this;
    }

    public function dispatch($eventName, $routeName=null, $args=array())
    {
        if (is_null($routeName)) {
            $route = BFrontController::i()->currentRoute();
            if (!$route) {
                return array();
            }
            $routeName = $route['route_name'];
            $args['route_name'] = $route['route_name'];
            $result = BEventRegistry::i()->dispatch("layout.{$eventName}", $args);
        } else {
            $result = array();
        }
        $routes = is_string($routeName) ? explode(',', $routeName) : $routeName;
        foreach ($routes as $route) {
            $args['route_name'] = $route;
            $r2 = BEventRegistry::i()->dispatch("layout.{$eventName}: {$route}", $args);
            $result = BParser::i()->arrayMerge($result, $r2);
        }
        return $result;
    }

    public function currentStage($stage=null)
    {
        if (is_null($stage)) {
            return $this->_currentStage;
        }
        $this->_currentStage = $stage;
        return $this;
    }

    public function render($routeName=null, $args=array())
    {
        $this->dispatch('render.before', $routeName, $args);

        $this->currentStage('render');
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

class BView
{
    static protected $_defaultClass = __CLASS__;
    protected $_params;

    static public function factory($viewname, $params)
    {
        $params['viewname'] = $viewname;
        $className = !empty($params['view_class']) ? $params['view_class'] : self::$_defaultClass;
        $view = BClassRegistry::i()->getInstance($className, $params);
        return $view;
    }

    public function __construct($params)
    {
        $this->_params = $params;
    }

    public function param($key=null, $value=BNULL)
    {
        if (is_null($key)) {
            return $this->_params;
        }
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->param($k, $v);
            }
            return $this;
        }
        if ($value===BNULL) {
            return isset($this->_params[$key]) ? $this->_params[$key] : null;
        }
        $this->_params[$key] = $value;
        return $this;
    }

    public function __get($name)
    {
        return isset($this->_params['args'][$name]) ? $this->_params['args'][$name] : null;
    }

    public function __set($name, $value)
    {
        $this->_params['args'][$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->_params['args'][$name]);
    }

    public function __unset($name)
    {
        unset($this->_pararms['args'][$name]);
    }

    public function view($viewname)
    {
        if ($viewname===$this->param('name')) {
            throw new BException(BApp::t('Circular reference detected: %s', $viewname));
        }

        return BLayout::i()->view($viewname);
    }

    public function render($args=array())
    {
        if ($this->param('raw_text')!==null) {
            return $this->param('raw_text');
        }
        foreach ($args as $k=>$v) {
            $this->_params['args'][$k] = $v;
        }
        if ($this->param('module_name')) {
            BModuleRegistry::i()->currentModule($this->param('module_name'));
        }
        $module = BModuleRegistry::i()->currentModule();
        $template = $module ? $module->view_root_dir.'/' : '';
        if ($this->param('template')) {
            $template .= $this->param('template');
        } else {
            $template .= $this->param('name').'.php';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }

    public function clear()
    {
        unset($this->_params);
    }

    public function __destruct()
    {
        $this->clear();
    }

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

    public function q($str)
    {
        return htmlspecialchars($str);
    }
}

class BViewHead extends BView
{
    static protected $_defaultClass = __CLASS__;

    protected $_meta = array();
    protected $_js = array();
    protected $_css = array();
    protected $_elements = array();
    protected $_defaultTag = array(
        'js' => '<script type="text/javascript" src="%s"></script>',
        'css' => '<link rel="stylesheet" type="text/css" href="%s"/>',
    );
    protected $_currentIfContext = null;

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

    protected function _externalResource($type, $name, $args)
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

    public function js($name=BNULL, $args=BNULL)
    {
        return $this->_externalResource('js', $name, $args);
    }

    public function css($name=BNULL, $args=BNULL)
    {
        return $this->_externalResource('css', $name, $args);
    }

    public function ifContext($context=null)
    {
        $this->_currentIfContext = $context;
        return $this;
    }

    public function render($args=array())
    {
        if (!$this->param('template')) {
            return $this->meta()."\n".$this->css()."\n".$this->js();
        }
        return parent::render($args);
    }
}

class BViewList extends BView
{
    static protected $_defaultClass = __CLASS__;

    protected $_children = array();
    protected $_lastPosition = 0;

    public function append($viewname, $params=array())
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

    public function appendText($text)
    {
        $parser = BParser::i();
        $layout = BLayout::i();
        for ($viewname = md5(mt_rand()); $layout->view($viewname); );
        $layout->view($viewname, array('raw_text'=>$text));
        $this->append($viewname);
        return $this;
    }

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

    public function render($args=array())
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

    public function sortChildren($a, $b)
    {
        return $a['position']<$b['position'] ? -1 : ($a['position']>$b['position'] ? 1 : 0);
    }
}

class BSession extends BClass
{
    public $data = null;

    protected $_company;
    protected $_location;

    protected $_sessionId;
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

    public function open($id=null, $close=true)
    {
        if (!is_null($this->data)) {
            return;
        }
        $config = BConfig::i()->get('cookie');
        session_set_cookie_params($config['timeout'], $config['path'], $config['domain']);
        session_name($config['name']);
        if (!empty($id) || ($id = BRequest::i()->get('SID'))) {
            session_id($id);
        }
        session_start();

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
        }
    }

    public function dirty($flag=null)
    {
        if (is_null($flag)) {
            return $this->_dirty;
        }
        $this->open();
        $this->_dirty = $flag;
    }

    public function data($key=null, $value=null)
    {
        $this->open();
        if (is_null($key)) {
            return $this->data;
        }
        if (is_null($value)) {
            return isset($this->data[$key]) ? $this->data[$key] : null;
        }
        if (!isset($this->data[$key]) || $this->data[$key]!==$value) {
            $this->dirty(true);
        }
        $this->data[$key] = $value;
        return $this;
    }

    public function &dataToUpdate()
    {
        $this->open();
        $this->dirty(true);
        return $this->data;
    }

    public function close()
    {
        if (!$this->dirty()) {
            return;
        }
        session_start();
        $namespace = BConfig::i()->get('cookie/session_namespace');
        $_SESSION[$namespace] = $this->data;
        session_write_close();
        $this->dirty(false);
    }

    public function sessionId()
    {
        return $this->_sessionId;
    }
}

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

    public function init()
    {

    }
}

class BLocale extends BClass
{
    protected $_defaultTz = 'America/Los_Angeles';
    protected $_defaultLocale = 'en_US';
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

    public function __construct()
    {
        date_default_timezone_set($this->_defaultTz);
        setlocale(LC_ALL, $this->_defaultLocale);
        $this->_tzCache['GMT'] = new DateTimeZone('GMT');
    }

    public function t($string, $args)
    {
        return BParser::i()->sprintfn($string, $args);
    }

    public function serverTz()
    {
        return date('e'); // Examples: UTC, GMT, Atlantic/Azores
    }

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

    public function datetimeLocalToDb($value)
    {
        return gmstrftime('%F %T', strtotime($value));
    }

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
