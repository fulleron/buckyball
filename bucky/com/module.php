<?php

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
    * Register or return module object
    *
    * @param string $modName
    * @param array|callback $params if not supplied, return module by name
    * @return BModule
    */
    public function module($modName, $params=BNULL)
    {
        if (BNULL===$params) {
            return isset($this->_modules[$modName]) ? $this->_modules[$modName] : null;
        }

        if (is_callable($params)) {
            $params = array('bootstrap'=>array('callback'=>$params));
        } else {
            $params = (array)$params;
        }
        $params['name'] = $modName;
        if (!empty($this->_modules[$modName])) {
            $rootDir = $this->_modules[$modName]->root_dir;
            $file = $this->_modules[$modName]->bootstrap['file'];
            throw new BException(BApp::t('Module is already registered: %s (%s)', array($modName, $rootDir.'/'.$file)));
        }
        if (empty($params['bootstrap']['callback'])) {
            BApp::log('Missing bootstrap information, skipping module: %s', $modName);
            return $this;
        }
        if (empty($params['bootstrap']['file'])) {
            $params['bootstrap']['file'] = null;
        }
        if (empty($params['root_dir'])) {
            $params['root_dir'] = '.';
        }
        if (empty($params['view_root_dir'])) {
            $params['view_root_dir'] = '.';
        }
        if (empty($params['url_prefix'])) {
            $params['url_prefix'] = '';
        }
        if (empty($params['base_url'])) {
            $params['base_url'] = BApp::baseUrl().$params['url_prefix'];
        }
        $this->_modules[$modName] = BModule::i(true, $params);
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
            $basePath = !empty($manifest['base_path']) ? $manifest['base_path'] : dirname($file);
            $rootDir = dirname(realpath($file));
            foreach ($manifest['modules'] as $modName=>$params) {
                $params['name'] = $modName;
                $modRootDir = (!empty($params['root_dir']) ? $params['root_dir'] : '');
                $params['root_dir'] = BUtil::normalizePath($rootDir.'/'.$modRootDir);
                $params['view_root_dir'] = $params['root_dir'];
                $params['base_url'] = BUtil::normalizePath(BApp::baseUrl().'/'.$basePath.'/'.$modRootDir);
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
        $config = BConfig::i();
        $reqModules = (array)$config->get('bootstrap/modules');

        // scan for dependencies
        foreach ($this->_modules as $modName=>$mod) {
            foreach ($mod->depends as &$dep) {
                if (is_string($dep)) {
                    $dep = array('name'=>$dep);
                }
            }
            if ((empty($reqModules) || in_array($modName, $reqModules)) && !empty($mod->depends)) {
                foreach ($mod->depends as &$dep) {
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
                        if (!empty($reqModules)) {
                            BConfig::i()->add(array('bootstrap'=>array('depends'=>array($dep['name']))));
                        }
                    } else {
                        $dep['error'] = array('type'=>'missing');
                    }
                }
                unset($dep);
            }
        }
        if (!empty($reqModules)) {
            foreach ($reqModules as $modName) {
                if (empty($this->_modules[$modName])) {
                    throw new BException('Invalid module name: '.$modName);
                }
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
        if (empty($this->_modules[$modName])) {
            return $this;
        }
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
            // remove processed module from list
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

        $config = BConfig::i()->get('bootstrap');
        foreach ($this->_modules as $mod) {
            if (!empty($mod->error)) {
                BApp::log($mod->name.': '.$mod->error);
                continue;
            }
            if (!empty($config['modules'])
                && !in_array($mod->name, (array)$config['modules'])
                && !empty($config['depends']) && !in_array($mod->name, $config['depends'])
            ) {
                continue;
            }
            $this->currentModule($mod->name);
            if (!empty($mod->bootstrap['file'])) {
                require (BUtil::normalizePath($mod->root_dir.'/'.$mod->bootstrap['file']));
            }
            $start = BDebug::debug(BApp::t('Start bootstrap for %s', array($mod->name)));
            call_user_func($mod->bootstrap['callback']);
            BDebug::profile($start);
            BDebug::debug(BApp::t('End bootstrap for %s', array($mod->name)));
        }
        BModuleRegistry::i()->currentModule(null);
        return $this;
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
#echo '<hr><pre>'; debug_print_backtrace(); echo static::$_currentModuleName.' * '; print_r($this->module(static::$_currentModuleName)); #echo '</pre>';
            return static::$_currentModuleName ? $this->module(static::$_currentModuleName) : false;
        }
        static::$_currentModuleName = $name;
        return $this;
    }

    static public function currentModuleName()
    {
        return static::$_currentModuleName;
    }

    public function debug()
    {
        return $this->_modules;
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
    public $db_name;
    public $root_dir;
    public $autoload_root_dir;
    public $autoload_filename_cb;
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

    /**
    * Register module specific autoload callback
    *
    * @param mixed $rootDir
    * @param mixed $callback
    */
    public function autoload($rootDir='', $callback=null)
    {
        $this->autoload_root_dir = rtrim((!$rootDir || $rootDir[0]!=='/' && $rootDir[1]!==':' ? $this->root_dir.'/' : '').$rootDir, '/');
        $this->autoload_filename_cb = $callback;
        spl_autoload_register(array($this, 'autoloadCallback'), false);
        return $this;
    }

    /**
    * Default autoload callback
    *
    * @param string $class
    */
    public function autoloadCallback($class)
    {
        if ($this->autoload_filename_cb) {
            $file = call_user_func($this->autoload_filename_cb, $class);
        } else {
            $file = str_replace('_', '/', $class).'.php';
        }
        if ($file) {
            if ($file[0]!=='/' && $file[1]!==':') {
                $file = $this->autoload_root_dir.'/'.$file;
            }
            include ($file);
        }
    }

    /**
    * Module specific base URL
    *
    * @return string
    */
    public function baseUrl()
    {
        return $this->base_url;
    }

    public function _($string, $args=array())
    {
        $tr = dgettext($this->name, $string);
        if ($args) {
            $tr = BUtil::sprintfn($tr, $args);
        }
        return $tr;
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
