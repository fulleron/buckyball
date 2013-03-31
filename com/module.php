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

/**
* Registry of modules, their manifests and dependencies
*/
class BModuleRegistry extends BClass
{
    /**
    * Local cache for BApp::i()->get('area');
    *
    * @todo make sure handling is case insensitive
    * @var mixed
    */
    protected $_area;

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
        //BPubSub::i()->on('BFrontController::dispatch.before', array($this, 'onBeforeDispatch'));
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

    public static function getAllModules()
    {
        return static::$_modules;
    }

    public static function isLoaded($modName)
    {
        return !empty(static::$_modules[$modName]) && static::$_modules[$modName]->run_status===BModule::LOADED;
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
        if (is_null($this->_area)) {
            if ($area = BApp::i()->get('area')) {
                $this->_area = $area;
            } else {
                $this->_area = null; // false;
            }
        }
        if ($this->_area && !empty($params['areas'][$this->_area])) {
            $params = BUtil::arrayMerge($params, $params['areas'][$this->_area]);
        }

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
//        if (empty($params['bootstrap']['callback'])) {
//            BDebug::warning('Missing bootstrap information, skipping module: %s', $modName);
//        } else {
            static::$_modules[$modName] = BModule::i(true, $params);
//        }
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
            $info = pathinfo($file);
            switch ($info['extension']) {
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
            if (empty($manifest['modules']) && empty($manifest['include'])) {
                BDebug::error(BLocale::_("Could not read manifest file: %s", $file));
            }
            if (!empty($manifest['modules'])) {
                foreach ($manifest['modules'] as $modName=>$params) {
                    $params['manifest_file'] = $file;
                    $this->addModule($modName, $params);
                }
            }
            if (!empty($manifest['include'])) {
                $dir = dirname($file);
                foreach ($manifest['include'] as $include) {
                    $this->scan($dir.'/'.$include);
                }
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
                BDebug::warning('Module is required but not found: '.$modName);
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
            if (!is_object($mod)) {
                var_dump($mod); exit;
            }
            if ($mod->errors && !$mod->errors_propagated) {
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
        $mod->errors_propagated = true;
        foreach ($mod->children as $childName) {
            if (empty(static::$_modules[$childName])) {
                continue;
            }
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
            if (empty(static::$_modules[$parentName])) {
                continue;
            }
            $parent = static::$_modules[$parentName];
            if ($parent->run_status===BModule::PENDING) {
                continue;
            }
            $parent->run_status = BModule::PENDING;
            $this->propagateDepends($parent);
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
        // take care of 'load_after' option
        foreach ($modules as $modName=>$mod) {
            $mod->children_copy = $mod->children;
            if ($mod->load_after) {
                foreach ($mod->load_after as $n) {
                    if (empty($modules[$n])) {
                        BDebug::notice('Invalid module name specified: '.$n);
                        continue;
                    }
                    $mod->parents[] = $n;
                    $modules[$n]->children[] = $mod->name;
                }
            }
        }
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
    * Check module requirements
    *
    * @return BModuleRegistry
    */
    public function checkRequires()
    {
        if (!static::$_modules) {
            // validate required modules
            $requestRunLevels = (array)BConfig::i()->get('request/module_run_level');
            foreach ($requestRunLevels as $modName=>$runLevel) {
                if (!empty(static::$_modules[$modName])) {
                    static::$_modules[$modName]->run_level = $runLevel;
                } elseif ($runLevel===BModule::REQUIRED) {
                    BDebug::warning('Module is required but not found: '.$modName);
                }
            }
        }
        // scan for require

        foreach (static::$_modules as $modName=>$mod) {
            if (!isset($mod->require)) {
                continue;
            }
            // normalize require format
            $mod->require = $this->normalizeManifestRequireFormat($mod->require);

            // is currently iterated module required?
            if ($mod->run_level===BModule::REQUIRED) {
                $mod->run_status = BModule::PENDING; // only 2 options: PENDING or ERROR
            }
            if (!isset($mod->errors)) {
                $mod->errors = array();
            }
            // iterate over require for modules
            if (!empty($mod->require['module'])) {
                foreach ($mod->require['module'] as &$req) {
                    $reqMod = !empty(static::$_modules[$req['name']]) ? static::$_modules[$req['name']] : false;
                    // is the module missing
                    if (!$reqMod) {
                        $mod->errors[] = array('type'=>'missing', 'mod'=>$req['name']);
                        continue;
                    // is the module disabled
                    } elseif ($reqMod->run_level===BModule::DISABLED) {
                        $mod->errors[] = array('type'=>'disabled', 'mod'=>$req['name']);
                        continue;
                    // is the module version not valid
                    } elseif (!empty($req['version'])) {
                        $reqVer = $req['version'];
                        if (!empty($reqVer['from']) && version_compare($reqMod->version, $reqVer['from'], '<')
                            || !empty($reqVer['to']) && version_compare($reqMod->version, $reqVer['to'], '>')
                            || !empty($reqVer['exclude']) && in_array($reqVer->version, (array)$reqVer['exclude'])
                        ) {
                            $mod->errors[] = array('type'=>'version', 'mod'=>$req['name']);
                            continue;
                        }
                    }
                    if (!in_array($req['name'], $mod->parents)) {
                        $mod->parents[] = $req['name'];
                    }
                    if (!in_array($modName, $reqMod->children)) {
                        $reqMod->children[] = $modName;
                    }
                    if ($mod->run_status===BModule::PENDING) {
                        $reqMod->run_status = BModule::PENDING;
                    }
                }
                unset($req);
            }

            if (!$mod->errors && $mod->run_level===BModule::REQUESTED) {
                $mod->run_status = BModule::PENDING;
            }
        }

        foreach (static::$_modules as $modName=>$mod) {
            if (!is_object($mod)) {
                var_dump($mod); exit;
            }
            if ($mod->errors && !$mod->errors_propagated) {
                // propagate dependency errors into subdependent modules
                $this->propagateDependErrors($mod);
            } elseif ($mod->run_status===BModule::PENDING) {
                // propagate pending status into deep dependent modules
                $this->propagateDepends($mod);
            }
        }
        //print_r(static::$_modules);exit;
        return $this;
    }

    public function normalizeManifestRequireFormat($require)
    {
        // normalize require format
            foreach ($require as $reqType => &$req) {
                if (is_string($req)) {
                    if (is_numeric($reqType)) {
                        $require['module'] = array(array('name' => $req));
                        unset($require[$reqType]);
                    } else {
                        $require[$reqType] = array(array('name' => $req));
                    }
                } else if (is_array($req)) {
                    foreach($req as $reqMod => &$reqVer) {
                        if (is_numeric($reqMod)) {
                            $reqVer = array('name' => $reqVer);
                        } else {
                            $from = '';
                            $to = '';

                            $reqVerAr = explode(";", $reqVer);
                            if (!empty($reqVerAr[0])) {
                                $from = $reqVerAr[0];
                            }
                            if (!empty($reqVerAr[1])) {
                                $to = $reqVerAr[1];
                            }
                            if (!empty($from)) {
                                $reqVer = array('name' => $reqMod, 'version' => array('from' => $from, 'to' => $to));
                            } else {
                                $reqVer = array('name' => $reqMod);
                            }
                        }

                    }
                }
            }
            return $require;
    }

    /**
    * Run modules bootstrap callbacks
    *
    * @return BModuleRegistry
    */
    public function bootstrap()
    {
        $language = BSession::i()->data('_language');
        $this->checkDepends();
        $this->sortDepends();
        $this->checkRequires();
/*
echo "<pre>";
print_r(BConfig::i()->get());
print_r(static::$_modules);
echo "</pre>"; exit;
*/

        foreach (static::$_modules as $mod) {
            $this->pushModule($mod->name);
            $mod->bootstrap();
            //load translations
            if (!empty($language) && !empty($mod->translations[$language])) {
                if (!is_array($mod->translations[$language])) {
                    $mod->translations[$language] = array($mod->translations[$language]);
                }
                foreach($mod->translations[$language] as $file) {
                    BLocale::addTranslationsFile($file);
                }
            }
            $this->popModule();
        }
        BPubSub::i()->fire('bootstrap::after');
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
/*
    public function onBeforeDispatch()
    {
        $front = BFrontController::i();
        foreach (static::$_modules as $module) {
            if ($module->run_status===BModule::LOADED && ($prefix = $module->url_prefix)) {
                $front->redirect('GET /'.$prefix, $prefix.'/');
            }
        }
    }
*/
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
    * Default module run_level
    *
    * @var string
    */
    static protected $_defaultRunLevel = 'ONDEMAND';

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
    public $children_copy = array();
    public $update;
    public $errors = array();
    public $errors_propagated;
    public $description;
    public $migrate;
    public $load_after;

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
    * Set default run_level which new modules should initialize with
    *
    * @param string $runLevel
    */
    public static function defaultRunLevel($runLevel)
    {
        static::$_defaultRunLevel = $runLevel;
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
        if (!empty($this->bootstrap) && empty($this->bootstrap['file'])) {
            $this->bootstrap['file'] = null;
        }
        if (empty($this->root_dir)) {
            $this->root_dir = $m['root_dir'];
        }
        //TODO: optimize path calculations
        if (!BUtil::isPathAbsolute($this->root_dir)) {
//echo "{$m['root_dir']}, {$args['root_dir']}\n";
            if($m['root_dir'] != $this->root_dir)
                $this->root_dir = BUtil::normalizePath($m['root_dir'].'/'.$this->root_dir);
            else{
                $this->root_dir = BUtil::normalizePath($this->root_dir);
            }

            //$this->root_dir = BUtil::normalizePath($this->root_dir);
            //echo $this->root_dir."\n";
        }
        $this->run_level = static::$_defaultRunLevel; // disallow declaring run_level in manifest
        /*
        if (!isset($this->run_level)) {
            $runLevel = BConfig::i()->get('request/module_run_level/'.$this->name);
            $this->run_level = $runLevel ? $runLevel : BModule::ONDEMAND;
        }
        */
        if (!isset($this->run_status)) {
            $this->run_status = BModule::IDLE;
        }

    }

    protected function _getManifestData()
    {
        if (empty($this->manifest_file)) {
            $bt = debug_backtrace();
            foreach ($bt as $i=>$t) {
                if (!empty($t['function']) && ($t['function']==='module' || $t['function']==='addModule')) {
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
            static::$_manifestCache[$file] = array('root_dir' => str_replace('\\', '/', dirname($file)));
        }
        return static::$_manifestCache[$file];
    }

    /**
    * put your comment there...
    *
    * @todo optional omit http(s):
    */
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
        if (($rootDir = $c->get('fs/root_dir'))) {
            static::$_env['root_dir'] = str_replace('\\', '/', $rootDir);
        } else {
            static::$_env['root_dir'] = str_replace('\\', '/', $r->scriptDir());
        }
        if (($baseSrc = $c->get('web/base_src'))) {
            static::$_env['base_src'] = $baseSrc;//$r->scheme().'://'.static::$_env['http_host'].$baseSrc;
        } else {
            static::$_env['base_src'] = static::$_env['web_root'];
        }
        if (($baseHref = $c->get('web/base_href'))) {
            static::$_env['base_href'] = $baseHref;//$r->scheme().'://'.static::$_env['http_host'].$c->get('web/base_href');
        } else {
            static::$_env['base_href'] = static::$_env['web_root'];
        }
#echo "<pre>"; var_dump(static::$_env, $_SERVER); echo "</pre>"; exit;
        foreach (static::$_manifestCache as &$m) {
			//    $m['base_src'] = static::$_env['base_src'].str_replace(static::$_env['root_dir'], '', $m['root_dir']);
			$m['base_src'] = rtrim(static::$_env['base_src'], '/').str_replace(static::$_env['root_dir'], '', $m['root_dir']);
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
            $url = $m['base_src'];
            $url .= str_replace($m['root_dir'], '', $this->root_dir);
            $this->base_src = BUtil::normalizePath(rtrim($url, '/'));
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
    public function baseSrc($full=true)
    {
        $src = $this->base_src;
        if ($full) {
            $r = BRequest::i();
            $src = $r->scheme().'://'.$r->httpHost().$src;
        }
        return $src;
    }

    public function baseHref($full=true)
    {
        $href = $this->base_src;
        if ($full) {
            $r = BRequest::i();
            $href = $r->scheme().'://'.$r->httpHost().$href;
        }
        return $href;
    }

    public function baseDir()
    {
        $dir = $this->root_dir;

        return $dir;
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
        if (empty($this->bootstrap)) {
            BDebug::debug('Module does not have bootstrap for this area: '.$this->name);
            $this->run_status = static::IDLE;
            return $this;
        }
//echo "<hr>"; var_dump($this->bootstrap);
        if ($this->run_status!==BModule::PENDING && !$force) {
            return $this;
        }
        $this->_prepareModuleEnvData();
        if (!empty($this->bootstrap['file'])) {
            $includeFile = BUtil::normalizePath($this->root_dir.'/'.$this->bootstrap['file']);
            BDebug::debug('MODULE.BOOTSTRAP '.$includeFile);
            require_once ($includeFile);
        }
        if (!empty($this->bootstrap['callback'])) {
            $start = BDebug::debug(BLocale::_('Start bootstrap for %s', array($this->name)));
            call_user_func($this->bootstrap['callback']);
            #$mod->run_status = BModule::LOADED;
            BDebug::profile($start);
            BDebug::debug(BLocale::_('End bootstrap for %s', array($this->name)));
        }
        $this->run_status = BModule::LOADED;
        return $this;
    }
}

class BMigrate extends BClass
{
    /**
    * Information about current module being migrated
    *
    * @var array
    */
    protected static $_migratingModule;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BMigrate
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Collect migration data from all modules
    *
    * @return array
    */
    public static function getMigrationData()
    {
        $migration = array();
        foreach (BModuleRegistry::getAllModules() as $modName=>$mod) {
            if ($mod->version && $mod->migrate) {
                $connName = $mod->db_connection_name ? $mod->db_connection_name : 'DEFAULT';
                $migration[$connName][$modName] = array(
                    'code_version' => $mod->version,
                    'script' => $mod->migrate,
                    'run_status' => $mod->run_status,
                    'module_name' => $modName,
                    'connection_name' => $connName,
                );
            }
        }
        return $migration;
    }

    /**
    * Declare DB Migration script for a module
    *
    * @param string $script callback, script file name, script class name or directory
    * @param string|null $moduleName if null, use current module
    */
    /*
    public static function migrate($script='migrate.php', $moduleName=null)
    {
        if (is_null($moduleName)) {
            $moduleName = BModuleRegistry::currentModuleName();
        }
        $module = BModuleRegistry::i()->module($moduleName);
        $connectionName = $module->db_connection_name ? $module->db_connection_name : 'DEFAULT';
        static::$_migration[$connectionName][$moduleName]['script'] = $script;
    }
    */

    /**
    * Declare DB uninstallation script for a module
    *
    * @param mixed $script
    * @param empty $moduleName
    */
    /*
    public static function uninstall($script, $moduleName=null)
    {
        if (is_null($moduleName)) {
            $moduleName = BModuleRegistry::currentModuleName();
        }
        static::$_uninstall[$moduleName]['script'] = $script;
    }
    */

    /**
    * Run declared migration scripts to install or upgrade module DB scheme
    *
    * @param mixed $limitModules
    *   - false: migrate ALL declared modules (including disabled)
    *   - true: migrate only enabled modules in current request
    *   - array or comma separated string: migrate only specified modules
    */
    public static function migrateModules($limitModules=false)
    {
        $modReg = BModuleRegistry::i();
        $migration = static::getMigrationData();
        if (!$migration) {
            return;
        }
        if (is_string($limitModules)) {
            $limitModules = explode(',', $limitModules);
        }
        // initialize module tables
        // find all installed modules
        foreach ($migration as $connectionName=>&$modules) {
            if ($limitModules) {
                foreach ($modules as $modName=>$mod) {
                    if ((true===$limitModules && $mod['run_status']==='LOADED')
                        || (is_array($limitModules) && in_array($modName, $limitModules))
                    ) {
                        continue;
                    }
                    unset($modules[$modName]);
                }
            }
            BDb::connect($connectionName); // switch connection
            BDbModule::init(); // Ensure modules table in current connection
            // collect module db schema versions
            $dbModules = BDbModule::i()->orm()->find_many();
            foreach ($dbModules as $m) {
                if ($m->last_status==='INSTALLING') { // error during last installation
                    $m->delete();
                    continue;
                }
                $modules[$m->module_name]['schema_version'] = $m->schema_version;
            }
            // run required migration scripts
            foreach ($modules as $modName=>$mod) {
                if (empty($mod['code_version'])) {
                    continue; // skip migration of registered module that is not current active
                }
                if (empty($mod['script'])) {
                    BDebug::warning('No migration script found: '.$modName);
                    continue;
                }
                $modReg->currentModule($modName);
                $script = $mod['script'];
                if (is_array($script)) {
                     if (!empty($script['file'])) {
                         $filename = BApp::m($modName)->root_dir.'/'.$script['file'];
                         if (!file_exists($filename)) {
                             BDebug::warning('Migration file not exists: '.$filename);
                             continue;
                         }
                         require_once $filename;
                     }
                     $script = $script['callback'];
                }
                $module = $modReg->module($modName);
                static::$_migratingModule =& $mod;
                /*
                try {
                    BDb::transaction();
                */
                    BDb::ddlClearCache(); // clear DDL cache before each migration step
                    BDebug::debug('DB.MIGRATE '.$script);
                    if (is_callable($script)) {
                        $result = call_user_func($script);
                    } elseif (is_file($module->root_dir.'/'.$script)) {
                        $result = include_once($module->root_dir.'/'.$script);
                    } elseif (is_dir($module->root_dir.'/'.$script)) {
                        //TODO: process directory of migration scripts
                    } elseif (class_exists($script, true)) {
                        $script::i()->run();
                    }
                /*
                    BDb::commit();
                } catch (Exception $e) {
                    BDb::rollback();
                    throw $e;
                }
                */
            }
        }
        unset($modules);
        $modReg->currentModule(null);
        static::$_migratingModule = null;
    }

    /**
     * Run module DB installation scripts and set module db scheme version
     *
     * @param string $version
     * @param mixed  $callback SQL string, callback or file name
     * @return bool
     * @throws Exception
     * @return bool
     */
    public static function install($version, $callback)
    {
        $mod =& static::$_migratingModule;
        // if no code version set, return
        if (empty($mod['code_version'])) {
            return false;
        }
        // if schema version exists, skip
        if (!empty($mod['schema_version'])) {
            return true;
        }
BDebug::debug(__METHOD__.': '.var_export($mod, 1));
        // creating module before running install, so the module configuration values can be created within script
        $module = BDbModule::i()->create(array(
            'module_name' => $mod['module_name'],
            'schema_version' => $version,
            'last_upgrade' => BDb::now(),
            'last_status' => 'INSTALLING',
        ))->save();
        // call install migration script
        try {
            if (is_callable($callback)) {
                $result = call_user_func($callback);
            } elseif (is_file($callback)) {
                $result = include $callback;
            } elseif (is_string($callback)) {
                BDb::run($callback);
                $result = null;
            }
            if (false===$result) {
                $module->delete();
                return false;
            }
            $module->set(array('last_status'=>'INSTALLED'))->save();
            $mod['schema_version'] = $version;
        } catch (Exception $e) {
            // delete module schema record if unsuccessful
            $module->delete();
            throw $e;
        }
        return true;
    }

    /**
     * Run module DB upgrade scripts for specific version difference
     *
     * @param string $fromVersion
     * @param string $toVersion
     * @param mixed  $callback SQL string, callback or file name
     * @return bool
     * @throws BException
     * @throws Exception
     * @return bool
     */
    public static function upgrade($fromVersion, $toVersion, $callback)
    {
        $mod =& static::$_migratingModule;
        // if no code version set, return
        if (empty($mod['code_version'])) {
            return false;
        }
        // if schema doesn't exist, throw exception
        if (empty($mod['schema_version'])) {
            throw new BException(BLocale::_("Can't upgrade, module schema doesn't exist yet: %s", BModuleRegistry::currentModuleName()));
        }
        $schemaVersion = $mod['schema_version'];

        // if schema is newer than requested FROM version, skip
        if (version_compare($schemaVersion, $fromVersion, '>')) {
            return true;
        }
        $module = BDbModule::i()->load($mod['module_name'], 'module_name')->set(array(
            'last_upgrade' => BDb::now(),
            'last_status'=>'UPGRADING',
        ))->save();
        // call upgrade migration script
        try {
            if (is_callable($callback)) {
                $result = call_user_func($callback);
            } elseif (is_file($callback)) {
                $result = include $callback;
            } elseif (is_string($callback)) {
                BDb::run($callback);
                $result = null;
            }
            if (false===$result) {
                return false;
            }
            // update module schema version to new one
            $mod['schema_version'] = $toVersion;
            $module->set(array(
                'schema_version' => $toVersion,
            ))->save();
        } catch (Exception $e) {
            $module->set(array('last_status'=>'UPGRADED'))->save();
            throw $e;
        }
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
        $mod =& static::$_migratingModule;

        // if no code version set, return
        if (empty($mod['code_version'])) {
            return false;
        }
        // if module schema doesn't exist, skip
        if (empty($mod['schema_version'])) {
            return true;
        }
        $callback = $mod->uninstall_callback; //TODO: implement
        // call uninstall migration script
        if (is_callable($callback)) {
            call_user_func($callback);
        } elseif (is_file($callback)) {
            include $callback;
        } elseif (is_string($callback)) {
            BDb::run($callback);
        }
        // delete module schema version from db, related configuration entries will be deleted
        BDbModule::i()->load($mod['module_name'], 'module_name')->delete();
        return true;
    }
}


class BDbModule extends BModel
{
    protected static $_table = 'buckyball_module';

    public static function init()
    {
        //BDb::connect();
        $table = static::table();
        if (!BDb::ddlTableExists($table)) {
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
        //BDbModuleConfig::init();
    }
}
/*
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
*/
