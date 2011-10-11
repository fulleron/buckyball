<?php

/**
* Sometimes NULL is a value too.
*/
define('BNULL', 'THIS IS A DUMMY VALUE TO DISTINCT BETWEEN LACK OF ARGUMENT/VALUE AND PHP NULL VALUE');

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
    * @param bool|object $new if true returns a new instance, otherwise singleton
    *                         if object, returns singleton of the same class
    * @param array $args
    * @return BClass
    */
    public static function i($new=false, array $args=array())
    {
        if (is_object($new)) {
            $class = get_class($new);
            $new = false;
        } else {
            $class = get_called_class();
        }
        return BClassRegistry::i()->instance($class, $args, !$new);
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
    * Shortcut to get a current module or module by name
    *
    * @param string $modName
    * @return BModule
    */
    public static function m($modName=null)
    {
        $reg = BModuleRegistry::i();
        return is_null($modName) ? $reg->currentModule() : $reg->module($modName);
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
        $class = get_called_class();
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
        if (!class_exists($className, true)) {
            throw new BException(BApp::t('Invalid class name: %s', $className));
        }
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
    * observe|watch|on|subscribe ?
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
    * Alias for observe()
    *
    * @param string|array $eventName
    * @param mixed $callback
    * @param array $args
    * @return BEventRegistry
    */
    public function on($eventName, $callback=null, $args=array())
    {
        return $this->observe($eventName, $callback, $args);
    }

    /**
    * Dispatch event observers
    *
    * dispatch|fire|notify ?
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
                if (is_string($observer['callback'])) {
                    $r = explode('.', $observer['callback']);
                    if (sizeof($r)==2) {
                        $observer['callback'] = array($r[0]::i(), $r[1]);
                    }
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

    /**
    * Alias for dispatch()
    *
    * @param string|array $eventName
    * @param array $args
    * @return array Collection of results from observers
    */
    public function fire($eventName, $args=array())
    {
        return $this->dispatch($eventName, $args);
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

    public function pop($key)
    {
        $data = $this->data($key);
        $this->data($key, null);
        return $data;
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

    /**
    * Add session message
    *
    * @param string $msg
    * @param string $type
    * @param string $tag
    * @return BSession
    */
    public function addMessage($msg, $type='info', $tag='_')
    {
        $this->dirty(true);
        $this->data['_messages'][$tag][] = array('msg'=>$msg, 'type'=>$type);
        return $this;
    }

    /**
    * Return any buffered messages for a tag and clear them from session
    *
    * @param string $tags comma separated
    * @return array
    */
    public function messages($tags='_')
    {
        if (empty($this->data['_messages'])) {
            return array();
        }
        $tags = explode(',', $tags);
        $msgs = array();
        foreach ($tags as $tag) {
            if (empty($this->data['_messages'][$tag])) continue;
            foreach ($avail[$tag] as $i=>$m) {
                $msgs[] = $m;
                unset($this->data['_messages'][$tag][$i]);
                $this->dirty(true);
            }
        }
        return $msgs;
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
    * Generate random string based on pattern
    *
    * Syntax: {ULD10}-{U5}
    * - U: upper case letters
    * - L: lower case letters
    * - D: digits
    *
    * @param string $pattern
    * @return string
    */
    public static function randomPattern($pattern)
    {
        static $chars = array('L'=>'bcdfghjkmnpqrstvwxyz', 'U'=>'BCDFGHJKLMNPQRSTVWXYZ', 'D'=>'123456789');

        while (preg_match('#\{([ULD]+)([0-9]+)\}#i', $pattern, $m)) {
            for ($i=0, $c=''; $i<strlen($m[1]); $i++) $c .= $chars[$m[1][$i]];
            $pattern = preg_replace('#'.preg_quote($m[0]).'#', BUtil::randomString($m[2], $c), $pattern, 1);
        }
        return $pattern;
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
            foreach ($fields as $k) $result[$k] = isset($source[$k]) ? $source[$k] : null;
        } else {
            foreach ($source as $k=>$v) if (!in_array($k, $fields)) $result[$k] = $v;
        }
        return $result;
    }

    /**
    * Send simple POST request to external server and retrieve response
    *
    * @param string $url
    * @param array $data
    * @return string
    */
    public static function post($url, $data) {
        $request = http_build_query($data);
        $opts = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                    ."Content-Length: ".strlen($request)."\r\n",
                'content' => $request,
                'timeout' => 5,
            ),
        );
        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);
        parse_str($response, $result);
        return $result;
    }

    public static function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        if (strpos($path, '/..')!==false) {
            $a = explode('/', $path);
            $b = array();
            foreach ($a as $p) {
                if ($p==='..') array_pop($b); else $b[] = $p;
            }
            $path = join('/', $b);
        }
        return $path;
    }
}
