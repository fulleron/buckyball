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
*
* @package BuckyBall
* @link http://github.com/unirgy/buckyball
* @author Boris Gurvich <boris@unirgy.com>
* @copyright (c) 2010-2012 Boris Gurvich
* @license http://www.apache.org/licenses/LICENSE-2.0.html
*/

define('BNULL', '!@BNULL#$');

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
    * Original class to be used as event prefix to remain constant in overridden classes
    *
    * Usage:
    *
    * class Some_Class extends BClass
    * {
    *    static protected $_origClass = __CLASS__;
    * }
    *
    * @var string
    */
    static protected $_origClass;

    /**
    * Retrieve original class name
    *
    * @return string
    */
    public static function origClass()
    {
        return static::$_origClass;
    }

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

    public function __call($name, $args)
    {
        return BClassRegistry::i()->callMethod($this, $name, $args, static::$_origClass);
    }

    public static function __callStatic($name, $args)
    {
        return BClassRegistry::i()->callStaticMethod(get_called_class(), $name, $args, static::$_origClass);
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
    * Flags whether vars shouldn't be changed
    *
    * @var mixed
    */
    protected $_isConst = array();

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
            BDebug::error(BLocale::_('Unknown feature: %s', $feature));
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
     * @param bool  $new
     * @param array $args
     * @return BApp
     */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Application constructor
    *
    * Starts debugging session for timing
    *
    * @return BApp
    */
    public function __construct()
    {
        BDebug::i();
        umask(0);
    }

    /**
     * Shortcut to add configuration, used mostly from bootstrap index file
     *
     * @param array|string $config If string will load configuration from file
     * @return $this
     */
    public function config($config)
    {
        if (is_array($config)) {
            BConfig::i()->add($config);
        } elseif (is_string($config) && is_file($config)) {
            BConfig::i()->addFile($config);
        } else {
            BDebug::error("Invalid configuration argument");
        }
        return $this;
    }

    /**
     * Shortcut to scan folders for module manifest files
     *
     * @param string|array $folders Relative path(s) to manifests. May include wildcards.
     * @return $this
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

        // run module migration scripts if necessary
        if (BConfig::i()->get('db/implicit_migration')) {
            BMigrate::i()->migrateModules(true);
        }

        // dispatch requested controller action
        BRouting::i()->dispatch();

        // If session variables were changed, update session
        BSession::i()->close();

        return $this;
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
        return Blocale::_($string, $args);
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
     * @param bool $full whether the URL should include schema and host
     * @param int  $method
     *   1 : use config for full url
     *   2 : use entry point for full url
     * @return string
     */
    public static function baseUrl($full=true, $method=1)
    {
        static $baseUrl = array();
        $full = (int)$full;
        $key = $full.'|'.$method;
        if (empty($baseUrl[$key])) {
            /** @var BRequest */
            $r = BRequest::i();
            $c = BConfig::i();
            $scriptPath = pathinfo($r->scriptName());
            switch ($method) {
                case 1:
                    $url = $c->get('web/base_href');
                    if (!$url) {
                        $url = $scriptPath['dirname'];
                    }
                    break;
                case 2:
                    $url = $scriptPath['dirname'];
                    break;
            }

            if (!($r->modRewriteEnabled() && $c->get('web/hide_script_name'))) {
                $url = rtrim($url, "\\"); //for windows installation
                $url = rtrim($url, '/') . '/' . $scriptPath['basename'];
            }
            if ($full) {
                $url = $r->scheme().'://'.$r->httpHost().$url;
            }

            $baseUrl[$key] = rtrim($url, '/').'/';
        }

        return $baseUrl[$key];
    }

    /**
    * Shortcut to generate URL of module base and custom path
    *
    * @deprecated by href() and src()
    * @param string $modName
    * @param string $url
    * @param string $method
    * @return string
    */
    public static function url($modName, $url='', $method='baseHref')
    {
        $m = BApp::m($modName);
        if (!$m) {
            BDebug::error('Invalid module: '.$modName);
            return '';
        }
        return $m->$method() . $url;
    }

    public static function href($url='', $full=true, $method=2)
    {
        return BApp::baseUrl($full, $method)
            . BRouting::processHref($url);
    }

    /**
    * Shortcut to generate URL with base src (js, css, images, etc)
    *)
    * @param string $modName
    * @param string $url
    * @param string $method
    * @return string
    */
    public static function src($modName, $url='', $method='baseSrc')
    {
        if ($modName[0]==='@' && !$url) {
            list($modName, $url) = explode('/', substr($modName, 1), 2);
        }
        $m = BApp::m($modName);
        if (!$m) {
            BDebug::error('Invalid module: '.$modName);
            return '';
        }
        return $m->$method() . '/' . rtrim($url, '/');
    }

    public function set($key, $val, $const=false)
    {
        if (!empty($this->_isConst[$key])) {
            BDebug::warning('Trying to reset a constant var: '.$key.' = '.$val);
            return $this;
        }
        $this->_vars[$key] = $val;
        if ($const) $this->_isConst[$key] = true;
        return $this;
    }

    public function get($key)
    {
        return isset($this->_vars[$key]) ? $this->_vars[$key] : null;
    }

    /**
     * Helper to get class singletons and instances from templates like Twig
     *
     * @param string $class
     * @param boolean $new
     * @param array $args
     * @return BClass
     */
    public function instance($class, $new=false, $args=array())
    {
        return $class::i($new, $args);
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
        //BApp::log($message, array(), array('event'=>'exception', 'code'=>$code, 'file'=>$this->getFile(), 'line'=>$this->getLine()));
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
    * Configuration that will be saved on request
    *
    * @var array
    */
    protected $_configToSave = array();

    /**
    * Enable double data storage for saving?
    *
    * @var boolean
    */
    protected $_enableSaving = true;

    protected $_encryptedPaths = array();

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
    * @param boolean $toSave whether this config should be saved in file
    * @return BConfig
    */
    public function add(array $config, $toSave=false)
    {
        $this->_config = BUtil::arrayMerge($this->_config, $config);
        if ($this->_enableSaving && $toSave) {
            $this->_configToSave = BUtil::arrayMerge($this->_configToSave, $config);
        }
        return $this;
    }

    /**
    * Add configuration from file, stored as JSON
    *
    * @param string $filename
    */
    public function addFile($filename, $toSave=false)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
#echo "<pre>"; print_r($this); echo "</pre>";
        if (!BUtil::isPathAbsolute($filename)) {
            $configDir = $this->get('fs/config_dir');
            if (!$configDir) {
                $configDir = BConfig::i()->get('fs/config_dir');
            }
            $filename = $configDir.'/'.$filename;
        }
        if (!is_readable($filename)) {
            BDebug::error(BLocale::_('Invalid configuration file name: %s', $filename));
        }

        switch ($ext) {
        case 'php':
            $config = include($filename);
            break;

        case 'yml':
            $config = BYAML::i()->load($filename);
            break;

        case 'json':
            $config = BUtil::fromJson(file_get_contents($filename));
            break;
        }
        if (!is_array($config)) {
            BDebug::error(BLocale::_('Invalid configuration contents: %s', $filename));
        }
        $this->add($config, $toSave);
        return $this;
    }

    public function setPathEncrypted($path)
    {
        $this->_encryptedPaths[$path] = true;
        return $this;
    }

    public function shouldBeEncrypted($path)
    {
        return !empty($this->_encryptedPaths[$path]);
    }

    /**
     * Set configuration data in $path location
     *
     * @param string  $path slash separated path to the config node
     * @param mixed   $value scalar or array value
     * @param boolean $merge merge new value to old?
     * @param bool    $toSave
     * @return $this
     */
    public function set($path, $value, $merge=false, $toSave=false)
    {
        if (is_string($toSave) && $toSave==='_configToSave') { // limit?
            $node =& $this->$toSave;
        } else {
            $node =& $this->_config;
        }
        if ($this->shouldBeEncrypted($path)) {

        }
        foreach (explode('/', $path) as $key) {
            $node =& $node[$key];
        }
        if ($merge) {
            $node = BUtil::arrayMerge((array)$node, (array)$value);
        } else {
            $node = $value;
        }
        if ($this->_enableSaving && true===$toSave) {
            $this->set($path, $value, $merge, '_configToSave');
        }
        return $this;
    }

    /**
    * Get configuration data using path
    *
    * Ex: BConfig::i()->get('some/deep/config')
    *
    * @param string $path
    * @param boolean $toSave if true, get the configuration from config tree to save
    */
    public function get($path=null, $toSave=false)
    {
        $node = $toSave ? $this->_configToSave : $this->_config;
        if (is_null($path)) {
            return $node;
        }
        foreach (explode('/', $path) as $key) {
            if (!isset($node[$key])) {
                return null;
            }
            $node = $node[$key];
        }
        return $node;
    }

    public function writeFile($filename, $config=null, $format=null)
    {
        if (is_null($config)) {
            $config = $this->_configToSave;
        }
        if (is_null($format)) {
            $format = pathinfo($filename, PATHINFO_EXTENSION);
        }
        switch ($format) {
            case 'php':
                $contents = "<?php return ".var_export($config, 1).';';

                // Additional check for allowed tokens

                if ($this->isInvalidManifestPHP($contents)) {
                    throw new BException('Invalid tokens in configuration found');
                }

                // a small formatting enhancement
                $contents = preg_replace('#=> \n\s+#', '=> ', $contents);
                break;

            case 'yml':
                $contents = BYAML::i()->dump($config);
                break;

            case 'json':
                $contents = BUtil::i()->toJson($config);
                break;
        }

        if (!BUtil::isPathAbsolute($filename)) {
            $configDir = $this->get('fs/config_dir');
            if (!$configDir) {
                $configDir = BConfig::i()->get('fs/config_dir');
            }
            $filename = $configDir . '/' . $filename;
        }
        BUtil::ensureDir(dirname($filename));
        // Write contents
        if (!file_put_contents($filename, $contents, LOCK_EX)) {
            BDebug::error('Error writing configuration file: '.$filename);
        }
    }

    public function unsetConfig()
    {
        $this->_config = array();
    }

    public function isInvalidManifestPHP($contents)
    {
        $tokens = token_get_all($contents);
        $allowed = array(T_OPEN_TAG=>1, T_RETURN=>1, T_WHITESPACE=>1, T_COMMENT=>1,
                    T_ARRAY=>1, T_CONSTANT_ENCAPSED_STRING=>1, T_DOUBLE_ARROW=>1,
                    T_DNUMBER=>1, T_LNUMBER=>1, T_STRING=>1,
                    '('=>1, ','=>1, ')'=>1, ';'=>1);
        $denied = array();
        foreach ($tokens as $t) {
            if (is_string($t) && !isset($t)) {
                $denied[] = $t;
            } elseif (is_array($t) && !isset($allowed[$t[0]])) {
                $denied[] = token_name($t[0]).': '.$t[1]
                    .(!empty($t[2]) ? ' ('.$t[2].')':'');
            }
        }
        if (count($denied)) {
            return $denied;
        }
        return false;
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
    * Cache for method callbacks
    *
    * @var array
    */
    protected $_methodOverrideCache = array();

    /**
    * Classes that require decoration because of overridden methods
    *
    * @var array
    */
    protected $_decoratedClasses = array();

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
    * Overridden class should be called one of the following ways:
    * - BClassRegistry::i()->instance('BaseClass')
    * - BaseClass:i() -- if it extends BClass or has the shortcut defined
    *
    * Remembering the module that overrode the class for debugging
    *
    * @todo figure out how to update events on class override
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
            'module_name' => BModuleRegistry::i()->currentModuleName(),
        );
        BDebug::debug('OVERRIDE CLASS: '.$class.' -> '.$newClass);
        if ($replaceSingleton && !empty($this->_singletons[$class]) && get_class($this->_singletons[$class])!==$newClass) {
            $this->_singletons[$class] = $this->instance($newClass);
        }
        return $this;
    }

    /**
    * Dynamically add a class method
    *
    * @param string $class
    *   - '*' - will add method to all classes
    *   - 'extends AbstractClass' - will add method to all classes extending AbstractClass
    *   - 'implements Interface' - will add method to all classes implementing Interface
    * @param string $name
    * @param callback $callback
    * @return BClassRegistry
    */
    public function addMethod($class, $method, $callback, $static=false)
    {
        $arr = explode(' ', $class);
        if (!empty($arr[1])) {
            $rel = $arr[0];
            $class = $arr[1];
        } else {
            $rel = 'is';
        }
        $this->_methods[$method][$static ? 1 : 0]['override'][$rel][$class] = array(
            'module_name' => BModuleRegistry::i()->currentModuleName(),
            'callback' => $callback,
        );
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
    * @param string $class Class to be overridden
    * @param string $method Method to be overridden
    * @param mixed $callback Callback to invoke on method call
    * @param bool $static Whether the static method call should be overridden
    * @return BClassRegistry
    */
    public function overrideMethod($class, $method, $callback, $static=false)
    {
        $this->addMethod($class, $method, $callback, $static);
        $this->_decoratedClasses[$class] = true;
        return $this;
    }

    /**
    * Dynamically augment class method result
    *
    * Allows to change result of a method for every invocation.
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
        $this->_methods[$method][$static ? 1 : 0]['augment']['is'][$class][] = array(
            'module_name' => BModuleRegistry::i()->currentModuleName(),
            'callback' => $callback,
        );
        $this->_decoratedClasses[$class] = true;
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
             BDebug::error(BLocale::_('Invalid property augmentation operator: %s', $op));
        }
        if ($type!=='override' && $type!=='before' && $type!=='after') {
            BDebug::error(BLocale::_('Invalid property augmentation type: %s', $type));
        }
        $entry = array(
            'module_name' => BModuleRegistry::i()->currentModuleName(),
            'callback' => $callback,
        );
        if ($type==='override') {
            $this->_properties[$class][$property][$op.'_'.$type] = $entry;
        } else {
            $this->_properties[$class][$property][$op.'_'.$type][] = $entry;
        }
        //have to be added to redefine augmentProperty Setter/Getter methods
        $this->_decoratedClasses[$class] = true;
        return $this;
    }

    public function findMethodInfo($class, $method, $static=0, $type='override')
    {
        //$this->_methods[$method][$static ? 1 : 0]['override'][$rel][$class]
        if (!empty($this->_methods[$method][$static][$type]['is'][$class])) {
            //return $class;
            return $this->_methods[$method][$static][$type]['is'][$class];
        }
        $cacheKey = $class.'|'.$method.'|'.$static.'|'.$type;
        if (!empty($this->_methodOverrideCache[$cacheKey])) {
            return $this->_methodOverrideCache[$cacheKey];
        }
        if (!empty($this->_methods[$method][$static][$type]['extends'])) {
            $parents = class_parents($class);
#echo "<pre>"; echo $class.'::'.$method.';'; print_r($parents); print_r($this->_methods[$method][$static][$type]['extends']); echo "</pre><hr>";
            foreach ($this->_methods[$method][$static][$type]['extends'] as $c=>$v) {
                if (isset($parents[$c])) {
#echo ' * ';
                    $this->_methodOverrideCache[$cacheKey] = $v;
                    return $v;
                }
            }
        }
        if (!empty($this->_methods[$method][$static][$type]['implements'])) {
            $implements = class_implements($class);
            foreach ($this->_methods[$method][$static][$type]['implements'] as $i=>$v) {
                if (isset($implements[$i])) {
                    $this->_methodOverrideCache[$cacheKey] = $v;
                    return $v;
                }
            }
        }
        if (!empty($this->_methods[$method][$static][$type]['is']['*'])) {
            $v = $this->_methods[$method][$static][$type]['is']['*'];
            $this->_methodOverrideCache[$cacheKey] = $v;
            return $v;
        }
        return null;
    }

    /**
    * Check if the callback is callable, accounting for dynamic methods
    *
    * @param mixed $cb
    * @return boolean
    */
    public function isCallable($cb)
    {
        if (is_string($cb)) { // plain string callback?
            $cb = explode('::', $cb);
            if (empty($cb[1])) { // not static?
                $cb = BUtil::extCallback($cb); // account for special singleton syntax
            }
        } elseif (!is_array($cb)) { // unknown?
            return is_callable($cb);
        }
        if (empty($cb[1])) { // regular function?
            return function_exists($cb[0]);
        }
        if (method_exists($cb[0], $cb[1])) { // regular method?
            return true;
        }
        if (is_object($cb[0])) { // instance
            if (!$cb[0] instanceof BClass) { // regular class?
                return false;
            }
            return (bool)$this->findMethodInfo(get_class($cb[0]), $cb[1]);
        } elseif (is_string($cb[0])) { // static?
            return (bool)$this->findMethodInfo($cb[0], $cb[1], 1);
        } else { // unknown?
            return false;
        }
    }

    /**
    * Call overridden method
    *
    * @param object $origObject
    * @param string $method
    * @param mixed $args
    * @return mixed
    */
    public function callMethod($origObject, $method, array $args=array(), $origClass=null)
    {
        //$class = $origClass ? $origClass : get_class($origObject);
        $class = get_class($origObject);

        if (($info = $this->findMethodInfo($class, $method, 0, 'override'))) {
            $callback = $info['callback'];
            array_unshift($args, $origObject);
            $overridden = true;
        } elseif (method_exists($origObject, $method)) {
            $callback = array($origObject, $method);
            $overridden = false;
        } else {
            BDebug::error('Invalid method: '.get_class($origObject).'::'.$method);
            return null;
        }

        $result = call_user_func_array($callback, $args);

        if (($info = $this->findMethodInfo($class, $method, 0, 'augment'))) {
            if (!$overridden) {
                array_unshift($args, $origObject);
            }
            array_unshift($args, $result);
            foreach ($info as $augment) {
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
    public function callStaticMethod($class, $method, array $args=array(), $origClass=null)
    {
        if (($info = $this->findMethodInfo($class, $method, 1, 'override'))) {
            $callback = $info['callback'];
        } else {
            if (method_exists($class, $method)) {
                $callback = array($class, $method);
            } else {
                throw new Exception('Invalid static method: '.$class.'::'.$method);
            }
        }

        $result = call_user_func_array($callback, $args);

        if (($info = $this->findMethodInfo($class, $method, 1, 'augment'))) {
            array_unshift($args, $result);
            foreach ($info as $augment) {
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
//print_r($this->_properties);exit;
        if (!empty($this->_properties[$class][$property]['set_before'])) {
            foreach ($this->_properties[$class][$property]['set_before'] as $entry) {
                call_user_func($entry['callback'], $origObject, $property, $value);
            }
        }

        if (!empty($this->_properties[$class][$property]['set_override'])) {
            $callback = $this->_properties[$class][$property]['set_override']['callback'];
            call_user_func($callback, $origObject, $property, $value);
        } else {
            $origObject->$property = $value;
        }

        if (!empty($this->_properties[$class][$property]['set_after'])) {
            foreach ($this->_properties[$class][$property]['set_after'] as $entry) {
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

        if (!empty($this->_properties[$class][$property]['get_override'])) {
            $callback = $this->_properties[$class][$property]['get_override']['callback'];
            $result = call_user_func($callback, $origObject, $property);
        } else {
            $result = $origObject->$property;
        }

        if (!empty($this->_properties[$class][$property]['get_after'])) {
            foreach ($this->_properties[$class][$property]['get_after'] as $entry) {
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
            BDebug::error(BLocale::_('Invalid class name: %s', $className));
        }
        $instance = new $className($args);

        // if any methods are overridden or augmented, get decorator
        if (!empty($this->_decoratedClasses[$class])) {
            $instance = $this->instance('BClassDecorator', array($instance));
        }

        // if singleton is requested, save
        if ($singleton) {
            $this->_singletons[$class] = $instance;
        }

        return $instance;
    }

    public function unsetInstance()
    {
        static::$_instance = null;
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
    * @param array(object|string $class)
    * @return BClassDecorator
    */
    public function __construct($args)
    {
//echo '1: '; print_r($class);
        $class = array_shift($args);
        $this->_decoratedComponent = is_string($class) ? BClassRegistry::i()->instance($class, $args) : $class;
    }

    public function __destruct()
    {
        $this->_decoratedComponent = null;
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

    /**
     * Return object of decorated class
     * @return object
     */
    public function getDecoratedComponent()
    {
        return $this->_decoratedComponent;
    }
}

class BClassAutoload extends BClass
{
    public $root_dir;
    public $filename_cb;
    public $module_name;

    public function __construct($params)
    {
        foreach ($params as $k=>$v) {
            $this->$k = $v;
        }
        spl_autoload_register(array($this, 'callback'), false);
        BDebug::debug('AUTOLOAD: '.print_r($this,1));
    }

    /**
    * Default autoload callback
    *
    * @param string $class
    */
    public function callback($class)
    {
#echo $this->root_dir.' : '.$class.'<br>';
        if ($this->filename_cb) {
            $file = call_user_func($this->filename_cb, $class);
        } else {
            $file = str_replace(array('_', '\\'), array('/', '/'), $class).'.php';
        }
        if ($file) {
            if ($file[0]!=='/' && $file[1]!==':') {
                $file = $this->root_dir.'/'.$file;
            }
            if (file_exists($file)) {
                include ($file);
            }
        }
    }
}

/**
* Events and observers registry
*/
class BEvents extends BClass
{
    /**
    * Stores events and observers
    *
    * @todo figure out how to update events on class override
    *
    * @var array
    */
    protected $_events = array();

    /**
     * Shortcut to help with IDE autocompletion
     *
     * @param bool  $new
     * @param array $args
     * @return BEvents
     */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Declare event with default arguments in bootstrap function
    *
    * This method is optional and currently not used.
    *
    * @param string|array $eventName accepts multiple events in form of non-associative array
    * @param array|object $args
    * @return BEvents
    */
    public function event($eventName, $args=array())
    {
        if (is_array($eventName)) {
            foreach ($eventName as $event) {
                $this->event($event[0], !empty($event[1]) ? $event[1] : array());
            }
            return $this;
        }
        $eventName = strtolower($eventName);
        $this->_events[$eventName] = array(
            'observers' => array(),
            'args' => $args,
        );
        return $this;
    }

    /**
    * Declare observers in bootstrap function
    *
    * observe|watch|on|sub|subscribe ?
    *
    * @param string|array $eventName accepts multiple observers in form of non-associative array
    * @param mixed $callback
    * @param array|object $args
    * @return BEvents
    */
    public function on($eventName, $callback = null, $args = array(), $alias = null)
    {
        if (is_array($eventName)) {
            foreach ($eventName as $obs) {
                $this->on($obs[0], $obs[1], !empty($obs[2]) ? $obs[2] : array());
            }
            return $this;
        }
        if (is_null($alias) && is_string($callback)) {
            $alias = $callback;
        }
        $observer = array('callback' => $callback, 'args' => $args, 'alias' => $alias);
        if (($moduleName = BModuleRegistry::i()->currentModuleName())) {
            $observer['module_name'] = $moduleName;
        }
        //TODO: create named observers
        $eventName = strtolower($eventName);
        $this->_events[$eventName]['observers'][] = $observer;
        BDebug::debug('SUBSCRIBE '.$eventName, 1);
        return $this;
    }

    /**
     * Run callback on event only once, and remove automatically
     *
     * @param string|array $eventName accepts multiple observers in form of non-associative array
     * @param mixed $callback
     * @param array|object $args
     * @return BEvents
     */
    public function once($eventName, $callback=null, $args=array(), $alias = null)
    {
        if (is_array($eventName)) {
            foreach ($eventName as $obs) {
                $this->once($obs[0], $obs[1], !empty($obs[2]) ? $obs[2] : array());
            }
            return $this;
        }
        $this->on($eventName, $callback, $args, $alias);
        $lastId = sizeof($this->_events[$eventName]['observers']);
        $this->on($eventName, function() use ($eventName, $lastId) {
            BEvents::i()
                ->off($eventName, $lastId-1) // remove the observer
                ->off($eventName, $lastId) // remove the remover
            ;
        });
        return $this;
    }

    /**
    * Disable all observers for an event or a specific observer
    *
    * @param string $eventName
    * @param callback $callback
    * @return BEvents
    */
    public function off($eventName, $alias = null)
    {
        $eventName = strtolower($eventName);
        if (true === $alias) { //TODO: null too?
            unset($this->_events[$eventName]);
            return $this;
        }
        if (is_numeric($alias)) {
            unset($this->_events[$eventName]['observers'][$alias]);
            return $this;
        }
        if (!empty($this->_events[$eventName]['observers'])) {
            foreach ($this->_events[$eventName]['observers'] as $i=>$observer) {
                if (!empty($observer['alias']) && $observer['alias'] === $alias) {
                    unset($this->_events[$eventName]['observers'][$i]);
                }
            }
        }
        return $this;
    }

    /**
    * Dispatch event observers
    *
    * dispatch|fire|notify|pub|publish ?
    *
    * @param string $eventName
    * @param array|object $args
    * @return array Collection of results from observers
    */
    public function fire($eventName, $args=array())
    {
        $eventName = strtolower($eventName);
        $profileStart = BDebug::debug('FIRE '.$eventName.(empty($this->_events[$eventName])?' (NO SUBSCRIBERS)':''), 1);
        $result = array();
        if (empty($this->_events[$eventName])) {
            return $result;
        }
        $observers =& $this->_events[$eventName]['observers'];
        // sort order observers
        do {
            $dirty = false;
            foreach ($observers as $i=>$observer) {
                if (!empty($observer['args']['position']) && empty($observer['ordered'])) {
                    unset($observers[$i]);
                    $observer['ordered'] = true;
                    $observers = BUtil::arrayInsert($observers, $observer, $observer['position']);
                    $dirty = true;
                    break;
                }
            }
        } while ($dirty);

        foreach ($observers as $i=>$observer) {
            if (!empty($this->_events[$eventName]['args'])) {
                $args = array_merge($this->_events[$eventName]['args'], $args);
            }
            if (!empty($observer['args'])) {
                $args = array_merge($observer['args'], $args);
            }

            // Set current module to be used in observer callback
            if (!empty($observer['module_name'])) {
                BModuleRegistry::i()->pushModule($observer['module_name']);
            }

            $cb = $observer['callback'];

            // For cases like BView
            if (is_object($cb) && !$cb instanceof Closure) {
                if (method_exists($cb, 'set')) {
                    $cb->set($args);
                }
                $result[] = (string)$cb;
                continue;
            }

            // Special singleton syntax
            if (is_string($cb)) {
                foreach (array('.', '->') as $sep) {
                    $r = explode($sep, $cb);
                    if (sizeof($r)==2) {
if (!class_exists($r[0])) {
    echo "<pre>"; debug_print_backtrace(); echo "</pre>";
}
                        $cb = array($r[0]::i(), $r[1]);
                        $observer['callback'] = $cb;
                        // remember for next call, don't want to use &$observer
                        $observers[$i]['callback'] = $cb;
                        break;
                    }
                }
            }

            // Invoke observer
            if (is_callable($cb)) {
                BDebug::debug('ON '.$eventName/*.' : '.var_export($cb, 1)*/, 1);
                $result[] = call_user_func($cb, $args);
            } else {
                BDebug::warning('Invalid callback: '.var_export($cb, 1), 1);
            }

            if (!empty($observer['module_name'])) {
                BModuleRegistry::i()->popModule();
            }
        }
        BDebug::profile($profileStart);
        return $result;
    }

    public function fireRegexp($eventRegexp, $args)
    {
        $results = array();
        foreach ($this->_events as $eventName => $event) {
            if (preg_match($eventRegexp, $eventName)) {
                $results += (array)$this->fire($eventName, $args);
            }
        }
        return $results;
    }

    public function debug()
    {
        echo "<pre>"; print_r($this->_events); echo "</pre>";
    }
}

/**
 * Alias for backwards compatibility
 *
 * @deprecated by BEvents
 */
class BPubSub extends BEvents {}

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

    protected $_availableHandlers = array();

    protected $_defaultSessionCookieName = 'buckyball';
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BSession
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function addHandler($name, $class)
    {
        $this->_availableHandlers[$name] = $class;
    }

    public function getHandlers()
    {
        $handlers = array_keys($this->_availableHandlers);
        return array_combine($handlers, $handlers);
    }

    /**
     * Open session
     *
     * @param string|null $id Optional session ID
     * @param bool        $autoClose
     * @return $this
     */
    public function open($id=null, $autoClose=false)
    {
        if (!is_null($this->data)) {
            return $this;
        }
        $config = BConfig::i()->get('cookie');
        if (!empty($config['session_disable'])) {
            return $this;
        }

        $ttl = !empty($config['timeout']) ? $config['timeout'] : 3600;
        $path = !empty($config['path']) ? $config['path'] : BConfig::i()->get('web/base_href');
        if (empty($path)) $path = BRequest::i()->webRoot();

        $domain = !empty($config['domain']) ? $config['domain'] : BRequest::i()->httpHost(false);
        if (!empty($config['session_handler']) && !empty($this->_availableHandlers[$config['session_handler']])) {
            $class = $this->_availableHandlers[$config['session_handler']];
            $class::i()->register($ttl);
        }
        //session_set_cookie_params($ttl, $path, $domain);
        session_name(!empty($config['name']) ? $config['name'] : $this->_defaultSessionCookieName);
        if (($dir = BConfig::i()->get('fs/storage_dir'))) {
            $dir .= '/session';
            BUtil::ensureDir($dir);
            session_save_path($dir);
        }

        if (!empty($id) || ($id = BRequest::i()->get('SID'))) {
            session_id($id);
        }
        if (headers_sent()) {
            BDebug::warning("Headers already sent, can't start session");
        } else {
            session_set_cookie_params($ttl, $path, $domain);
            session_start();
            // update session cookie expiration to reflect current visit
            // @see http://www.php.net/manual/en/function.session-set-cookie-params.php#100657
            setcookie(session_name(), session_id(), time()+$ttl, $path, $domain);
        }
        $this->_phpSessionOpen = true;
        $this->_sessionId = session_id();

        if (!empty($config['session_check_ip'])) {
            $ip = BRequest::i()->ip();
            if (empty($_SESSION['_ip'])) {
                $_SESSION['_ip'] = $ip;
            } elseif ($_SESSION['_ip']!==$ip) {
                session_destroy();
                session_start();
                //BResponse::i()->status(403, "Remote IP doesn't match session", "Remote IP doesn't match session");
            }
        }

        $namespace = !empty($config['session_namespace']) ? $config['session_namespace'] : 'default';
        if (empty($_SESSION[$namespace])) {
            $_SESSION[$namespace] = array();
        }
        if ($autoClose) {
            $this->data = $_SESSION[$namespace];
        } else {
            $this->data =& $_SESSION[$namespace];
        }

        if (empty($this->data['_language'])) {
            $lang = BRequest::language();
            if (!empty($lang)) {
                $this->data['_language'] = $lang;
            }
        }

        $this->data['_locale'] = BConfig::i()->get('locale');
        if (!empty($this->data['_locale'])) {
            if (is_array($this->data['_locale'])) {
                foreach ($this->data['_locale'] as $c=>$l) {
                    setlocale($c, $l);
                }
            } elseif (is_string($this->data['_locale'])) {
                setlocale(LC_ALL, $this->data['_locale']);
            }
        } else {
            setLocale(LC_ALL, 'en_US.UTF-8');
        }

        if (!empty($this->data['_timezone'])) {
            date_default_timezone_set($this->data['_timezone']);
        }

        if ($autoClose) {
            session_write_close();
            $this->_phpSessionOpen = false;
        }
BDebug::debug(__METHOD__.': '.spl_object_hash($this));
        return $this;
    }

    /**
    * Set or retrieve dirty session flag
    *
    * @deprecated
    * @param bool $flag
    * @return bool
    */
    public function dirty($flag=null)
    {
        if (is_null($flag)) {
            return $this->_dirty;
        }
        BDebug::debug('SESSION.DIRTY '.($flag?'TRUE':'FALSE'), 2);
        $this->_dirty = $flag;
        return $this;
    }

    public function isDirty()
    {
        return $this->_dirty;
    }

    public function setDirty($flag=true)
    {
        BDebug::debug('SESSION.DIRTY '.($flag?'TRUE':'FALSE'), 2);
        $this->_dirty = $flag;
        return $this;
    }

    /**
    * Set or retrieve session variable
    *
    * @deprecated
    * @param string $key If ommited, return all session data
    * @param mixed $value If ommited, return data by $key
    * @return mixed|BSession
    */
    public function data($key=null, $value=BNULL)
    {
        if (is_null($key)) {
            return $this->data;
        }
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->data($k, $v);
            }
            return $this;
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

    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    public function set($key, $value)
    {
        if (!isset($this->data[$key]) || $this->data[$key]!==$value) {
            $this->setDirty();
        }
        $this->data[$key] = $value;
        return $this;
    }

    public function pop($key)
    {
        $data = $this->get($key);
        $this->set($key, null);
        return $data;
    }

    /**
    * Get reference to session data and set dirty flag true
    *
    * @return array
    */
    public function &dataToUpdate()
    {
        $this->setDirty();
        return $this->data;
    }

    /**
    * Write session variable changes and close PHP session
    *
    * @return BSession
    */
    public function close()
    {
        if (!$this->_dirty || !empty($_SESSION)) {
            return;
        }
BDebug::debug(__METHOD__.': '.spl_object_hash($this));
#ob_start(); debug_print_backtrace(); BDebug::debug(nl2br(ob_get_clean()));
        if (!$this->_phpSessionOpen) {
            if (headers_sent()) {
                BDebug::warning("Headers already sent, can't start session");
            } else {
                session_start();
            }
            $namespace = BConfig::i()->get('cookie/session_namespace');
            if (!$namespace) $namespace = 'default';
            $_SESSION[$namespace] = $this->data;
        }
        BDebug::debug(__METHOD__, 1);
        session_write_close();
        $this->_phpSessionOpen = false;
        //$this->setDirty();
        return $this;
    }

    public function destroy()
    {
        session_destroy();
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
        $this->setDirty();
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
            if (empty($this->data['_messages'][$tag])) {
                continue;
            }
            foreach ($this->data['_messages'][$tag] as $i=>$m) {
                $msgs[] = $m;
                unset($this->data['_messages'][$tag][$i]);
                $this->setDirty();
            }
        }
        return $msgs;
    }

    public function csrfToken()
    {
        $data =& static::dataToUpdate();
        if (empty($data['_csrf_token'])) {
            $data['_csrf_token'] = BUtil::randomString(32);
        }
        return $data['_csrf_token'];
    }

    public function __destruct()
    {
        //$this->close();
    }
}

class BSession_APC extends BClass
{
    protected $_prefix;
    protected $_ttl = 0;
    protected $_lockTimeout = 10;

    public function __construct()
    {
        if (function_exists('apc_store')) {
            BSession::i()->addHandler('apc', __CLASS__);
        }
    }

    public function register($ttl=null)
    {
        if ($ttl) {
            $this->_ttl = $ttl;
        }
        session_set_save_handler(
            array($this, 'open'), array($this, 'close'),
            array($this, 'read'), array($this, 'write'),
            array($this, 'destroy'), array($this, 'gc')
        );
    }

    public function open($savePath, $sessionName)
    {
        $this->_prefix = 'BSession/'.$sessionName;
        if (!apc_exists($this->_prefix.'/TS')) {
            // creating non-empty array @see http://us.php.net/manual/en/function.apc-store.php#107359
            apc_store($this->_prefix.'/TS', array(''));
            apc_store($this->_prefix.'/LOCK', array(''));
        }
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $key = $this->_prefix.'/'.$id;
        if (!apc_exists($key)) {
            return ''; // no session
        }

        // redundant check for ttl before read
        if ($this->_ttl) {
            $ts = apc_fetch($this->_prefix.'/TS');
            if (empty($ts[$id])) {
                return ''; // no session
            } elseif (!empty($ts[$id]) && $ts[$id] + $this->_ttl < time()) {
                unset($ts[$id]);
                apc_delete($key);
                apc_store($this->_prefix.'/TS', $ts);
                return ''; // session expired
            }
        }

        if ($this->_lockTimeout) {
            $locks = apc_fetch($this->_prefix.'/LOCK');
            if (!empty($locks[$id])) {
                while (!empty($locks[$id]) && $locks[$id] + $this->_lockTimeout >= time()) {
                    usleep(10000); // sleep 10ms
                    $locks = apc_fetch($this->_prefix.'/LOCK');
                }
            }
            /*
            // by default will overwrite session after lock expired to allow smooth site function
            // alternative handling is to abort current process
            if (!empty($locks[$id])) {
                return false; // abort read of waiting for lock timed out
            }
            */
            $locks[$id] = time(); // set session lock
            apc_store($this->_prefix.'/LOCK', $locks);
        }

        return apc_fetch($key); // if no data returns empty string per doc
    }

    public function write($id, $data)
    {
        $ts = apc_fetch($this->_prefix.'/TS');
        $ts[$id] = time();
        apc_store($this->_prefix.'/TS', $ts);

        $locks = apc_fetch($this->_prefix.'/LOCK');
        unset($locks[$id]);
        apc_store($this->_prefix.'/LOCK', $locks);

        return apc_store($this->_prefix.'/'.$id, $data, $this->_ttl);
    }

    public function destroy($id)
    {
        $ts = apc_fetch($this->_prefix.'/TS');
        unset($ts[$id]);
        apc_store($this->_prefix.'/TS', $ts);

        $locks = apc_fetch($this->_prefix.'/LOCK');
        unset($locks[$id]);
        apc_store($this->_prefix.'/LOCK', $locks);

        return apc_delete($this->_prefix.'/'.$id);
    }

    public function gc($lifetime)
    {
        if ($this->_ttl) {
            $lifetime = min($lifetime, $this->_ttl);
        }
        $ts = apc_fetch($this->_prefix.'/TS');
        foreach ($ts as $id=>$time) {
            if ($time + $lifetime < time()) {
                apc_delete($this->_prefix.'/'.$id);
                unset($ts[$id]);
            }
        }
        return apc_store($this->_prefix.'/TS', $ts);
    }
}
BSession_APC::i();
