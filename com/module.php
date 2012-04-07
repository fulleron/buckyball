<?php

/**
* Registry of modules, their manifests and dependencies
*/
class BModuleRegistry extends BClass
{
    /**
    * Module information collected from manifests
    *
    * @var array
    */
    protected static $_modules = array();

    /**
    * Current module name, not BNULL when:
    * - In module bootstrap
    * - In observer
    * - In view
    *
    * @var string
    */
    protected static $_currentModuleName = null;

    /**
    * Current module stack trace
    *
    * @var array
    */
    protected static $_currentModuleStack = array();

    public function __construct()
    {
        BPubSub::i()->on('BFrontController::dispatch.before', array($this, 'onBeforeDispatch'));
    }

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
    * Register or return module object
    *
    * @todo remove adding module from here
    * @param string $modName
    * @param mixed $params if not supplied, return module by name
    * @return BModule
    */
    public function module($modName, $params=null)
    {
        if (is_null($params)) {
            return isset(static::$_modules[$modName]) ? static::$_modules[$modName] : null;
        }
        return $this->addModule($modName, $params);
    }

    public function addModule($modName, $params)
    {
        if (is_callable($params)) {
            $params = array('bootstrap'=>array('callback'=>$params));
        } else {
            $params = (array)$params;
        }
        $params['name'] = $modName;
        if (!empty(static::$_modules[$modName])) {
            $mod = static::$_modules[$modName];
            if (empty($params['update'])) {
                $rootDir = $mod->root_dir;
                $file = $mod->bootstrap['file'];
                throw new BException(BLocale::_('Module is already registered: %s (%s)', array($modName, $rootDir.'/'.$file)));
            } else {
                BDebug::debug('MODULE UPDATE: '.$modName);
                foreach ($params as $k=>$v) {
                    if (is_array($mod->$k)) {
                        $mod->$k = array_merge_recursive($mod->$k, $v);
                    } else {
                        $mod->$k = $v;
                        //TODO: make more flexible without sacrificing performance
                        switch ($k) {
                        case 'url_prefix':
                            $mod->base_href = BApp::baseUrl().($v ? '/'.$v : '');
                            break;
                        }
                    }
                }
                return $this;
            }
        }
        if (empty($params['bootstrap']['callback'])) {
            BDebug::warning('Missing bootstrap information, skipping module: %s', $modName);
        } else {
            static::$_modules[$modName] = BModule::i(true, $params);
        }
        return $this;
    }

    /**
    * Scan for module manifests in a folder
    *
    * Scan can be performed multiple times on different locations, order doesn't matter for dependencies
    * Wildcards are accepted.
    *
    * @see BApp::i()->load() for examples
    *
    * @param string $source
    */
    public function scan($source)
    {
        // if $source does not end with .json, assume it is a folder
        if (substr($source, -5)!=='.json' && substr($source, -4)!=='.php') {
            $source .= '/manifest.*';
        }
        $manifests = glob($source, GLOB_BRACE);
        BDebug::debug('MODULE.SCAN '.$source.': '.print_r($manifests, 1));
        if (!$manifests) {
            return $this;
        }
        foreach ($manifests as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            switch ($ext) {
                case 'php':
                    $manifest = include($file);
                    break;
                case 'json':
                    $json = file_get_contents($file);
                    $manifest = BUtil::fromJson($json);
                    break;
                default:
                    BDebug::error(BLocale::_("Unknown manifest file format: %s", $file));
            }
            if (empty($manifest['modules'])) {
                BDebug::error(BLocale::_("Could not read manifest file: %s", $file));
            }
            foreach ($manifest['modules'] as $modName=>$params) {
                $params['manifest_file'] = $file;
                $this->module($modName, $params);
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
        // validate required modules
        $requestRunLevels = (array)BConfig::i()->get('request/module_run_level');
        foreach ($requestRunLevels as $modName=>$runLevel) {
            if (!empty(static::$_modules[$modName])) {
                static::$_modules[$modName]->run_level = $runLevel;
            } elseif ($runLevel===BModule::REQUIRED) {
                BDebug::error('Module is required but not found: '.$modName);
            }
        }
        // scan for dependencies
        foreach (static::$_modules as $modName=>$mod) {
            // normalize dependencies format
            foreach ($mod->depends as &$dep) {
                if (is_string($dep)) {
                    $dep = array('name'=>$dep);
                }
            }
            unset($dep);
            // is currently iterated module required?
            if ($mod->run_level===BModule::REQUIRED) {
                $mod->run_status = BModule::PENDING; // only 2 options: PENDING or ERROR
            }
            $mod->errors = array();
            // iterate over module dependencies
            if (!empty($mod->depends)) {
                foreach ($mod->depends as &$dep) {
                    $depMod = !empty(static::$_modules[$dep['name']]) ? static::$_modules[$dep['name']] : false;
                    // is the module missing
                    if (!$depMod) {
                        $mod->errors[] = array('type'=>'missing', 'mod'=>$dep['name']);
                        continue;
                    // is the module disabled
                    } elseif ($depMod->run_level===BModule::DISABLED) {
                        $mod->errors[] = array('type'=>'disabled', 'mod'=>$dep['name']);
                        continue;
                    // is the module version not valid
                    } elseif (!empty($dep['version'])) {
                        $depVer = $dep['version'];
                        if (!empty($depVer['from']) && version_compare($depMod->version, $depVer['from'], '<')
                            || !empty($depVer['to']) && version_compare($depMod->version, $depVer['to'], '>')
                            || !empty($depVer['exclude']) && in_array($depMod->version, (array)$depVer['exclude'])
                        ) {
                            $mod->errors[] = array('type'=>'version', 'mod'=>$dep['name']);
                            continue;
                        }
                    }
                    // for ordering by dependency
                    $mod->parents[] = $dep['name'];
                    $depMod->children[] = $modName;
                    if ($mod->run_status===BModule::PENDING) {
                        $depMod->run_status = BModule::PENDING;
                    }
                    // add dependency information to bootstrap config
                    //if (!empty($reqModules)) {
                    //    BConfig::i()->add(array('bootstrap'=>array('depends'=>array($dep['name']))));
                    //}

                }
                unset($dep);
            }

            if (!$mod->errors && $mod->run_level===BModule::REQUESTED) {
                $mod->run_status = BModule::PENDING;
            }
        }
        foreach (static::$_modules as $modName=>$mod) {
            if ($mod->errors && !$mod->errorsPropagated) {
                // propagate dependency errors into subdependent modules
                $this->propagateDependErrors($mod);
            } elseif ($mod->run_status===BModule::PENDING) {
                // propagate pending status into deep dependent modules
                $this->propagateDepends($mod);
            }
        }
        return $this;
    }

    /**
    * Propagate dependency errors into children modules recursively
    *
    * @param BModule $mod
    * @return BModuleRegistry
    */
    public function propagateDependErrors($mod)
    {
        //$mod->action = !empty($dep['action']) ? $dep['action'] : 'error';
        $mod->run_status = BModule::ERROR;
        $mod->errorsPropagated = true;
        foreach ($mod->children as $childName) {
            $child = static::$_modules[$childName];
            if ($child->run_level===BModule::REQUIRED && $child->run_status!==BModule::ERROR) {
                $this->propagateDependErrors($child);
            }
        }
        return $this;
    }

    /**
    * Propagate dependencies into parent modules recursively
    *
    * @param BModule $mod
    * @return BModuleRegistry
    */
    public function propagateDepends($mod)
    {
        foreach ($mod->parents as $parentName) {
            $mod = static::$_modules[$parentName];
            if ($mod->run_status===BModule::PENDING) {
                continue;
            }
            $mod->run_status = BModule::PENDING;
            $this->propagateDepends($parentName);
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
        $modules = static::$_modules;
        // get modules without dependencies
        $rootModules = array();
        foreach ($modules as $modName=>$mod) {
            if (empty($mod->parents)) {
                $rootModules[] = $mod;
            }
        }
#echo "<pre>"; print_r(static::$_modules); echo "</pre>";
#echo "<pre>"; print_r($rootModules); echo "</pre>";
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
            // remove processed module from list
            unset($modules[$n->name]);
        }
        static::$_modules = $sorted;

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
/*
echo "<pre>";
print_r(BConfig::i()->get());
print_r(static::$_modules);
echo "</pre>"; exit;
*/

        foreach (static::$_modules as $mod) {
            $this->pushModule($mod->name);
            $mod->bootstrap();
            $this->popModule();
        }
        return $this;
    }

    /**
    * Set or return current module context
    *
    * If $name is specified, set current module, otherwise retrieve one
    *
    * Used in context of bootstrap, event observer, view
    *
    * @todo remove setting module func
    *
    * @param string|empty $name
    * @return BModule|BModuleRegistry
    */
    public function currentModule($name=null)
    {
        if (is_null($name)) {
#echo '<hr><pre>'; debug_print_backtrace(); echo static::$_currentModuleName.' * '; print_r($this->module(static::$_currentModuleName)); #echo '</pre>';
            $name = static::currentModuleName();
            return $name ? $this->module($name) : false;
        }
        static::$_currentModuleName = $name;
        return $this;
    }

    public function setCurrentModule($name)
    {
        static::$_currentModuleName = $name;
        return $this;
    }

    public function pushModule($name)
    {
        array_push(self::$_currentModuleStack, $name);
        return $this;
    }

    public function popModule()
    {
        array_pop(self::$_currentModuleStack);
        return $this;
    }

    static public function currentModuleName()
    {
        if (!empty(self::$_currentModuleStack)) {
            return self::$_currentModuleStack[sizeof(self::$_currentModuleStack)-1];
        }
        return static::$_currentModuleName;
    }

    public function onBeforeDispatch()
    {
        $front = BFrontController::i();
        foreach (static::$_modules as $module) {
            if ($module->run_status===BModule::LOADED && ($prefix = $module->url_prefix)) {
                $front->redirect('GET /'.$prefix, $prefix.'/');
            }
        }
    }

    public function debug()
    {
        return static::$_modules;
    }
}

/**
* Module object to store module manifest and other properties
*/
class BModule extends BClass
{
    /**
    * Relevant environment variables cache
    *
    * @var array
    */
    static protected $_env = array();

    /**
    * Manifest files cache
    *
    * @var array
    */
    static protected $_manifestCache = array();

    public $name;
    public $run_level;
    public $run_status;
    public $bootstrap;
    public $version;
    public $db_connection_name;
    public $root_dir;
    public $view_root_dir;
    public $url_prefix;
    public $base_src;
    public $base_href;
    public $manifest_file;
    public $depends = array();
    public $parents = array();
    public $children = array();
    public $update;
    public $errors = array();
    public $errorsPropagated;

    const
        // run_level
        DISABLED  = 'DISABLED',
        ONDEMAND  = 'ONDEMAND',
        REQUESTED = 'REQUESTED',
        REQUIRED  = 'REQUIRED',

        // run_status
        IDLE    = 'IDLE',
        PENDING = 'PENDING',
        LOADED  = 'LOADED',
        ERROR   = 'ERROR'
    ;

    protected static $_fieldOptions = array(
        'run_level' => array(
            self::DISABLED  => 'DISABLED',
            self::ONDEMAND  => 'ONDEMAND',
            self::REQUESTED => 'REQUESTED',
            self::REQUIRED  => 'REQUIRED',
        ),
        'run_status' => array(
            self::IDLE    => 'IDLE',
            self::PENDING => 'PENDING',
            self::LOADED  => 'LOADED',
            self::ERROR   => 'ERROR'
        ),
    );

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
        $this->set($args);

        $m = $this->_getManifestData();
        if (empty($this->bootstrap['file'])) {
            $this->bootstrap['file'] = null;
        }
        if (empty($this->root_dir)) {
            $this->root_dir = $m['root_dir'];
        }
        //TODO: optimize path calculations
        if (!BUtil::isPathAbsolute($this->root_dir)) {
//echo "{$m['root_dir']}, {$args['root_dir']}\n";
            $this->root_dir = BUtil::normalizePath($m['root_dir'].'/'.$this->root_dir);
        }
        if (!isset($this->run_level)) {
            $runLevel = BConfig::i()->get('request/module_run_level/'.$this->name);
            $this->run_level = $runLevel ? $runLevel : BModule::ONDEMAND;
        }
        if (!isset($this->run_status)) {
            $this->run_status = BModule::IDLE;
        }

    }


    protected function _getManifestData()
    {
        if (empty($this->manifest_file)) {
            $bt = debug_backtrace();
            foreach ($bt as $i=>$t) {
                if (!empty($t['function']) && $t['function'] === 'module') {
                    $t1 = $t;
                    break;
                }
            }
            if (!empty($t1)) {
                $this->manifest_file = $t1['file'];
            }
        }
        //TODO: eliminate need for manifest file
        $file = $this->manifest_file;
        if (empty(static::$_manifestCache[$file])) {
            static::$_manifestCache[$file] = array('root_dir' => dirname(realpath($file)));
        }
        return static::$_manifestCache[$file];
    }

    protected static function _initEnvData()
    {
        if (!empty(static::$_env)) {
            return;
        }
        $r = BRequest::i();
        $c = BConfig::i();
        static::$_env['doc_root'] = $r->docRoot();
        static::$_env['web_root'] = $r->webRoot();
        static::$_env['http_host'] = $r->httpHost();
        static::$_env['root_dir'] = $c->get('fs/root_dir');
        static::$_env['base_src'] = '//'.static::$_env['http_host'].$c->get('web/base_src');
        static::$_env['base_href'] = '//'.static::$_env['http_host'].$c->get('web/base_href');

        foreach (static::$_manifestCache as &$m) {
            $m['base_src'] = static::$_env['base_src'].str_replace(static::$_env['root_dir'], '', $m['root_dir']);
        }
        unset($m);
    }

    protected function _prepareModuleEnvData()
    {
        static::_initEnvData();
        $m = static::$_manifestCache[$this->manifest_file];

        if (empty($this->url_prefix)) {
            $this->url_prefix = '';
        }
        if (empty($this->view_root_dir)) {
            $this->view_root_dir = $this->root_dir;
        }
        if (empty($this->base_src)) {
            $this->base_src = BUtil::normalizePath(rtrim($m['base_src'].str_replace($m['root_dir'], '', $this->root_dir), '/'));
        }
        if (empty($this->base_href)) {
            $this->base_href = static::$_env['base_href'];
            if (!empty($this->url_prefix)) {
                $this->base_href .= '/'.$this->url_prefix;
            }
        }
    }

    /**
    * Register module specific autoload callback
    *
    * @param mixed $rootDir
    * @param mixed $callback
    */
    public function autoload($rootDir='', $callback=null)
    {
        if (!$rootDir) {
            $rootDir = $this->root_dir;
        } elseif (!BUtil::isPathAbsolute($rootDir)) {
            $rootDir = $this->root_dir.'/'.$rootDir;
        }
        BClassAutoload::i(true, array(
            'module_name' => $this->name,
            'root_dir' => rtrim($rootDir, '/'),
            'filename_cb' => $callback,
        ));
        return $this;
    }

    /**
    * Module specific base URL
    *
    * @return string
    */
    public function baseSrc()
    {
        return $this->base_src;
    }

    public function baseHref()
    {
        return $this->base_href;
    }

    public function runLevel($level=null, $updateConfig=false)
    {
        if (is_null($level)) {
            return $this->run_level;
        }
        return $this->setRunLevel($level, $updateConfig);
    }

    public function setRunLevel($level, $updateConfig=false)
    {
        $this->run_level = $level;
        if ($updateConfig) {
            BConfig::i()->set('request/module_run_level/'.$this->name, $level);
        }
        return $this;
    }

    /**
    * @todo remove set func
    *
    * @param mixed $status
    * @return BModule
    */
    public function runStatus($status=null)
    {
        if (BNULL===$status) {
            return $this->run_status;
        }
        $this->run_status = $status;
        return $this;
    }

    public function setRunStatus($status)
    {
        $this->run_status = $status;
        return $this;
    }

    public function _($string, $args=array())
    {
        $tr = dgettext($this->name, $string);
        if ($args) {
            $tr = BUtil::sprintfn($tr, $args);
        }
        return $tr;
    }

    public function set($key, $value=null)
    {
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                $this->$k = $v;
            }
            return $this;
        }
        $this->$key = $value;
        return $this;
    }

    public function bootstrap($force=false)
    {
        if ($this->run_status!==BModule::PENDING && !$force) {
            return $this;
        }
        $this->_prepareModuleEnvData();
        if (!empty($this->bootstrap['file'])) {
            $includeFile = BUtil::normalizePath($this->root_dir.'/'.$this->bootstrap['file']);
            BDebug::debug('MODULE.BOOTSTRAP '.$includeFile);
            require ($includeFile);
        }
        $start = BDebug::debug(BLocale::_('Start bootstrap for %s', array($this->name)));
        call_user_func($this->bootstrap['callback']);
        #$mod->run_status = BModule::LOADED;
        BDebug::profile($start);
        BDebug::debug(BLocale::_('End bootstrap for %s', array($this->name)));
        $this->run_status = BModule::LOADED;
        return $this;
    }
}


class BDbModule extends BModel
{
    protected static $_table = 'buckyball_module';

    public static function init()
    {
        $table = static::table();
        BDb::connect();
        if (BDebug::is('debug,development') && !BDb::ddlTableExists($table)) {
            BDb::run("
CREATE TABLE {$table} (
id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
module_name VARCHAR(100) NOT NULL,
schema_version VARCHAR(20),
data_version varchar(20),
last_upgrade DATETIME,
last_status varchar(20),
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
        $table = static::table();
        $modTable = BDbModule::table();
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
