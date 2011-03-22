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
* Main BuckyBall Framework class
*/
class BApp
{
    /**
    * Global registry of services, which are singleton instances of classes
    * with essential or just useful functionality.
    *
    * Will include both system and custom services.
    *
    * @var array
    */
    protected static $_services = array();

    /**
    * The first method to be ran in bootstrap index file.
    *
    * Initializes system services, which will be lazy loaded whenever needed.
    *
    */
    public static function init()
    {
        self::service('debug', 'BDebug');
        self::service('classes', 'BClassRegistry');
        self::service('parser', 'BParser');
        self::service('config', 'BConfig');
        self::service('db', 'BDb');
        self::service('events', 'BEventRegistry');
        self::service('modules', 'BModuleRegistry');
        self::service('controller', 'BFrontController');
        self::service('request', 'BRequest');
        self::service('response', 'BResponse');
        self::service('layout', 'BLayout');
        self::service('cache', 'BCache');
        self::service('session', 'BSession');
        self::service('locale', 'BLocale');
        #self::service('unit', 'BUnit');

        #self::load(dirname(__FILE__));
    }

    /**
    * The last method to be ran in bootstrap index file.
    *
    * Performs necessary initializations and dispatches requested action.
    *
    */
    public static function run()
    {
        self::service('db')->init();
        self::service('session')->open();
        self::service('modules')->bootstrap();
        self::service('controller')->dispatch();
    }

    /**
    * Set or retrieve a service singleton by name.
    *
    * @param mixed $name simple descriptive name for the service
    * @param object|string $class if string, will be used as class name for lazy loading
    * @return object service singleton
    */
    public static function service($name, $class=BNULL)
    {
        if (BNULL===$class) {
            if (empty(self::$_services[$name])) {
                return null; // for checks whether service exists
                #throw new BException(self::t('Invalid service name: %s', $name));
            }
            if (is_string(self::$_services[$name])) {
                $class = self::$_services[$name];
                self::$_services[$name] = new $class;
            }
            return self::$_services[$name];
        }
        if (is_string($class) || is_object($class)) {
            self::$_services[$name] = $class;
        } else {
            throw new BException('Invalid service class argument');
        }
        self::service('debug')->log(array(
            'event' => __METHOD__,
            'name' => $name,
            'class' => is_object($class) ? get_class($class) : $class
        ));
    }

    /**
    * Check whether service exists by name
    *
    * @param string $name service name
    * @return boolean
    */
    public static function serviceExists($name)
    {
        return !empty(self::$_services[$name]);
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
        self::service('debug')->log($data);
    }

    /**
    * Shortcut to retrieve a current module object
    *
    * @return BModule
    */
    public static function module()
    {
        return self::service('modules')->currentModule();
    }

    /**
    * Shortcut to retrieve potentially overridden class instance
    *
    * @param string $class
    */
    public static function instance($class)
    {
        return self::service('classes')->instance($class);
    }

    /**
    * Shortcut to retrieve potentially overridden class singleton
    *
    * @param string $class
    */
    public static function singleton($class)
    {
        return self::service('classes')->singleton($class);
    }

    /**
    * Shortcut to add configuration, used mostly from bootstrap index file
    *
    * @param array|string $config If string will load configuration from file
    */
    public static function config($config)
    {
        if (is_array($config)) {
            self::service('config')->add($config);
        } elseif (is_string($config) && is_file($config)) {
            self::service('config')->addFile($config);
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
        $modules = self::service('modules');
        foreach ($folders as $folder) {
            $modules->scan($folder);
        }
    }

    /**
    * Shortcut for translation
    *
    * @param string $string Text to be translated
    * @param string|array $args Arguments for the text
    */
    public static function t($string, $args=array())
    {
        return self::service('locale')->t($string, $args);
    }

    /**
    * Shortcut for base URL to use in views and controllers
    *
    * @return string
    */
    public static function baseUrl()
    {
        static $baseUrl;
        if (!$baseUrl) {
            $baseUrl = self::service('request')->webRoot();
        }
        return $baseUrl;
    }
}

/**
* Bucky specialized exception
*/
class BException extends Exception
{
    public function __construct($message="", $code=0)
    {
        parent::__construct($message, $code);
        BApp::log($message, array(), array('event'=>'exception', 'code'=>$code, 'file'=>$this->getFile(), 'line'=>$this->getLine()));
    }
}

/**
* Service to log errors and events for development and debugging
*/
class BDebug
{
    protected $_startTime;
    protected $_events = array();

    /**
    * Contructor, remember script start time for delta timestamps
    *
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
    static public function service()
    {
        return BApp::service('debug');
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
        if (BApp::serviceExists('modules') && ($module = BApp::module())) {
            $event['module'] = $module->name;
        }
        $this->_events[] = $event;
        return $this;
    }

    public function delta()
    {
        return microtime(true)-$this->_startTime;
    }
}

class BParser
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BParser
    */
    static public function service()
    {
        return BApp::service('parser');
    }

    public function toJson($data)
    {
        return json_encode($data);
    }

    public function fromJson($data, $asObject=false)
    {
        $obj = json_decode($data);
        return $asObject ? $obj : $this->objectToArray($obj);
    }

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

    public function injectVars($str, $vars)
    {
        foreach ($vars as $k=>$v) {
            $str = str_replace(':'.$k, $v, $str);
        }
        return $str;
    }

    public function arrayCompare($array1, $array2)
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

    public function routeSave(&$tree, $route, $callback=null, $args=null, $multiple=false)
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
        if (($module = BApp::service('modules')->currentModule())) {
            $observer['module_name'] = $module->name;
        }
        if (!empty($args)) {
            $observer['args'] = $args;
        }
        if ($multiple || empty($node['observers'])) {
            $node['observers'][] = $observer;
        } else {
            $node['observers'][0] = array_merge_recursive($node['observers'][0], $observer);
        }
        unset($node);

        return $this;
    }

    public function routeLoad(&$tree, $route=null)
    {
        if (is_null($route)) {
            $route = BApp::service('request')->rawPath();
        }
        if (strpos($route, ' ')===false) {
            $method = BApp::service('request')->method();
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
}

class BConfig
{
    protected $_config = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BConfig
    */
    static public function service()
    {
        return BApp::service('config');
    }

    public function add($config, $root=null)
    {
        $this->_config = array_merge_recursive($this->_config, $config);
        return $this;
    }

    public function addFile($filename)
    {
        if (!is_readable($filename)) {
            throw new BException(BApp::t('Invalid configuration file name: %s', $filename));
        }
        $config = BApp::service('parser')->fromJson(file_get_contents($filename));
        if (!$config) {
            throw new BException(BApp::t('Invalid configuration contents: %s', $filename));
        }
        $this->add($config);
        return $this;
    }

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

class BDb
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDb
    */
    static public function service()
    {
        return BApp::service('db');
    }

    public function init()
    {
        /**
        * @see http://j4mie.github.com/idiormandparis/
        */
        include_once('lib/idiorm.php');
        include_once('lib/paris.php');

        $config = BApp::service('config');
        if (($dsn = $config->get('db/dsn'))) {
            ORM::configure($dsn);
            ORM::configure('username', $config->get('db/username'));
            ORM::configure('password', $config->get('db/password'));
            ORM::configure('logging', $config->get('db/logging'));
        }
    }
}

class BClassRegistry
{
    protected $_classes = array();
    protected $_singletons = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDb
    */
    static public function service()
    {
        return BApp::service('classes');
    }


    public function override($class, $newClass)
    {
        $this->_classes[$class] = $newClass;
    }

    public function className($class)
    {
        return isset($this->_classes[$class]) ? $this->_classes[$class] : $class;
    }

    public function instance($class, $args=array())
    {
        $className = $this->className($class);
        return new $className($args);
    }

    public function singleton($class, $args=array())
    {
        if (empty($this->_singletons[$class])) {
             $this->_singletons[$class] = $this->instance($class, $args);
        }
        return $this->_singletons[$class];
    }
}

class BEventRegistry
{
    protected $_events = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDb
    */
    static public function service()
    {
        return BApp::service('events');
    }

    /**
    * Declare event with default arguments in bootstrap function
    *
    * @param string|array $eventName accepts multiple events in form of non-associative array
    * @param array $args
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
        if (($module = BApp::service('modules')->currentModule())) {
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
                    $observer['callback'][0] = BApp::service('classes')->singleton($observer['callback'][0]);
                }
                $result[] = call_user_func($observer['callback'], $args);
            }
        }
        return $result;
    }
}

class BModuleRegistry
{
    protected $_modules = array();
    protected $_moduleDepends = array();
    protected $_services = array();
    protected $_serviceDepends = array();
    protected $_currentModuleName;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BModuleRegistry
    */
    static public function service()
    {
        return BApp::service('modules');
    }

    public function scan($source)
    {
        if (substr($source, -5)!=='.json') {
            $source .= '/manifest.json';
        }
        $manifests = glob($source);
        if (!$manifests) {
            return $this;
        }
        $parser = BApp::service('parser');
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
                $this->_modules[$modName] = BModule::factory($params);
            }
        }
        return $this;
    }

    public function checkDepends()
    {
        foreach ($this->_modules as $mod) {
            if (!empty($mod->depends['module'])) {
                foreach ($mod->depends['module'] as &$dep) {
                    if (is_string($dep)) {
                        $dep = array('name'=>$dep);
                    }
                    $this->_moduleDepends[$dep['name']][$mod['name']] = $dep;
                }
                unset($dep);
            }
            /*
            if (!empty($mod['services'])) {
                foreach ($mod['services'] as $i=>$svc) {
                    $this->_services[$svc]['modules'] = $mod['name'];
                }
            }
            */
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
        /*
        foreach($this->_modules as $mod) {
            if (!empty($mod['depends']['service'])) {
                foreach ($mod['depends']['service'] as $dep) {
                    if (is_string($dep)) {
                        $dep = array('name'=>$dep);
                        $mod['depends']['service'][$i] = $dep;
                    }
                    $this->_serviceDepends[$dep['name']][$mod['name']] = $dep;
                    if (empty($this->_services[$dep['name']])) {
                        if ($dep['action']!='ignore') {
                            $this->_serviceDepends[$dep['name']][$mod['name']]['error'] = 'missing';
                        }
                        continue;
                    }
                }
            }
        }
        */
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
            $this->_currentModuleName = $mod->name;
            include_once ($mod->root_dir.'/'.$mod->bootstrap['file']);
            call_user_func($mod->bootstrap['callback']);
        }
        return $this;
    }

    public function sortCallback($mod, $dep)
    {
        if (empty($mod->name) || empty($dep->name)) {
            return 0;
            var_dump($mod); var_dump($dep);
        }
        if (!empty($this->_moduleDepends[$mod->name][$dep->name])) return -1;
        elseif (!empty($this->_moduleDepends[$dep->name][$mod->name])) return 1;
        return 0;
    }

    public function find($moduleName=null)
    {
        if (is_null($moduleName)) {
            $moduleName = $this->_currentModuleName;
        }
        if (empty($this->_modules[$moduleName])) {
            throw new BException(BApp::t('Invalid module name: %s', $moduleName));
        }
        if ($setAsCurrent) {
            $this->_currentModuleName = $moduleName;
        }
        return $this->_modules[$moduleName];
    }

    public function module($name)
    {
        return isset($this->_modules[$name]) ? $this->_modules[$name] : null;
    }

    public function currentModule()
    {
        return $this->_currentModuleName ? $this->module($this->_currentModuleName) : false;
    }
}

class BModule
{
    static protected $_defaultClass = __CLASS__;
    protected $_params;

    static public function factory($params)
    {
        $className = !empty($params['registry_class']) ? $params['registry_class'] : self::$_defaultClass;
        $module = BApp::service('classes')->instance($className, $params);
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
}

class BFrontController
{
    protected $_routes = array();
    protected $_defaultRoutes = array();
    protected $_routeTree = array();
    protected $_urlTemplates = array();

    protected $_controllerName;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BFrontController
    */
    static public function service()
    {
        return BApp::service('controller');
    }

    public function route($route, $callback=null, $args=null, $name=null)
    {
        if (is_array($route)) {
            foreach ($route as $a) {
                $this->route($a[0], $a[1], isset($a[2])?$a[2]:null, isset($a[3])?$a[3]:null);
            }
            return;
        }

        BApp::service('parser')->routeSave($this->_routeTree, $route, $callback, $args, false);

        $this->_routes[$route] = $callback;
        if (!is_null($name)) {
            $this->_urlTemplates[$name] = $route;
        }
        return $this;
    }

    public function defaultRoute($callback, $args=null, $name=null)
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
        $routeNode = BApp::service('parser')->routeLoad($this->_routeTree, $route);
        $this->_currentRoute = $routeNode;

        if (!$routeNode || empty($routeNode['observers'])) {
            $params = array();
            if ($this->_defaultRoutes) {
//TODO
            } else {
                $callback = array('BActionController', 'noroute');
            }
        } else {
            $callback = $routeNode['observers'][0]['callback'];
            $params = isset($routeNode['params_values']) ? $routeNode['params_values'] : array();
        }
        $controllerName = $callback[0];
        $actionName = $callback[1];
        $args = !empty($routeNode['args']) ? $routeNode['args'] : array();
        $controller = null;
        $attempts = 0;
        $request = BApp::service('request');
        do {
            if (!empty($controller)) {
                list($actionName, $forwardControllerName, $params) = $controller->forward();
                if ($forwardControllerName) {
                    $controllerName = $forwardControllerName;
                }
            }
            $request->initParams($params);
            $controller = BApp::service('classes')->singleton($controllerName);
            $controller->dispatch($actionName, $args);
        } while ((++$attempts<100) && $controller->forward());

        if ($attempts==100) {
            throw new BException(BApp::t('Reached 100 route iterations: %s', print_r($callback,1)));
        }
    }

    static public function url($name, $params = array())
    {
// not implemented because not needed yet
    }
}

class BActionController
{
    public $params = array();

    protected $_action;
    protected $_forward;
    protected $_actionMethodPrefix = 'action_';

    public function view($viewname)
    {
        return BApp::service('layout')->view($viewname);
    }

    public function dispatch($actionName, $args=array())
    {
        $this->_action = $actionName;
        $this->_forward = null;
        if (!$this->authorize($args) || !$this->beforeDispatch($args)) {
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

    public function authorize($actionName=null)
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
        BApp::service('response')->set($message)->status(503);
    }

    public function action_noroute()
    {
        BApp::service('response')->set("Route not found")->status(404);
    }

    public function renderOutput()
    {
        BApp::service('response')->output();
    }
}

class BRequest
{
    protected $_params = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BRequest
    */
    static public function service()
    {
        return BApp::service('request');
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

    public function rawPath()
    {
        return !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
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

    public function request($key=null)
    {
        return is_null($key) ? $_REQUEST : (isset($_REQUEST[$key]) ? $_REQUEST[$key] : null);
    }

    public function rawPost($json=false, $asObject=false)
    {
        $post = file_get_contents('php://input');
        if ($post && $json) {
            $post = BApp::service('parser')->fromJson($post, $asObject);
        }
        return $post;
    }

    public function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        if (is_null($value)) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
        if (false===$value) {
            return $this->cookie($name, '', -1000);
        }
        setcookie($name, $value, time()+$lifespan, $path, $domain);
        return $this;
    }

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
            }
            switch ($f) {
                case 'int': $v = (int)$v; break;
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
                case 'alnum': $v = preg_replace('#[^a-z0-9_]#i', '', $v); break;
                case 'regex': case 'regexp': $v = preg_replace($p, '', $v); break;
            }
        }
        return $v;
    }
}

class BResponse
{
    protected $_contentType = 'text/html';
    protected $_content;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BResponse
    */
    static public function service()
    {
        return BApp::service('response');
    }

    public function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        BApp::service('request')->cookie($name, $value, $lifespan, $path, $domain);
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
        $this->_contentType = $type;
        return $this;
    }

    public function sendFile($filename)
    {
        BApp::service('session')->close();
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

    public function status($status, $message=null)
    {
        if (is_null($message)) {
            switch ((int)$status) {
                case 404: $message = 'Not Found'; break;
                case 503: $message = 'Service Unavailable'; break;
                default: $message = 'Unknown';
            }
        }
        header("HTTP/1.0 {$status} {$message}");
        header("Status: {$status} {$message}");
        $this->output();
    }

    public function output()
    {
        BApp::service('session')->close();
        header('Content-Type: '.$this->_contentType);
        if ($this->_contentType=='application/json') {
            $this->_content = BApp::service('parser')->toJson($this->_content);
        } elseif (is_null($this->_content)) {
            $this->_content = BApp::service('layout')->render();
        }

        print_r($this->_content);
        if ($this->_contentType=='text/html') {
            echo "<hr>DELTA: ".BApp::service('debug')->delta().', PEAK: '.memory_get_peak_usage(true).', EXIT: '.memory_get_usage(true);
        }
        exit;
    }

    public function render()
    {
        $this->output();
    }

    public function redirect($url)
    {
        header("Location: {$url}");
        exit;
    }
}

class BLayout
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
    static public function service()
    {
        return BApp::service('layout');
    }

    public function allViews($rootDir)
    {
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
        BApp::service('modules')->currentModule()->view_root_dir = $rootDir;
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
        if (!isset($this->_views[$viewname])) {
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
            $route = BApp::service('controller')->currentRoute();
            if (!$route) {
                return array();
            }
            $routeName = $route['route_name'];
            $args['route_name'] = $route['route_name'];
            $result = BApp::service('events')->dispatch("layout.{$eventName}", $args);
        } else {
            $result = array();
        }
        $routes = is_string($routeName) ? explode(',', $routeName) : $routeName;
        foreach ($routes as $route) {
            $args['route_name'] = $route;
            $r2 = BApp::service('events')->dispatch("layout.{$eventName}: {$route}", $args);
            $result = array_merge($result, $r2);
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
        $result = $this->mainView()->render($args);

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
        if (($module = BApp::service('modules')->currentModule())) {
            $params['module'] = $module->name;
        }
        $className = !empty($params['view_class']) ? $params['view_class'] : self::$_defaultClass;
        $view = BApp::service('classes')->instance($className, $params);
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

    public function view($viewname)
    {
        if ($viewname===$this->param('name')) {
            throw new BException(BApp::t('Circular reference detected: %s', $viewname));
        }

        return BApp::service('layout')->view($viewname);
    }

    public function render($args=array())
    {
        foreach ($args as $k=>$v) {
            $this->_params['args'][$k] = $v;
        }
        $module = BApp::service('modules')->module($this->param('module'));
        $template = $module->root_dir.'/';
        if ($module->view_root_dir) {
            $template .= $module->view_root_dir.'/';
        }
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
        return $this->render();
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
        $layout = BApp::service('layout');
        foreach ($this->_children as $child) {
            $output[] = $layout->view($child['name'])->render($args);
        }
        return join('', $output);
    }

    public function sortChildren($a, $b)
    {
        return $a['position']<$b['position'] ? -1 : ($a['position']>$b['position'] ? 1 : 0);
    }
}

class BSession
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
    static public function service()
    {
        return BApp::service('session');
    }

    public function open($id=null, $close=true)
    {
        if (!is_null($this->data)) {
            return;
        }
        $config = BApp::service('config')->get('cookie');
        session_set_cookie_params($config['timeout'], $config['path'], $config['domain']);
        session_name($config['name']);
        if (!empty($id) || ($id = BApp::service('request')->get('SID'))) {
            session_id($id);
        }
        session_start();

        $this->_sessionId = session_id();

        $namespace = $config['session_namespace'];
        $this->data = !empty($_SESSION[$namespace]) ? $_SESSION[$namespace] : array();

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

    public function data()
    {
        $this->open();
        return $this->data;
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
        $namespace = BApp::service('config')->get('cookie/session_namespace');
        $_SESSION[$namespace] = $this->data;
        session_write_close();
        $this->dirty(false);
    }

    public function sessionId()
    {
        return $this->_sessionId;
    }
}

class BCache
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BCache
    */
    static public function service()
    {
        return BApp::service('cache');
    }

    public function init()
    {

    }
}

class BLocale
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BLocale
    */
    static public function service()
    {
        return BApp::service('locale');
    }

    public function t($string, $args)
    {
        return BApp::service('parser')->sprintfn($string, $args);
    }
}

class BUnit
{
    protected $_currentTest;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BUnit
    */
    static public function service()
    {
        return BApp::service('unit');
    }

    public function test($methods)
    {

    }
}
