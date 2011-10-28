<?php

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
    protected $_rootViewName = 'main';

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
    * @param boolean $reset update or reset view params //TODO
    * @return BView|BLayout
    */
    public function view($viewname, $params=BNULL, $reset=false)
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
        if (empty($params['module_name']) && ($moduleName = BModuleRegistry::currentModuleName())) {
            $params['module_name'] = $moduleName;
        }
        if (!isset($this->_views[$viewname]) || !empty($params['view_class'])) {
            $this->_views[$viewname] = BView::i()->factory($viewname, $params);
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
    public function rootView($viewname=BNULL)
    {
        if (BNULL===$viewname) {
            return $this->_rootViewName ? $this->view($this->_rootViewName) : null;
        }
        if (empty($this->_views[$viewname])) {
            throw new BException(BApp::t('Invalid view name for main view: %s', $viewname));
        }
        $this->_rootViewName = $viewname;
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

    public function on($hookname, $callback, $args=array())
    {
        // special case for view name
        if (is_string($callback) && !is_callable($callback)) {
            $view = BLayout::i()->view($callback);
            if (!$view) {
                BDebug::warning('Invalid view name: '.$callback, 1);
                return $this;
            }
            $callback = $view;
        }
        BPubSub::i()->on('BLayout::on.'.$hookname, $callback, $args);
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
        if (!$rootView) {
            throw new BException(BApp::t('Main view not found: %s', $this->_rootViewName));
        }
        $result = $rootView->render($args);

        $args['output'] =& $result;
        $this->dispatch('render.after', $routeName, $args);

        return $result;
    }

    public function debugPrintViews()
    {
        foreach ($this->_views as $viewname=>$view) {
            echo $viewname.':<pre>'; print_r($view); echo '</pre><hr>';
        }
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
        $params['view_name'] = $viewname;
        $className = !empty($params['view_class']) ? $params['view_class'] : static::$_defaultClass;
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
        if ($viewname===$this->param('name')) {
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
        $args['_viewname'] = $this->param('name');
        $result = BPubSub::i()->fire('BLayout::on.'.$hookname, $args);
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
        if (!$this->_beforeRender()) {
            return false;
        }
        $result = $this->_render();
        $this->_afterRender();
        if ($modName) {
            BModuleRegistry::i()->currentModule(null);
        }
        return $result;
    }

    protected function _beforeRender()
    {
        return true;
    }

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
        'js' => '<script type="text/javascript" src="%s" %a></script>',
        'css' => '<link rel="stylesheet" type="text/css" href="%s" %a/>',
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
            $params = !empty($args['params']) ? $args['params'] : '';
            if (strpos($file, 'http:')===false && strpos($file, 'https:')===false && $file[0]!=='/') {
                $module = !empty($args['module_name']) ? BModuleRegistry::i()->module($args['module_name']) : null;
                $baseUrl = $module ? $module->base_url : BApp::baseUrl();
                $file = $baseUrl.'/'.$file;
            }
            $tag = str_replace('%s', htmlspecialchars($file), $tag);
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
