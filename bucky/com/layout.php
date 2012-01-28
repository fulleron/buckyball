<?php

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

    protected static $_metaDirectives = array(
        'callback' => 'BLayout::metaDirectiveCallback',
        'layout' => 'BLayout::metaDirectiveLayoutCallback',
        'root' => 'BLayout::metaDirectiveRootCallback',
        'hook' => 'BLayout::metaDirectiveHookCallback',
        'view' => 'BLayout::metaDirectiveViewCallback',
    );

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
    public function viewRootDir($rootDir=null)
    {
        $module = BModuleRegistry::i()->currentModule();
        if (is_null($rootDir)) {
            return $module ? $module->view_root_dir : $this->_viewRootDir;
        }
        $isAbsPath = strpos($rootDir, '/')===0 || strpos($rootDir, ':')===1;
        if ($module) {
            $module->view_root_dir = $isAbsPath ? $rootDir : $module->root_dir.'/'.$rootDir;
        } else {
            $this->_viewRootDir = $rootDir;
        }
        return $this;
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
    public function allViews($rootDir, $prefix='')
    {
        if (($curModule = BModuleRegistry::i()->currentModule())) {
            $rootDir = $curModule->root_dir.'/'.$rootDir;
        }
        $this->viewRootDir($rootDir);

        $files = BUtil::globRecursive($rootDir.'/*');
        if (!$files) {
            return $this;
        }

        if ($prefix) {
            $prefix = rtrim($prefix, '/').'/';
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (preg_match('#^('.preg_quote($rootDir.'/', '#').')(.*)(\.php)$#', $file, $m)) {
                $this->view($prefix.$m[2], array('template'=>$m[2].$m[3]));
            }
        }
        return $this;
    }

    public function defaultViewClass($className)
    {
        $this->_defaultViewClass = $className;
        return $this;
    }

    /**
    * Register or retrieve a view object
    *
    * @param string $viewName
    * @param array $params View parameters
    *   - template: optional, for templated views
    *   - view_class: optional, for custom views
    *   - module_name: optional, to use template from a specific module
    * @param boolean $reset update or reset view params //TODO
    * @return BView|BLayout
    */
    public function view($viewName, $params=BNULL, $reset=false)
    {
        if (is_array($viewName)) {
            foreach ($viewName as $i=>$view) {
                if (!is_numeric($i)) {
                    throw new BException(BApp::t('Invalid argument: %s', print_r($viewName,1)));
                }
                $this->view($view[0], $view[1]);
            }
            return $this;
        }
        if (BNULL===$params) {
            if (!isset($this->_views[$viewName])) {
                return null;
            }
            return $this->_views[$viewName];
        }
        if (empty($params['module_name']) && ($moduleName = BModuleRegistry::currentModuleName())) {
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
            BPubSub::i()->fire('BLayout::view.add: '.$viewAlias, array(
                'view'=>$this->_views[$viewAlias],
            ));
        } else {
            $this->_views[$viewAlias]->param($params);
            BPubSub::i()->fire('BLayout::view.update: '.$viewAlias, array(
                'view'=>$this->_views[$viewAlias],
            ));
        }
        return $this;
    }

    /**
    * Set or retrieve main (root) view object
    *
    * @param string $viewName
    * @return BView|BLayout
    */
    public function rootView($viewName=BNULL)
    {
        if (BNULL===$viewName) {
            return $this->_rootViewName ? $this->view($this->_rootViewName) : null;
        }
        /*
        if (empty($this->_views[$viewName])) {
            throw new BException(BApp::t('Invalid view name for main view: %s', $viewName));
        }
        */
        $this->_rootViewName = $viewName;
        return $this;
    }

    /**
    * Clone view object to another name
    *
    * @param string $from
    * @param string $to
    * @return BView
    */
    public function cloneView($from, $to=BNULL)
    {
        if (BNULL===$to) $to = $from.'-copy';
        $this->_views[$to] = clone $this->_views[$from];
        $this->_views[$to]->view_name = $to;
        return $this->_views[$to];
    }

    public function hook($hookName, $callback, $args=array())
    {
        BPubSub::i()->on('BLayout::hook.'.$hookName, $callback, $args);
        return $this;
    }

    public function hookView($hookName, $viewName, $args=array())
    {
        if (is_array($viewName)) {
            $params = $viewName;
            $viewName = array_shift($params);
            BLayout::i()->view($viewName, $params);
        }
        $view = BLayout::i()->view($viewName);
        if (!$view) {
            BDebug::warning('Invalid view name: '.$viewName, 1);
            return $this;
        }
        return $this->hook($hookName, $view, $args);
    }

    public function layout($layoutName, $layout=null)
    {
        if (!is_null($layout)) {
            if (!isset($this->_layouts[$layoutName])) {
                BDebug::debug('LAYOUT.ADD '.$layoutName);
                $this->_layouts[$layoutName] = $layout;
            } else {
                BDebug::debug('LAYOUT.UPDATE '.$layoutName);
                $this->_layouts[$layoutName] = array_merge_recursive($this->_layouts[$layoutName], $layout);
            }
            return $this;
        }
        if (is_array($layoutName)) {
            foreach ($layoutName as $l=>$def) {
                $this->layout($l, $def);
            }
            return $this;
        }
        if (empty($this->_layouts[$layoutName])) {
            BDebug::debug('LAYOUT.EMPTY '.$layoutName);
            return $this;
        }
        BDebug::debug('LAYOUT.PROCESS '.$layoutName);
        foreach ($this->_layouts[$layoutName] as $d) {
            if (empty($d['type']) && !empty($d[0])) {
                $d['type'] = $d[0];
            }
            if (empty(self::$_metaDirectives[$d['type']])) {
                BDebug::error('Unknown directive: '.$d['type']);
                continue;
            }
            if (empty($d['name']) && !empty($d[1])) {
                $d['name'] = $d[1];
            }
            $d['layout_name'] = $layoutName;
            $callback = self::$_metaDirectives[$d['type']];
            call_user_func($callback, $d);
        }
        return $this;
    }

    public function metaDirectiveCallback($d)
    {
        call_user_func($d['name']);
    }

    public function metaDirectiveLayoutCallback($d)
    {
        if ($d['name']==$d['layout_name']) { // simple 1 level recursion stop
            BDebug::error('Layout recursion detected: '.$d['name']);
            return;
        }
        $this->layout($d['name']);
    }

    public function metaDirectiveRootCallback($d)
    {
        $this->rootView($d['name']);
    }

    public function metaDirectiveHookCallback($d)
    {
        if (!empty($d['callbacks'])) {
            foreach ($d['callbacks'] as $cb) {
                $this->hook($d['name'], $cb);
            }
        }
        if (!empty($d['views'])) {
            foreach ($d['views'] as $v) {
                $this->hookView($d['name'], $v);
            }
        }
    }

    public function metaDirectiveViewCallback($d)
    {
        $view = $this->view($d['name']);
        if (!empty($d['set'])) {
            foreach ($d['set'] as $k=>$v) {
                $view->$k = $v;
            }
        }
        if (!empty($d['do'])) {
            foreach ($d['do'] as $args) {
                $method = array_shift($args);
                BDebug::debug('LAYOUT.view.do '.$method);
                call_user_func_array(array($view, $method), $args);
            }
        }
    }

    public function defaultTheme($themeName=null)
    {
        if (is_null($themeName)) {
            return $this->_defaultTheme;
        }
        $this->_defaultTheme = $themeName;
        return $this;
    }

    public function theme($themeName=null, $params=null)
    {
#$bt = debug_backtrace();BDebug::debug(print_r($bt[1], 1));
        if (is_null($themeName)) {
            if (!$this->_defaultTheme) {
                BDebug::error('Empty theme supplied and no default theme is set');
            }
            $themeName = $this->_defaultTheme;
        }
        if (is_array($themeName)) {
            foreach ($themeName as $n) {
                $this->theme($n);
            }
            return $this;
        }
        if (!is_null($params)) {
            BDebug::debug('THEME.ADD '.$themeName);
            $area = FCom::i()->area();
            if (!empty($params['area']) && !in_array($area, (array)$params['area'])) {
                BDebug::debug('Theme '.$themeName.' can not be used in '.$area);
                return $this;
            }
            $this->_themes[$themeName] = $params;
            return $this;
        }
        if (empty($this->_themes[$themeName])) {
            BDebug::error('Invalid theme name: '.$themeName);
            return $this;
        }
        BDebug::debug('THEME.LOAD '.$themeName);
        BPubSub::i()->fire('BLayout::theme.load.before', array('theme_name'=>$themeName));
        call_user_func($this->_themes[$themeName]['callback']);
        BPubSub::i()->fire('BLayout::theme.load.after', array('theme_name'=>$themeName));
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
            if ($route && ($match = $route->match())) {
                $routeName = $match['route_name'];
                $args['route_name'] = $routeName;
            }
        }
        $result = BPubSub::i()->fire("BLayout::{$eventName}", $args);

        $routes = is_string($routeName) ? explode(',', $routeName) : (array)$routeName;
        foreach ($routes as $route) {
            $args['route_name'] = $route;
            $r2 = BPubSub::i()->fire("BLayout::{$eventName}: {$route}", $args);
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

        $rootView = $this->rootView();
        BDebug::debug('LAYOUT.RENDER '.var_export($rootView, 1));
        if (!$rootView) {
            BDebug::error(BApp::t('Main view not found: %s', $this->_rootViewName));
        }
        $result = $rootView->render($args);

        $args['output'] =& $result;
        $this->dispatch('render.after', $routeName, $args);

        BSession::i()->dirty(false); // disallow session change during layout render

        return $result;
    }

    public function debugPrintViews()
    {
        foreach ($this->_views as $viewName=>$view) {
            echo $viewName.':<pre>'; print_r($view); echo '</pre><hr>';
        }
    }

    public function debugPrintLayouts()
    {
        echo "<pre>"; print_r($this->_layouts); echo "</pre>";
    }
}

/**
* First parent view class
*/
class BView extends BClass
{
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
    static public function factory($viewName, array $params)
    {
        $params['view_name'] = $viewName;
        $className = !empty($params['view_class']) ? $params['view_class'] : get_called_class();
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

    public function set($name, $value=null)
    {
        if (is_array($name)) {
            foreach ($name as $k=>$v) {
                $this->_params['args'][$k] = $v;
            }
            return $this;
        }
        $this->_params['args'][$name] = $value;
        return $this;
    }

    public function get($name)
    {
        return isset($this->_params['args'][$name]) ? $this->_params['args'][$name] : null;
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
    * @param mixed $value
    */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
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
        if ($viewname===$this->param('view_name')) {
            throw new BException(BApp::t('Circular reference detected: %s', $viewname));
        }

        return BLayout::i()->view($viewname);
    }

    /**
    * Collect output from subscribers of a layout event
    *
    * @param string $hookname
    * @param array $args
    * @return string
    */
    public function hook($hookname, $args=array())
    {
        $args['_viewname'] = $this->param('view_name');
        $result = BPubSub::i()->fire('BLayout::hook.'.$hookname, $args);
        return join('', $result);
    }

    /**
    * View class specific rendering
    *
    * Can be overridden for different template engines (Smarty, etc)
    *
    * @return string
    */
    protected function _render()
    {
        $template = BLayout::i()->viewRootDir().'/';
        $template .= ($tpl = $this->param('template')) ? $tpl : ($this->param('view_name').'.php');
BDebug::debug('TEMPLATE '.$template);
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
        $timer = BDebug::debug('RENDER.VIEW '.$this->param('view_name'));
        if ($this->param('raw_text')!==null) {
            return $this->param('raw_text');
        }
        foreach ($args as $k=>$v) {
            $this->_params['args'][$k] = $v;
        }
        if (($modName = $this->param('module_name'))) {
            BModuleRegistry::i()->pushModule($modName);
        }
        if (!$this->_beforeRender()) {
            BDebug::debug('BEFORE.RENDER failed');
            return false;
        }

        $result = '';
        $result .= join('', BPubSub::i()->fire('BView::render.before', array('view'=>$this)));
        $result .= $this->_render();
        $result .= join('', BPubSub::i()->fire('BView::render.after', array('view'=>$this)));

        BDebug::profile($timer);

        $this->_afterRender();
        if ($modName) {
            BModuleRegistry::i()->popModule();
        }

        return $result;
    }

    protected function _beforeRender()
    {
        return true;
    }

    protected function _afterRender()
    {
        BPubSub::i()->fire('BView::afterRender', array('view'=>$this));
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
    * @param array $args
    * @return string
    */
    public function q($str, $args=array())
    {
        if (is_null($str)) {
            return '';
        }
        if (!is_string($str)) {
            var_dump($str);
            return ' ** ERROR ** ';
        }
        return htmlspecialchars($args ? BUtil::sprintfn($str, $args) : $str);
    }

    public function optionsHtml($options, $default)
    {
        $html = '';
        foreach ($options as $k=>$v) {
            if (is_array($v) && !empty($v[0])) {
                $html .= '<optgroup label="'.$this->q($k).'">'
                    .$this->selectOptions($v, $default)
                    .'</optgroup>';
                continue;
            }
            if (!is_array($v)) {
                $v = array('text'=>$v);
            }
            $html .= '<option value="'.$this->q($k).'"'.($default===$k ? ' selected' : '')
                .(!empty($v['style']) ? ' style="'.$v['style'].'"' : '')
                .'>'.$v['text'].'</option>';
        }
        return $html;
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
    public function email($p=array())
    {
        static $availHeaders = array('to','from','cc','bcc','reply-to','return-path');

        if (is_string($p)) {
            $p = array('to'=>$p);
        }

        $body = $this->render($p);
        $headers = array();
        $params = array();

        if (preg_match_all('#<!--\{\s*(.*?):\s*(.*?)\s*\}-->#i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $lh = strtolower($m[1]);
                if ($lh=='subject') {
                    $subject = $m[2];
                } elseif ($lh=='to') {
                    $to = $m[2];
                } elseif (in_array($lh, $availHeaders)) {
                    $headers[$lh] = $m[1].': '.$m[2];
                }
                $body = str_replace($m[0], '', $body);
            }
        }
        foreach ($p as $k=>$v) {
            $lh = strtolower($k);
            if ($lh=='subject') {
                $subject = $v;
            } elseif ($lh=='to') {
                $to = $v;
            } elseif (in_array($lh, $availHeaders)) {
                $headers[$lh] = $k.': '.$v;
            } elseif ($k=='-f') $params[$k] = $k.' '.$v;
        }
        return mail($to, $subject, trim($body), join("\r\n", $headers), join(' ', $params));
    }
}

/**
* View dedicated for rendering HTML HEAD tags
*/
class BViewHead extends BView
{
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
    * Default tag templates for JS and CSS resources
    *
    * @var array
    */
    protected $_defaultTag = array(
        'js' => '<script type="text/javascript" src="%s" %a></script>',
        'js_raw' => '<script type="text/javascript" %a>%c</script>',
        'css' => '<link rel="stylesheet" type="text/css" href="%s" %a/>',
        'css_raw' => '<style type="text/css" %a>%c</style>',
        'less' => '<link rel="stylesheet" type="text/less" href="%s" %a/>',
    );

    /**
    * Current IE <!--[if]--> context
    *
    * @var string
    */
    protected $_currentIfContext = null;

    public function subst($from, $to=null)
    {
        if (is_null($to)) {
            return str_replace(array_keys($this->_subst), array_values($this->_subst), $from);
        }
        $this->_subst['{'.$from.'}'] = $to;
        return $this;
    }

    /**
    * Add meta tag, or return meta tag(s)
    *
    * @param string $name If not specified, will return all meta tags as string
    * @param string $content If not specified, will return meta tag by name
    * @param bool $httpEquiv Whether the tag is http-equiv
    * @return BViewHead
    */
    public function meta($name=null, $content=null, $httpEquiv=false)
    {
        if (is_null($name)) {
            return join("\n", $this->_meta);
        }
        if (is_null($content)) {
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
    * @todo remove coupling with $type (to preserve requested load order)
    */
    public function item($type=null, $name=null, $args=null)
    {
        if (is_null($type)) {
            $result = '';
            foreach ($this->_elements as $typeName=>$els) {
                list($type, $name) = explode(':', $typeName, 2);
                $result .= $this->item($type, $name)."\n";
            }
            return $result;
        }
        if (is_null($args)) {
            if (!isset($this->_elements[$type.':'.$name])) {
                return null;
            }
            $args = $this->_elements[$type.':'.$name];
            $tag = !empty($args['tag']) ? $args['tag'] : $this->_defaultTag[$type];
            $file = !empty($args['file']) ? $args['file'] : $name;
            $params = !empty($args['params']) ? $args['params'] : '';
            if (preg_match('#\{(.*?)\}#', $file, $m)) { // real time retrieval of module and path
                $file = str_replace('{'.$m[1].'}', BApp::m($m[1])->baseSrc(), $file);
            }
            if (strpos($file, 'http:')===false && strpos($file, 'https:')===false && $file[0]!=='/') {
                $module = !empty($args['module_name']) ? BModuleRegistry::i()->module($args['module_name']) : null;
                $baseUrl = $module ? $module->baseSrc() : BApp::baseUrl();
                $file = $baseUrl.'/'.$file;
            }
            $tag = str_replace('%s', htmlspecialchars($file), $tag);
            $tag = str_replace('%c', !empty($args['content']) ? $args['content'] : '', $tag);
            $tag = str_replace('%a', $params, $tag);
            if (!empty($args['if'])) {
                $tag = '<!--[if '.$args['if'].']>'.$tag.'<![endif]-->';
            }
            return $tag;
        } elseif (!is_array($args)) {
            throw new BException(BApp::t('Invalid %s args: %s', array(strtoupper($type), print_r($args, 1))));
        }
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $args['module_name'] = $moduleName;
        }
        if ($this->_currentIfContext) {
            $args['if'] = $this->_currentIfContext;
        }
        $args['type'] = $type;
        $this->_elements[$type.':'.$name] = $args;

BDebug::debug('EXT.RESOURCE '.$name.': '.print_r($this->_elements[$type.':'.$name], 1));
        return $this;
    }

    /**
    * Add or return JS resources
    *
    * @param string $name If ommited, return all JS tags
    * @param array $args If ommited, return tag by $name
    * @return BViewHead|array|string
    */
    public function js($name=null, $args=null)
    {
        return $this->item('js', $name, $args);
    }

    /**
    * Add or return CSS resources
    *
    * @param string $name If ommited, return all CSS tags
    * @param array $args If ommited, return tag by $name
    * @return BViewHead|array|string
    */
    public function css($name=null, $args=null)
    {
        return $this->item('css', $name, $args);
    }

    public function less($name=null, $args=null)
    {
        return $this->item('less', $name, $args);
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
            return $this->meta()."\n".$this->item();
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
