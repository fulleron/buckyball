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
 * Layout facility to register views and render output from views
 */
class BLayout extends BClass
{
    /**
     * Installed themes registry
     *
     * @var array
     */
    protected $_themes = array();

    /**
     * Default theme name (current area / main module)
     *
     * @var string|array
     */
    protected $_defaultTheme;

    /**
     * Layouts declarations registry
     *
     * @var array
     */
    protected $_layouts = array();

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
    protected $_rootViewName = 'root';

    /**
     * Main root dir for view files if operating outside of a module
     *
     * @var mixed
     */
    protected $_viewRootDir;

    /**
     * Default class name for newly created views
     *
     * @var string
     */
    protected $_defaultViewClass;

    /**
     * @var array
     */
    protected static $_metaDirectives = array(
        'remove'   => 'BLayout::metaDirectiveRemoveCallback',
        'callback' => 'BLayout::metaDirectiveCallback',
        'layout'   => 'BLayout::metaDirectiveIncludeCallback',
        'include'   => 'BLayout::metaDirectiveIncludeCallback',
        'root'     => 'BLayout::metaDirectiveRootCallback',
        'hook'     => 'BLayout::metaDirectiveHookCallback',
        'view'     => 'BLayout::metaDirectiveViewCallback',
    );

    protected static $_renderers = array();

    /**
     * @var array
     */
    protected static $_extRenderers = array(
        '.php' => array('callback' => null),
    );

    /**
     * @var string
     */
    protected static $_extRegex = '\.php';

    /**
     * Shortcut to help with IDE autocompletion
     *
     * @param bool  $new
     * @param array $args
     * @return BLayout
     */
    public static function i($new = false, array $args = array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
     * Set root dir for view templates, relative to current module root
     *
     * @deprecated
     * @param string $rootDir
     * @return BLayout
     */
    public function viewRootDir($rootDir = null)
    {
        if (is_null($rootDir)) {
            return $this->getViewRootDir();
        }

        return $this->setViewRootDir($rootDir);
    }

    /**
     * Get view root dir
     * If there is a module in registry as current module, its view root dir will be used, else default one.
     *
     * @return string
     */
    public function getViewRootDir()
    {
        $module = BModuleRegistry::i()->currentModule();

        return $module ? $module->view_root_dir : $this->_viewRootDir;
    }

    /**
     * Set view root dir
     * If there is current module in registry, set it to it, else set it to layout.
     *
     * @param $rootDir
     * @return $this
     */
    public function setViewRootDir($rootDir, $module=null)
    {
        if (is_null($module)) {
            $module = BModuleRegistry::i()->currentModule();
        }
        $isAbsPath = strpos($rootDir, '/') === 0 || strpos($rootDir, ':') === 1;
        if ($module) {
            $module->view_root_dir = $isAbsPath ? $rootDir : $module->root_dir . '/' . $rootDir;
        } else {
            $this->_viewRootDir = $rootDir;
        }

        return $this;
    }

    /**
     * Add extension renderer
     *
     * Set renderer for particular file extension. E.g. '.php'
     * For renderer to work, params should either be array with 'renderer' field
     * or a string representing renderer class.
     *
     * @param string $ext
     * @param array $params
     * @return $this
     */
    public function addRenderer($name, $params)
    {
        if (is_string($name) && is_string($params)) {
            $params = array('file_ext' => array($name), 'callback' => $params);
        }
        if (is_string($params['file_ext'])) {
            $params['file_ext'] = explode(';', $params['file_ext']);
        }

        static::$_renderers[$name] = $params;

        foreach ($params['file_ext'] as $ext) {
            static::$_extRenderers[$ext] = $params;
        }
        static::$_extRegex = join('|', array_map('preg_quote', array_keys(static::$_extRenderers)));
        BDebug::debug('ADD RENDERER: '.join('; ', $params['file_ext']));
        return $this;
    }

    public function getAllRenderers($asOptions=false)
    {
        if ($asOptions) {
            $options = array();
            foreach (static::$_renderers as $k=>$r) {
                $options[$k] = !empty($r['description']) ? $r['description'] : $k;
            }
            asort($options);
            return $options;
        }
        return static::$_renderers;
    }

    public function getRenderer($name)
    {
        return !empty(static::$_renderers[$name]) ? static::$_renderers[$name] : null;
    }

    /**
     * Alias for addAllViews()
     *
     * @deprecated alias
     * @param mixed $rootDir
     * @param mixed $prefix
     * @return BLayout
     */
    public function allViews($rootDir = null, $prefix = '')
    {
        return $this->addAllViews($rootDir, $prefix);
    }

    /**
     * Find and register all templates within a folder as view objects
     *
     * View objects will be named by template file paths, stripped of extension (.php)
     *
     * @param string $rootDir Folder with view templates, relative to current module root
     *                        Can end with slash or not - make sure to specify
     * @param string $prefix Optional: add prefix to view names
     * @return BLayout
     */
    public function addAllViews($rootDir = null, $prefix = '')
    {
        if (is_null($rootDir)) {
            return $this->_views;
        }
        $curModule = BModuleRegistry::i()->currentModule();
        if ($curModule && !BUtil::isPathAbsolute($rootDir)) {
            $rootDir = $curModule->root_dir . '/' . $rootDir;
        }
        if (!is_dir($rootDir)) {
            BDebug::warning('Not a valid directory: ' . $rootDir);

            return $this;
        }
        $rootDir = realpath($rootDir);

        $this->setViewRootDir($rootDir);

        $files = BUtil::globRecursive($rootDir . '/*');
        if (!$files) {
            return $this;
        }

        if ($prefix) {
            $prefix = rtrim($prefix, '/') . '/';
        }
        $re = '#^(' . preg_quote(realpath($rootDir) . '/', '#') . ')(.*)(' . static::$_extRegex . ')$#';
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (preg_match($re, $file, $m)) {
                //$this->view($prefix.$m[2], array('template'=>$m[2].$m[3]));
                $viewParams = array('template' => $file, 'file_ext' => $m[3]);
                $viewParams['renderer'] = static::$_extRenderers[$m[3]]['callback'];
                $this->addView($prefix . $m[2], $viewParams);
            }
        }

        BEvents::i()->fire(__METHOD__, array('root_dir'=>$rootDir, 'prefix'=>$prefix, 'module'=>$curModule));

        return $this;
    }

    /**
     * Set default view class
     *
     * @todo rename to setDefaultViewClass()
     * @param mixed $className
     * @return BLayout
     */
    public function defaultViewClass($className)
    {
        $this->_defaultViewClass = $className;
        return $this;
    }

    /**
     * Register or retrieve a view object
     *
     * @todo remove adding view from here
     * @param string  $viewName
     * @param array   $params View parameters
     *   - template: optional, for templated views
     *   - view_class: optional, for custom views
     *   - module_name: optional, to use template from a specific module
     * @param boolean $reset update or reset view params //TODO
     * @return BView|BLayout
     */
    public function view($viewName, $params = null, $reset = false)
    {
        if ($params) {
            $this->addView($viewName, $params, $reset);

            return $this;
        }

        return $this->getView($viewName);
    }

    /**
     * Not sure whether to leave view() for convenience
     *
     * Return registered view
     *
     * @param mixed $viewName
     * @return null|BView
     */
    public function getView($viewName)
    {
        return isset($this->_views[$viewName]) ? $this->_views[$viewName] : BViewEmpty::i();
    }

    /**
     * Add or update view to layout
     * Adds or updates a view to layout.
     * If view already exists, will replace its params with provided ones.
     *
     * @param string|array $viewName
     * @param string|array $params if string - view class name
     * @param bool $reset
     * @return $this
     * @throws BException
     */
    public function addView($viewName, $params = array(), $reset = false)
    {
        if (is_array($viewName)) {
            foreach ($viewName as $i => $view) {
                if (!is_numeric($i)) {
                    throw new BException(BLocale::_('Invalid argument: %s', print_r($viewName, 1)));
                }
                $this->addView($view[0], $view[1], $reset); // if self::view is possible to disappear better not use it.
            }

            return $this;
        }
        if (is_string($params)) {
            $params = array('view_class' => $params);
        }
        if (empty($params['module_name']) && ($moduleName = BModuleRegistry::i()->currentModuleName())) {
            $params['module_name'] = $moduleName;
        }
        $viewAlias = !empty($params['view_alias']) ? $params['view_alias'] : $viewName;
        if (!isset($this->_views[$viewAlias]) || !empty($params['view_class'])) {
            if (empty($params['view_class'])) {
                /*
                if (!empty($params['module_name'])) {
                    $viewClass = BApp::m($params['module_name'])->default_view_class;
                    if ($viewClass) {
                        $params['view_class'] = $viewClass;
                    }
                } else
                */
                if (!empty($this->_defaultViewClass)) {
                    $params['view_class'] = $this->_defaultViewClass;
                }
            }

            $this->_views[$viewAlias] = BView::i()->factory($viewName, $params);
            BEvents::i()->fire('BLayout::view:add:' . $viewAlias, array(
                'view' => $this->_views[$viewAlias],
            ));
        } else {
            $this->_views[$viewAlias]->setParam($params);
            BEvents::i()->fire('BLayout::view:update:' . $viewAlias, array(
                'view' => $this->_views[$viewAlias],
            ));
        }

        return $this;
    }

    /**
     * Find a view by matching its name to a regular expression
     *
     * @param string $re
     * @return array
     */
    public function findViewsRegex($re)
    {
        $views = array();
        foreach ($this->_views as $viewName => $view) {
            if (preg_match($re, $viewName)) {
                $views[$viewName] = $view;
            }
        }

        return $views;
    }

    /**
     * Set or retrieve main (root) view object
     *
     * @deprecated
     * @param string $viewName
     * @return BView|BLayout
     */
    public function rootView($viewName = BNULL)
    {
        if (BNULL === $viewName) {
//            return $this->_rootViewName ? $this->view($this->_rootViewName) : null;
            return $this->getRootView(); // the above seems like this method?!
        }
        /*
        if (empty($this->_views[$viewName])) {
            throw new BException(BLocale::_('Invalid view name for main view: %s', $viewName));
        }
        */
        $this->_rootViewName = $viewName;

        return $this;
    }

    /**
     * Set root view name
     * @param string $viewName
     * @return $this
     */
    public function setRootView($viewName)
    {
        $this->_rootViewName = $viewName;

        return $this;
    }

    /**
     * @return BLayout|BView|null
     */
    public function getRootView()
    {
        return $this->_rootViewName ? $this->getView($this->_rootViewName) : null;
    }

    /**
     * @return string
     */
    public function getRootViewName()
    {
        return $this->_rootViewName;
    }

    /**
     * Clone view object to another name
     *
     * @param string $from
     * @param string $to
     * @return BView
     */
    public function cloneView($from, $to = BNULL)
    {
        if (BNULL === $to) {
            $to = $from . '-copy';
            for ($i = 2; !empty($this->_views[$to]); $i++) {
                $to = $from . '-copy' . $i;
            }
        }
        $this->_views[$to] = clone $this->_views[$from];
        $this->_views[$to]->setParam('view_name', $to);

        return $this->_views[$to];
    }

    /**
     * Register a call back to a hook
     *
     * @param string $hookName
     * @param mixed  $callback
     * @param array  $args
     * @return $this
     */
    public function hook($hookName, $callback, $args = array(), $alias = null)
    {
        BEvents::i()->on('BLayout::hook:' . $hookName, $callback, $args, $alias);

        return $this;
    }

    /**
     * Register a view as call back to a hook
     * $viewName should either be a string with a name of view,
     * or an array in which first field is view name and the rest are view parameters.
     *
     * @param string $hookName
     * @param string|array $viewName
     * @param array $args
     * @return $this
     */
    public function hookView($hookName, $viewName, $args = array())
    {
        if (is_array($viewName)) {
            $params   = $viewName;
            $viewName = array_shift($params);
            BLayout::i()->addView($viewName, $params);
        }
        $view = BLayout::i()->getView($viewName);
        if (!$view) {
            BDebug::warning('Invalid view name: ' . $viewName, 1);

            return $this;
        }
        //$view->set($args);
        return $this->hook($hookName, $view, $args, $viewName);
    }

    public function hookClear($hookName, $viewNames)
    {

        $eventHlp = BEvents::i();
        $eventName = 'BLayout::hook:' . $hookName;
        if (true === $viewNames || 'ALL' === $viewNames) {
            $eventHlp->off($eventName, true);
        } else {
            foreach ((array)$viewNames as $clearViewName) {
                $eventHlp->off($eventName, $clearViewName);
            }
        }
        return $this;
    }

    /**
     *
     * @deprecated
     * @param mixed $layoutName
     * @param mixed $layout
     * @return BLayout
     */
    public function layout($layoutName, $layout = null)
    {
        if (is_array($layoutName) || !is_null($layout)) {
            $this->addLayout($layoutName, $layout);
        } else {
            $this->applyLayout($layoutName);
        }

        return $this;
    }

    /**
    * Load layout update from file
    *
    * @param string $layoutFilename
    * @return BLayout
    */
    public function loadLayout($layoutFilename)
    {
#echo "<pre>"; debug_print_backtrace(); echo "</pre>";
        $ext = strtolower(pathinfo($layoutFilename, PATHINFO_EXTENSION));
        if (!BUtil::isPathAbsolute($layoutFilename)) {
            $mod = BModuleRegistry::i()->currentModule();
            if ($mod) {
                $layoutFilename = $mod->root_dir.'/'.$layoutFilename;
            }
        }
        BDebug::debug('LAYOUT.LOAD: '.$layoutFilename);
        switch ($ext) {
            case 'yml': case 'yaml': $layoutData = BYAML::i()->load($layoutFilename); break;
            case 'json': $layoutData = json_decode(file_get_contents($layoutFilename)); break;
            case 'php': $layoutData = include($layoutFilename); break;
            default: throw new BException('Unknown layout file type: '.$layoutFilename);
        }
        BLayout::i()->addLayout($layoutData);
        return $this;
    }

    /**
    * Load layout update after theme has been initialized
    *
    * @param string $layoutFilename
    * @return BLayout
    */
    public function loadLayoutAfterTheme($layoutFilename)
    {
        if (!BUtil::isPathAbsolute($layoutFilename)) {
            $mod = BModuleRegistry::i()->currentModule();
            if ($mod) {
                $layoutFilename = $mod->root_dir.'/'.$layoutFilename;
            }
        }
        $this->onAfterTheme(function() use($layoutFilename) {
            BLayout::i()->loadLayout($layoutFilename);
        });
        return $this;
    }

    /**
     * @param      $layoutName
     * @param null $layout
     * @return $this
     */
    public function addLayout($layoutName, $layout = null)
    {
        if (is_array($layoutName)) {
            foreach ($layoutName as $l => $def) {
                $this->addLayout($l, $def);
            }

            return $this;
        }
        if (!is_array($layout)) {
            BDebug::debug('LAYOUT.ADD ' . $layoutName . ': Invalid or empty layout');
        } else {
            if (!isset($this->_layouts[$layoutName])) {
                BDebug::debug('LAYOUT.ADD ' . $layoutName);
                $this->_layouts[$layoutName] = $layout;
            } else {
                BDebug::debug('LAYOUT.UPDATE ' . $layoutName);
                $this->_layouts[$layoutName] = array_merge_recursive($this->_layouts[$layoutName], $layout);
            }
        }

        return $this;
    }

    /**
     * @param $layoutName
     * @return $this
     */
    public function applyLayout($layoutName)
    {
        if (empty($this->_layouts[$layoutName])) {
            BDebug::debug('LAYOUT.EMPTY ' . $layoutName);

            return $this;
        }
        BDebug::debug('LAYOUT.APPLY ' . $layoutName);

        // collect callbacks
        $callbacks = array();
        foreach ($this->_layouts[$layoutName] as $d) {
            if (empty($d['type'])) {
                if (!is_array($d)) {
                    var_dump($layoutName, $d);
                }
                if (!empty($d[0])) {
                    $d['type'] = $d[0];
                } else {
                    foreach ($d as $k=>$n) {
                        if (!empty(self::$_metaDirectives[$k])) {
                            $d['type'] = $k;
                            $d['name'] = $n;
                            break;
                        }
                    }
                }
                if (empty($d['type'])) {
                    BDebug::dump($d);
                }
            }
            $d['type'] = trim($d['type']);
            if (empty($d['type']) || empty(self::$_metaDirectives[$d['type']])) {
                BDebug::error('Unknown directive: ' . $d['type']);
                continue;
            }
            if (empty($d['name']) && !empty($d[1])) {
                $d['name'] = $d[1];
            }
            $d['name'] = trim($d['name']);
            $d['layout_name'] = $layoutName;
            $callback = self::$_metaDirectives[$d['type']];

            if ($d['type'] === 'remove') {
                if ($d['name'] === 'all') { //TODO: allow removing specific instructions
                    $callbacks = array();
                }
            } else {
                $callbacks[] = array($callback, $d);
            }
        }

        // perform all callbacks
        foreach ($callbacks as $cb) {
            call_user_func($cb[0], $cb[1]);
        }

        return $this;
    }

    /**
     * @param $d
     */
    public function metaDirectiveCallback($d)
    {
        call_user_func($d['name'], $d);
    }

    /**
     * @param $d
     */
    public function metaDirectiveRemoveCallback($d)
    {
        //TODO: implement
    }

    /**
     * @param $d
     */
    public function metaDirectiveIncludeCallback($d)
    {
        if ($d['name'] == $d['layout_name']) { // simple 1 level recursion stop
            BDebug::error('Layout recursion detected: ' . $d['name']);

            return;
        }
        static $layoutsApplied = array();
        if (!empty($layoutsApplied[$d['name']]) && empty($d['repeat'])) {
            return;
        }
        $layoutsApplied[$d['name']] = 1;
        $this->applyLayout($d['name']);
    }

    /**
     * @param array $d
     */
    public function metaDirectiveRootCallback($d)
    {
        $this->setRootView($d['name']);
    }

    /**
     * @param array $d
     */
    public function metaDirectiveHookCallback($d)
    {
        $args = !empty($d['args']) ? $d['args'] : array();
        if (!empty($d['position'])) {
            $args['position'] = $d['position'];
        }
        if (!empty($d['callbacks'])) {
            foreach ($d['callbacks'] as $cb) {
                $this->hook($d['name'], $cb, $args);
            }
        }
        if (!empty($d['clear'])) {
            $this->hookClear($d['name'], $d['clear']);
        }
        if (!empty($d['views'])) {
            foreach ((array)$d['views'] as $v) {
                $this->hookView($d['name'], $v, $args);
            }
        }
    }

    /**
     * @param $d
     */
    public function metaDirectiveViewCallback($d)
    {
        $view = $this->getView($d['name']);
        if (!empty($d['set'])) {
            foreach ($d['set'] as $k => $v) {
                $view->set($k, $v);
            }
        }
        if (!empty($d['param'])) {
            foreach ($d['param'] as $k => $v) {
                $view->setParam($k, $v);
            }
        }
        if (!empty($d['do'])) {
            foreach ($d['do'] as $args) {
                $method = array_shift($args);
                BDebug::debug('LAYOUT.view.do ' . $method);
                call_user_func_array(array($view, $method), $args);
            }
        }
    }

    /**
     * @deprecated
     *
     * @param mixed $themeName
     * @return BLayout
     */
    public function defaultTheme($themeName = null)
    {
        if (is_null($themeName)) {
            return $this->_defaultTheme;
        }
        $this->_defaultTheme = $themeName;
        BDebug::debug('THEME.DEFAULT: ' . $themeName);

        return $this;
    }

    /**
     * @param $themeName
     * @return $this
     */
    public function setDefaultTheme($themeName)
    {
        $this->_defaultTheme = $themeName;
        BDebug::debug('THEME.DEFAULT: ' . $themeName);

        return $this;
    }

    /**
     * @return array|string
     */
    public function getDefaultTheme()
    {
        return $this->_defaultTheme;
    }

    /**
     * @param $themeName
     * @param $params
     * @return $this
     */
    public function addTheme($themeName, $params)
    {
        BDebug::debug('THEME.ADD ' . $themeName);
        $this->_themes[$themeName] = $params;

        return $this;
    }

    /**
     * @param null $area
     * @param bool $asOptions
     * @return array
     */
    public function getThemes($area = null, $asOptions = false)
    {
        if (is_null($area)) {
            return $this->_themes;
        }
        $themes = array();
        foreach ($this->_themes as $name => $theme) {
            if (!empty($theme['area']) && $theme['area'] === $area) {
                if ($asOptions) {
                    $themes[$name] = !empty($theme['description']) ? $theme['description'] : $name;
                } else {
                    $themes[$name] = $theme;
                }
            }
        }

        return $themes;
    }

    /**
     * @param null $themeName
     * @return $this
     */
    public function applyTheme($themeName = null)
    {
        if (is_null($themeName)) {
            if (!$this->_defaultTheme) {
                BDebug::error('Empty theme supplied and no default theme is set');
            }
            $themeName = $this->_defaultTheme;
        }
        if (is_array($themeName)) {
            foreach ($themeName as $n) {
                $this->applyTheme($n);
            }
            return $this;
        }
        BDebug::debug('THEME.APPLY ' . $themeName);
        BEvents::i()->fire('BLayout::applyTheme:before', array('theme_name' => $themeName));
        $this->loadTheme($themeName);
        BEvents::i()->fire('BLayout::applyTheme:after', array('theme_name' => $themeName));

        return $this;
    }

    public function loadTheme($themeName)
    {
        if (empty($this->_themes[$themeName])) {
            BDebug::warning('Invalid theme name: ' . $themeName);
            return false;
        }

        $theme = $this->_themes[$themeName];

        $area = BApp::i()->get('area');
        if (!empty($theme['area']) && !in_array($area, (array)$theme['area'])) {
            BDebug::debug('Theme ' . $themeName . ' can not be used in ' . $area);
            return false;
        }

        if (!empty($theme['parent'])) {
            foreach ((array)$theme['parent'] as $parentThemeName) {
                if ($this->loadTheme($parentThemeName)) {
                    break; // load the first available parent theme
                }
            }
        }

        BEvents::i()->fire('BLayout::loadTheme:before', array('theme_name' => $themeName, 'theme' => $theme));

        $modRootDir = !empty($theme['module_name']) ? BApp::m($theme['module_name'])->root_dir.'/' : '';
        if (!empty($theme['layout'])) {
            BLayout::i()->loadLayout($modRootDir.$theme['layout']);
        }
        if (!empty($theme['views'])) {
            BLayout::i()->addAllViews($modRootDir.$theme['views']);
        }
        if (!empty($theme['callback'])) {
            BUtil::i()->call($theme['callback']);
        }

        BEvents::i()->fire('BLayout::loadTheme:after', array('theme_name' => $themeName, 'theme' => $theme));

        return true;
    }

    /**
     * Shortcut for event registration
     * @param $callback
     * @return $this
     */
    public function onAfterTheme($callback)
    {
        BEvents::i()->on('BLayout::applyTheme:after', $callback);

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
     * @return array
     */
    public function dispatch($eventName, $routeName = null, $args = array())
    {
        if (is_null($routeName) && ($route = BRouting::i()->currentRoute())) {
            $args['route_name'] = $routeName = $route->route_name;
        }
        $result = BEvents::i()->fire("BLayout::{$eventName}", $args);

        $routes = is_string($routeName) ? explode(',', $routeName) : (array)$routeName;
        foreach ($routes as $route) {
            $args['route_name'] = $route;
            $r2                 = BEvents::i()->fire("BLayout::{$eventName}: {$route}", $args);
            $result             = BUtil::arrayMerge($result, $r2);
        }

        return $result;
    }

    /**
     * Render layout starting with main (root) view
     *
     * @param string $routeName Optional: render a specific route, default current route
     * @param array  $args Render arguments
     * @return mixed
     */
    public function render($routeName = null, $args = array())
    {
        $this->dispatch('render:before', $routeName, $args);

        $rootView = $this->getRootView();
        BDebug::debug('LAYOUT.RENDER ' . var_export($rootView, 1));
        if (!$rootView) {
            BDebug::error(BLocale::_('Main view not found: %s', $this->_rootViewName));
        }
        $result = $rootView->render($args);

        $args['output'] =& $result;
        $this->dispatch('render:after', $routeName, $args);

        //BSession::i()->dirty(false); // disallow session change during layout render

        return $result;
    }

    /**
     * @return void
     */
    public function debugPrintViews()
    {
        foreach ($this->_views as $viewName => $view) {
            echo $viewName . ':<pre>';
            print_r($view);
            echo '</pre><hr>';
        }
    }

    /**
     *
     */
    public function debugPrintLayouts()
    {
        echo "<pre>";
        print_r($this->_layouts);
        echo "</pre>";
    }
}

/**
 * First parent view class
 */
class BView extends BClass
{
    /**
     * @var
     */
    protected static $_renderer;

    /**
     * @var string
     */
    protected static $_metaDataRegex = '#<!--\s*\{\s*([^:]+):\s*(.*?)\s*\}\s*-->#';

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
     * @param string $viewName
     * @param array  $params
     * @return BView
     */
    static public function factory($viewName, array $params = array())
    {
        $params['view_name'] = $viewName;
        $className           = !empty($params['view_class']) ? $params['view_class'] : get_called_class();
        $view                = BClassRegistry::i()->instance($className, $params);

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
     * Retrieve view parameters
     *
     * @param string $key
     * @return mixed|BView
     */
    public function param($key = null)
    {
        if (is_null($key)) {
            return $this->_params;
        }

        return isset($this->_params[$key]) ? $this->_params[$key] : null;
    }

    /**
     * @param      $key
     * @param null $value
     * @return $this
     */
    public function setParam($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->setParam($k, $v);
            }

            return $this;
        }
        $this->_params[$key] = $value;

        return $this;
    }

    /**
     * @param $key
     * @return null
     */
    public function getParam($key)
    {
        return isset($this->_params[$key]) ? $this->_params[$key] : null;
    }

    /**
     * @param      $name
     * @param null $value
     * @return $this
     */
    public function set($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_params['args'][$k] = $v;
            }

            return $this;
        }
        $this->_params['args'][$name] = $value;

        return $this;
    }

    /**
     * @param $name
     * @return null
     */
    public function get($name)
    {
        return isset($this->_params['args'][$name]) ? $this->_params['args'][$name] : null;
    }

    /**
     * @return array
     */
    public function getAllArgs()
    {
        return !empty($this->_params['args']) ? $this->_params['args'] : array();
    }

    /**
     * Magic method to retrieve argument, accessible from view/template as $this->var
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Magic method to set argument, stored in params['args']
     *
     * @param string $name
     * @param mixed  $value
     * @return $this
     */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
     * Magic method to check if argument is set
     *
     * @param string $name
     * @return bool
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
        unset($this->_params['args'][$name]);
    }

    /**
     * Retrieve view object
     *
     * @todo detect multi-level circular references
     * @param string $viewName
     * @param array  $params
     * @throws BException
     * @return BView|null
     */
    public function view($viewName, $params = null)
    {
        if ($viewName === $this->param('view_name')) {
            throw new BException(BLocale::_('Circular reference detected: %s', $viewName));
        }

        $view = BLayout::i()->getView($viewName);

        if ($view && $params) {
            $view->set($params);
        }

        return $view;
    }

    /**
     * Collect output from subscribers of a layout event
     *
     * @param string $hookName
     * @param array  $args
     * @return string
     */
    public function hook($hookName, $args = array())
    {
        $args['_viewname'] = $this->param('view_name');
        $result = '';

        $debug = BDebug::is('DEBUG');
        if ($debug) {
            $result .= "<!-- START HOOK: {$hookName} -->\n";
        }

        $result .= join('', BEvents::i()->fire('BView::hook:before', array('view' => $this, 'name' => $hookName)));

        $result .= join('', BEvents::i()->fire('BLayout::hook:' . $hookName, $args));

        $result .= join('', BEvents::i()->fire('BView::hook:after', array('view' => $this, 'name' => $hookName)));

        if ($debug) {
            $result .= "<!-- END HOOK: {$hookName} -->\n";
        }

        return $result;
    }

    /**
     * @param string $defaultFileExt
     * @param bool $quiet
     * @return BView|mixed|string
     */
    public function getTemplateFileName($fileExt = null, $quiet = false)
    {
        if (is_null($fileExt)) {
            $fileExt = $this->getParam('file_ext');
        }
        $template = $this->param('template');
        if (!$template && ($viewName = $this->param('view_name'))) {
            $template = $viewName . $fileExt;
        }
        if ($template) {
            if (!BUtil::isPathAbsolute($template)) {
                $template = BLayout::i()->getViewRootDir() . '/' . $template;
            }
            if (!is_readable($template) && !$quiet) {
                BDebug::notice('TEMPLATE NOT FOUND: ' . $template);
            } else {
                BDebug::debug('TEMPLATE ' . $template);
            }
        }

        return $template;
    }

    /**
    * Used by external renderers to include compiled PHP file within $this context
    *
    * @param mixed $file
    */
    public function renderFile($file)
    {
        ob_start();
        include $file;
        return ob_get_clean();
    }

    public function renderEval($source)
    {
        ob_start();
        eval($source);
        return ob_get_clean();
    }

    /**
     * View class specific rendering
     *
     * @return string
     */
    protected function _render()
    {
        $renderer = $this->param('renderer');
        if ($renderer) {
            return call_user_func($renderer, $this);
        }

        ob_start();
        include $this->getTemplateFileName();
        return ob_get_clean();
    }

    /**
     * General render public method
     *
     * @param array $args
     * @param bool  $retrieveMetaData
     * @return string
     */
    public function render(array $args = array(), $retrieveMetaData = true)
    {
        $debug = BDebug::is('DEBUG') && !$this->get('no_debug');
        $viewName = $this->param('view_name');

        $timer = BDebug::debug('RENDER.VIEW ' . $viewName);
        if ($this->param('raw_text') !== null) {
            return $this->param('raw_text');
        }
        foreach ($args as $k => $v) {
            $this->_params['args'][$k] = $v;
        }
        if (($modName = $this->param('module_name'))) {
            BModuleRegistry::i()->pushModule($modName);
        }
        $result = '';
        if (!$this->_beforeRender()) {
            BDebug::debug('BEFORE.RENDER failed');
            if ($debug) {
                $result .= "<!-- FAILED VIEW: {$viewName} -->\n";
            }
            return $result;
        }

        $showDebugTags = $debug && $modName && $viewName && BLayout::i()->getRootViewName()!==$viewName;

        if ($showDebugTags) {
            $result .= "<!-- START VIEW: @{$modName}/{$viewName} -->\n";
        }
        $result .= join('', BEvents::i()->fire('BView::render:before', array('view' => $this)));

        $viewContent = $this->_render();

        if ($retrieveMetaData) {
            $metaData = array();
            if (preg_match_all(static::$_metaDataRegex, $viewContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $metaData[$m[1]] = $m[2];
                    $viewContent     = str_replace($m[0], '', $viewContent);
                }
            }
            $this->setParam('meta_data', $metaData);
        }
        $result .= $viewContent;
        $result .= join('', BEvents::i()->fire('BView::render:after', array('view' => $this)));

        if ($showDebugTags) {
            $result .= "<!-- END VIEW: @{$modName}/{$viewName} -->\n";
        }
        BDebug::profile($timer);

        $this->_afterRender();
        if ($modName) {
            BModuleRegistry::i()->popModule();
        }

        return $result;
    }

    /**
     * @return bool
     */
    protected function _beforeRender()
    {
        return true;
    }

    /**
     *
     */
    protected function _afterRender()
    {

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
            $result = '<hr>' . get_class($e) . ': ' . $e->getMessage() . '<hr>' . ORM::get_last_query() . '<hr>';
        } catch (Exception $e) {
            $result = '<hr>' . get_class($e) . ': ' . $e->getMessage() . '<hr>';
        }

        return $result;
    }

    /**
     * Escape HTML
     *
     * @param string $str
     * @param array  $args
     * @return string
     */
    public function q($str, $args = array())
    {
        if (is_null($str)) {
            return '';
        }
        if (!is_scalar($str)) {
            var_dump($str);

            return ' ** ERROR ** ';
        }

        return htmlspecialchars($args ? BUtil::sprintfn($str, $args) : $str);
    }

    /**
     * @param      $str
     * @param null $tags
     * @return string
     */
    public function s($str, $tags = null)
    {
        return strip_tags($str, $tags);
    }

    /**
     * @deprecated by BUtil::optionsHtml()
     * @param        $options
     * @param string $default
     * @return string
     */
    public function optionsHtml($options, $default = '')
    {
        return BUtil::optionsHtml($options, $default);
    }

    /**
     * Send email using the content of the view as body using standard PHP mail()
     *
     * Templates can include the following syntax for default headers:
     * - <!--{ From: Support <support@example.com> }-->
     * - <!--{ Subject: New order notification #<?php echo $this->order_id?> }-->
     *
     * $p accepts following parameters:
     * - to: email OR "name" <email>
     * - from: email OR "name" <email>
     * - subject: email subject
     * - cc: email OR "name" <email> OR array of these
     * - bcc: same as cc
     * - reply-to
     * - return-path
     *
     * All parameters are also available in the template as $this->{param}
     *
     * @param array|string $p if string, used as "To:" header
     * @return bool true if successful
     */
    public function email($p = array())
    {
        if (is_string($p)) {
            $p = array('to' => $p);
        }

        $body = $this->render($p, true);

        $data = array_merge(
            array_change_key_case($this->param('meta_data'), CASE_LOWER),
            array_change_key_case($p, CASE_LOWER)
        );
        $data['body'] = $body;

        return BEmail::i()->send($data);
    }

    /**
     * Translate string within view class method or template
     *
     * @param string $string
     * @param array  $params
     * @param string $module if null, try to get current view module
     * @return \false|string
     */
    public function _($string, $params = array(), $module = null)
    {
        if (empty($module) && !empty($this->_params['module_name'])) {
            $module = $this->_params['module_name'];
        }

        return BLocale::_($string, $params, $module);
    }
}

/**
 * Helper view to avoid errors of using views from disabled modules
 */
class BViewEmpty extends BView
{
    public function render(array $args = array(), $retrieveMetaData = true)
    {
        return '';
    }
}

/**
 * View dedicated for rendering HTML HEAD tags
 */
class BViewHead extends BView
{
    /**
     * @var array
     */
    protected $_title = array();

    /**
     * @var string
     */
    protected $_titleSeparator = ' :: ';

    /**
     * @var bool
     */
    protected $_titleReverse = true;

    /**
     * Substitution variables
     *
     * @var array
     */
    protected $_subst = array();

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
     * Support for head.js
     *
     * @see http://headjs.com/
     * @var array
     */
    protected $_headJs = array('enabled' => false, 'loaded' => false, 'jquery' => null, 'scripts' => array());

    /**
     * Support for require.js
     *
     * @see http://requirejs.org/
     * @var array
     */
    protected $_requireJs = array('config' => array(), 'run' => array());

    /**
     * Default tag templates for JS and CSS resources
     *
     * @var array
     */
    protected $_defaultTag = array(
        'js'      => '<script type="text/javascript" src="%s" %a></script>',
        'js_raw'  => '<script type="text/javascript" %a>%c</script>',
        'css'     => '<link rel="stylesheet" type="text/css" href="%s" %a/>',
        'css_raw' => '<style type="text/css" %a>%c</style>',
        //'less' => '<link rel="stylesheet" type="text/less" href="%s" %a/>',
        'less'    => '<link rel="stylesheet/less" type="text/css" href="%s" %a/>',
        'icon'    => '<link rel="icon" href="%s" type="image/x-icon" %a/><link rel="shortcut icon" href="%s" type="image/x-icon" %a/>',
    );

    /**
     * Current IE <!--[if]--> context
     *
     * @var string
     */
    protected $_currentIfContext = null;

    /**
     * @param      $from
     * @param null $to
     * @return $this|string
     */
    public function subst($from, $to = null)
    {
        if (is_null($to)) {
            return str_replace(array_keys($this->_subst), array_values($this->_subst), $from);
        }
        $this->_subst['{' . $from . '}'] = $to;

        return $this;
    }

    /**
     * Enable/disable head js
     *
     * @param bool $enable
     * @return $this
     */
    public function headJs($enable = true)
    {
        $this->_headJs['enabled'] = $enable;

        return $this;
    }

    /**
     * Alias for addTitle($title)
     *
     * @deprecated
     * @param mixed $title
     * @param bool  $start
     * @return BViewHead
     */
    public function title($title, $start = false)
    {
        $this->addTitle($title, $start);
    }

    /**
     * Add meta tag, or return meta tag(s)
     *
     * @deprecated
     *
     * @param string $name If not specified, will return all meta tags as string
     * @param string $content If not specified, will return meta tag by name
     * @param bool   $httpEquiv Whether the tag is http-equiv
     * @return BViewHead
     */
    public function meta($name = null, $content = null, $httpEquiv = false)
    {
        if (is_null($content)) {
            return $this->getMeta($name);
        }
        $this->addMeta($name, $content, $httpEquiv);

        return $this;
    }

    public function csrf_token()
    {
        $this->addMeta('csrf-token', BSession::i()->csrfToken());
        return $this;
    }

    /**
     * Add canonical link
     * @param $href
     * @return $this
     */
    public function canonical($href)
    {
        $this->addElement('link', 'canonical', array('tag' => '<link rel="canonical" href="' . $href . '"/>'));

        return $this;
    }

    /**
     * Add rss link
     *
     * @param $href
     */
    public function rss($href)
    {
        $this->addElement('link', 'rss', array('tag' => '<link rel="alternate" type="application/rss+xml" title="RSS" href="' . $href . '">'));
    }

    /**
     * Enable direct call of different item types as methods (js, css, icon, less)
     *
     * @param string $name
     * @param array  $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (!empty($this->_defaultTag[$name])) {
            array_unshift($args, $name);
            return call_user_func_array(array($this, 'addElement'), $args);
        } else {
            BDebug::error('Invalid method: ' . $name);
        }
    }

    public function removeAll()
    {
        $this->_elements = array();
        $this->_headJs = array();
        return $this;
    }

    /**
     * Remove JS/CSS elements by type and pattern (strpos)
     *
     * @param string $type
     * @param string $pattern
     * @return BViewHead
     */
    public function remove($type, $pattern)
    {
        if ($type === 'js' && $this->_headJs['loaded']) {
            foreach ($this->_headJs['scripts'] as $i => $file) {
                if (true===$pattern || strpos($file, $pattern) !== false) {
                    unset($this->_headJs['scripts'][$i]);
                }
            }
        }
        foreach ($this->_elements as $k => $args) {
            if (strpos($k, $type) === 0 && (true===$pattern || strpos($k, $pattern) !== false)) {
                unset($this->_elements[$k]);
            }
        }

        return $this;
    }

    /**
     * Set title
     * This will replace any current title
     *
     * @param $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->_title = array($title);
        return $this;
    }

    /**
     * Add title
     * Add title to be appended to or replace current titles
     *
     * @param      $title
     * @param bool $start
     * @return $this
     */
    public function addTitle($title, $start = false)
    {
        if ($start) {
            array_splice($this->_title, 0, 1, $title);
        } else {
            $this->_title[] = $title;
        }

        return $this;
    }

    /**
     * Set title separator
     * Set character or string to be used to separate title values.
     *
     * @param $sep
     * @return $this
     */
    public function setTitleSeparator($sep)
    {
        $this->_titleSeparator = $sep;
        return $this;
    }

    /**
     * Should title be composed in reverse order
     *
     * @param $reverse
     * @return $this
     */
    public function setTitleReverse($reverse)
    {
        $this->_titleReverse = $reverse;
        return $this;
    }

    /**
     * Compose and return title
     * Title is composed by all elements in $_title object field separated by _titleSeparator
     *
     * @return string
     */
    public function getTitle()
    {
        if (!$this->_title) {
            return '';
        }
        if ($this->_titleReverse) {
            $this->_title = array_reverse($this->_title);
        }

        return '<title>' . $this->q(join($this->_titleSeparator, $this->_title)) . '</title>';
    }

    /**
     * Get meta tags
     * If name is null, returns all meta tags joined
     * else returns named meta tag or null if name is not in _meta array
     *
     * @param null $name
     * @return null|string
     */
    public function getMeta($name = null)
    {
        if (is_null($name)) {
            return join("\n", $this->_meta);
        }

        return !empty($this->_meta[$name]) ? $this->_meta[$name] : null;
    }

    /**
     * Add meta tag
     *
     * @param      $name
     * @param      $content
     * @param bool $httpEquiv
     * @return $this
     */
    public function addMeta($name, $content, $httpEquiv = false)
    {
        if ($httpEquiv) {
            $this->_meta[$name] = '<meta http-equiv="' . $name . '" content="' . htmlspecialchars($content) . '" />';
        } else {
            $this->_meta[$name] = '<meta name="' . $name . '" content="' . htmlspecialchars($content) . '" />';
        }

        return $this;
    }

    /**
     * Add element
     * @param       $type
     * @param       $name
     * @param array $args
     * @return $this
     */
    public function addElement($type, $name, $args = array())
    {
//echo "<pre>"; debug_print_backtrace(); echo "</pre>";
//var_dump($type, $name, $args);
        if (is_string($args)) {
            $args = array('content' => $args);
        }
        if (!empty($args['alias'])) {
            $args['file'] = trim($name);
            $name         = trim($args['alias']);
        }
        if (!isset($args['module_name']) && ($moduleName = BModuleRegistry::i()->currentModuleName())) {
            $args['module_name'] = $moduleName;
        }
        if (!isset($args['if']) && $this->_currentIfContext) {
            $args['if'] = $this->_currentIfContext;
        }
        $args['type'] = $type;
        if (empty($args['position'])) {
            $this->_elements[$type . ':' . $name] = (array)$args;
        } else {
            $this->_elements = BUtil::arrayInsert(
                $this->_elements,
                array($type . ':' . $name => (array)$args),
                $args['position']
            );
#echo "<pre>"; print_r($this->_elements); echo "</pre>";
        }

        if ($this->_headJs['enabled']) {
            $basename = basename($name);
            if ($basename === 'head.js' || $basename === 'head.min.js' || $basename === 'head.load.min.js') {
                $this->_headJs['loaded'] = $name;
            }
        }

#BDebug::debug('EXT.RESOURCE '.$name.': '.print_r($this->_elements[$type.':'.$name], 1));
        return $this;
    }

    public function src($file, $ts = false)
    {
        if (is_array($file)) {
            $files = array();
            foreach ($file as $k=>$f) {
                $files[$k] = $this->src($f, $ts);
            }
            return $files;
        }
        if ($file[0] === '@') { // @Mod_Name/file.ext
            preg_match('#^@([^/]+)(.*)$#', $file, $m);
            $mod = BApp::m($m[1]);
            if (!$mod) {
                BDebug::notice('Module not found: ' . $file);
                return '';
            }
            $fsFile = BApp::m($m[1])->root_dir . $m[2];
            $file   = BApp::m($m[1])->baseSrc() . $m[2];
            if ($ts && file_exists($fsFile)) {
                $file .= '?' . substr(md5(filemtime($fsFile)), 0, 10);
            }
        } elseif (preg_match('#\{([A-Za-z0-9_]+)\}#', $file, $m)) { // {Mod_Name}/file.ext (deprecated)
            $mod = BApp::m($m[1]);
            if (!$mod) {
                BDebug::notice('Module not found: ' . $file);
                return '';
            }
            $fsFile = str_replace('{' . $m[1] . '}', BApp::m($m[1])->root_dir, $file);
            $file   = str_replace('{' . $m[1] . '}', BApp::m($m[1])->baseSrc(), $file);
            if ($ts && file_exists($fsFile)) {
                $file .= '?' . substr(md5(filemtime($fsFile)), 0, 10);
            }
        }
        return $file;
    }

    /**
     * @param $type
     * @param $name
     * @return mixed|null|string
     */
    public function getElement($type, $name)
    {
        $typeName = $type . ':' . $name;
        if (!isset($this->_elements[$typeName])) {
            return null;
        }
        $args = $this->_elements[$typeName];

        $file = !empty($args['file']) ? $args['file'] : $name;
        $file = $this->src($file, true);
        if (strpos($file, 'http:') === false && strpos($file, 'https:') === false && $file[0] !== '/') {
            $module  = !empty($args['module_name']) ? BModuleRegistry::i()->module($args['module_name']) : null;
            $baseUrl = $module ? $module->baseSrc() : BApp::baseUrl();
            $file    = $baseUrl . '/' . $file;
        }

        if ($type === 'js' && $this->_headJs['loaded'] && $this->_headJs['loaded'] !== $name
            && empty($args['separate']) && empty($args['tag']) && empty($args['params']) && empty($args['if'])
        ) {
            if (!$this->_headJs['jquery'] && strpos($name, 'jquery') !== false) {
                $this->_headJs['jquery'] = $file;
            } else {
                $this->_headJs['scripts'][] = $file;
            }

            return '';
        }

        $tag = !empty($args['tag']) ? $args['tag'] : $this->_defaultTag[$type];
        $tag = str_replace('%s', htmlspecialchars($file), $tag);
        $tag = str_replace('%c', !empty($args['content']) ? $args['content'] : '', $tag);
        $tag = str_replace('%a', !empty($args['params']) ? $args['params'] : '', $tag);
        if (!empty($args['if'])) {
            $tag = '<!--[if ' . $args['if'] . ']>' . $tag . '<![endif]-->';
        }

        return $tag;
    }

    /**
     * @return mixed
     */
    public function getAllElements()
    {
        $result = array();
        $res1   = array();
        foreach ($this->_elements as $typeName => $els) {
            list($type, $name) = explode(':', $typeName, 2);
            //$result[] = $this->getElement($type, $name);

            $res1[$type == 'css' ? 0 : 1][] = $this->getElement($type, $name);
        }
        for ($i = 0; $i <= 1; $i++) {
            if (!empty($res1[$i])) $result[] = join("\n", $res1[$i]);

        }

        return preg_replace('#\n{2,}#', "\n", join("\n", $result));
    }

    /**
     * Start/Stop IE if context
     *
     * @param mixed $context
     * @return $this
     */
    public function ifContext($context = null)
    {
        $this->_currentIfContext = $context;

        return $this;
    }

    public function requireModulePath($name = null, $path = null)
    {
        if (is_null($name)) {
            $m = BApp::m();
            $name = $m->name;
        } else {
            $m = BApp::m($name);
        }
        if (is_null($path)) {
            $path = trim($m->base_src, '/').'/js';
        }
        BDebug::debug(__METHOD__.':'.$name.':'.$path);
        $this->_requireJs['config']['paths'][$name] = $path;
        return $this;
    }

    public function requireJs($name, $path, $shim = null)
    {
        $this->_requireJs['config']['paths'][$name] = $path;
        if (!is_null($shim)) {
            $this->_requireJs['config']['shim'][$name] = $shim;
        }
        return $this;
    }

    public function requireConfig($config)
    {
        $this->_requireJs['config'] = BUtil::arrayMerge($this->_requireJs['config'], $config);
        return $this;
    }

    public function requireRun($names)
    {
        $this->_requireJs['run'] = array_merge($this->_requireJs['run'], (array)$names);
        return $this;
    }

    public function renderRequireJs()
    {
        $jsArr = array();
        if (!empty($this->_requireJs['config'])) {
            $config = $this->_requireJs['config'];
            if (empty($config['baseUrl'])) {
                $config['baseUrl'] = BConfig::i()->get('web/base_src');
            }
            if (!empty($config['paths'])) {
                foreach ($config['paths'] as $name => $file) {
                    $config['paths'][$name] = $this->src($file);
                }
            }
            $jsArr[] = "require.config(".BUtil::toJavaScript($config)."); ";
        }
        if (!empty($this->_requireJs['run'])) {
            $jsArr[] = "require(['" . join("', '", $this->_requireJs['run']) . "']);";
        }
        return join("\n", $jsArr);
    }

    /**
     * Render the view
     *
     * If param['template'] is not specified, return meta+css+js tags
     *
     * @param array $args
     * @param bool  $retrieveMetaData
     * @return string
     */
    public function render(array $args = array(), $retrieveMetaData = true)
    {
        if (!$this->param('template')) {
            $html = $this->getTitle() . "\n" . $this->getMeta() . "\n" . $this->getAllElements();

            $scriptsArr = array();
            if ($this->_headJs['scripts'] || $this->_headJs['jquery']) {
                if ($this->_headJs['scripts']) {
                    $scriptsArr[] = 'head.js("' . join('", "', $this->_headJs['scripts']) . '");';
                }
                if ($this->_headJs['jquery']) {
                    $scriptsArr[] = 'head.js({jquery:"' . $this->_headJs['jquery'] . '"}, function() { jQuery.fn.ready = head; ' . $scripts . '});';
                }
            }

            $requireJs = $this->renderRequireJs();
            if ($requireJs) {
                $scriptsArr[] = $requireJs;
            }

            if ($scriptsArr) {
                $html .= "<script>" . join("\n", $scriptsArr) . "</script>";
            }

            return $html;
        }

        return parent::render($args);
    }
}

/**
 * View subclass to store and render lists of views
 *
 * @deprecated by BLayout::i()->hook()
 */
class BViewList extends BView
{
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
     * @param array        $params
     * @return BViewList
     */
    public function append($viewname, array $params = array())
    {
        if (is_string($viewname)) {
            $viewname = explode(',', $viewname);
        }
        if (isset($params['position'])) {
            $this->_lastPosition = $params['position'];
        }
        foreach ($viewname as $v) {
            $params['name']     = $v;
            $params['position'] = $this->_lastPosition++;
            $this->_children[]  = $params;
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
        for ($viewname = md5(mt_rand()); $layout->getView($viewname);) ;
        $layout->addView($viewname, array('raw_text' => (string)$text));
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
        foreach ($this->_children as $i => $child) {
            $view = $this->view($child['name']);
            if (strpos($view->render(), $content) !== false) {
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
        if (true === $viewname) {
            $this->_children = array();

            return $this;
        }
        foreach ($this->_children as $i => $child) {
            if ($child['name'] == $viewname) {
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
     * @param bool  $retrieveMetaData
     * @throws BException
     * @return string
     */
    public function render(array $args = array(), $retrieveMetaData = true)
    {
        $output = array();
        uasort($this->_children, array($this, 'sortChildren'));
        $layout = BLayout::i();
        foreach ($this->_children as $child) {
            $childView = $layout->getView($child['name']);
            if (!$childView) {
                throw new BException(BLocale::_('Invalid view name: %s', $child['name']));
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
     * @return int
     */
    public function sortChildren($a, $b)
    {
        return $a['position'] < $b['position'] ? -1 : ($a['position'] > $b['position'] ? 1 : 0);
    }
}
