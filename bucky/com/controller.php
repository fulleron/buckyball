<?php

/**
* Facility to handle request input
*/
class BRequest extends BClass
{
    /**
    * Route parameters
    *
    * Taken from route, ex:
    * Route: /part1/:param1/part2/:param2
    * Request: /part1/test1/param2/test2
    * $_params: array('param1'=>'test1', 'param2'=>'test2')
    *
    * @var array
    */
    protected $_params = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BRequest
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * On first invokation strip magic quotes in case magic_quotes_gpc = on
    *
    * @return BRequest
    */
    public function __construct()
    {
        $this->stripMagicQuotes();
    }

    /**
    * Client remote IP
    *
    * @return string
    */
    public function ip()
    {
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
    * Server local IP
    *
    * @return string
    */
    public function serverIp()
    {
        return !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
    }

    /**
    * Server host name
    *
    * @return string
    */
    public function serverName()
    {
        return !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    /**
    * Host name from request headers
    *
    * @return string
    */
    public function httpHost()
    {
        return !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
    }

    /**
    * Whether request is SSL
    *
    * @return bool
    */
    public function https()
    {
        return !empty($_SERVER['HTTPS']);
    }

    /**
    * Whether request is AJAX
    *
    * @return bool
    */
    public function xhr()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest';
    }

    /**
    * Request method:
    *
    * @return string GET|POST|HEAD|PUT|DELETE
    */
    public function method()
    {
        return !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    /**
    * Web root path for current application
    *
    * If request is /folder1/folder2/index.php, return /folder1/folder2/
    *
    * @return string
    */
    public function webRoot()
    {
        return !empty($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : null;
    }

    /**
    * Full base URL, including scheme and domain name
    *
    * @param null|boolean $forceSecure - if not null, force scheme
    * @param boolean $includeQuery - add origin query string
    * @return string
    */
    public function baseUrl($forceSecure=null, $includeQuery=false)
    {
        if (is_null($forceSecure)) {
            $scheme = $this->https() ? 'https' : 'http';
        } else {
            $scheme = $forceSecure ? 'https' : 'http';
        }
        $url = $scheme.'://'.$this->serverName().$this->webRoot();
        if ($includeQuery && ($query = $this->rawGet())) {
            $url .= '?'.$query;
        }
        return $url;
    }

    /**
    * Full request path, one part or slice of path
    *
    * @param int $offset
    * @param int $length
    * @return string
    */
    public function path($offset, $length=BNULL)
    {
        if (empty($_SERVER['PATH_INFO'])) {
            return null;
        }
        $path = explode('/', ltrim($_SERVER['PATH_INFO'], '/'));
        if (BNULL===$length) {
            return isset($path[$offset]) ? $path[$offset] : null;
        }
        return join('/', array_slice($path, $offset, true===$length ? null : $length));
    }

    /**
    * Raw path string
    *
    * @return string
    */
    public function rawPath()
    {
        return !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
    }

    /**
    * Request query variables
    *
    * @param string $key
    * @return array|string|null
    */
    public function get($key=null)
    {
        return is_null($key) ? $_GET : (isset($_GET[$key]) ? $_GET[$key] : null);
    }

    /**
    * Request query as string
    *
    * @return string
    */
    public function rawGet()
    {
        return !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    /**
    * Request POST variables
    *
    * @param string|null $key
    * @return array|string|null
    */
    public function post($key=null)
    {
        return is_null($key) ? $_POST : (isset($_POST[$key]) ? $_POST[$key] : null);
    }

    /**
    * Request raw POST text
    *
    * @param bool $json Receive request as JSON
    * @param bool $asObject Return as object vs array
    * @return object|array|string
    */
    public function rawPost()
    {
        $post = file_get_contents('php://input');
        return $post;
    }

    /**
    * Request array/object from JSON API call
    *
    * @param boolean $asObject
    * @return mixed
    */
    public function json($asObject=false)
    {
        return BUtil::fromJson($this->rawPost(), $asObject);
    }

    /**
    * Request variable (GET|POST|COOKIE)
    *
    * @param string|null $key
    * @return array|string|null
    */
    public function request($key=null)
    {
        return is_null($key) ? $_REQUEST : (isset($_REQUEST[$key]) ? $_REQUEST[$key] : null);
    }

    /**
    * Set or retrieve cookie value
    *
    * @param string $name Cookie name
    * @param string $value Cookie value to be set
    * @param int $lifespan Optional lifespan, default from config
    * @param string $path Optional cookie path, default from config
    * @param string $domain Optional cookie domain, default from config
    */
    public function cookie($name, $value=BNULL, $lifespan=null, $path=null, $domain=null)
    {
        if (BNULL===$value) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
        if (is_null($value) || false===$value) {
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

    /**
    * Initialize route parameters
    *
    * @param array $params
    */
    public function initParams(array $params)
    {
        $this->_params = $params;
        return $this;
    }

    /**
    * Return route parameter by name or all parameters as array
    *
    * @param string $key
    * @return array|string|null
    */
    public function params($key=BNULL)
    {
        return BNULL===$key ? $this->_params : (isset($this->_params[$key]) ? $this->_params[$key] : null);
    }

    /**
    * Sanitize input and assign default values
    *
    * Syntax: BRequest::i()->sanitize($post, array(
    *   'var1' => 'alnum', // return only alphanumeric components, default null
    *   'var2' => array('trim|ucwords', 'default'), // trim and capitalize, default 'default'
    *   'var3' => array('regex:/[^0-9.]/', '0'), // remove anything not number or .
    * ));
    *
    * @param array|object $data Array to be sanitized
    * @param array $config Configuration for sanitizing
    * @param bool $trim Whether to return only variables specified in config
    * @return array Sanitized result
    */
    public function sanitize($data, $config, $trim=true)
    {
        $data = (array)$data;
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
                $data[$k] = is_array($c) && isset($c[1]) ? $c[1] : null;
            }
        }
        return $data;
    }

    /**
    * Sanitize one variable based on specified filter(s)
    *
    * Filters:
    * - int
    * - positive
    * - float
    * - trim
    * - nohtml
    * - plain
    * - upper
    * - lower
    * - ucwords
    * - ucfirst
    * - urle
    * - urld
    * - alnum
    * - regex
    * - date
    * - datetime
    * - gmdate
    * - gmdatetime
    *
    * @param string $v Value to be sanitized
    * @param array|string $filter Filters as array or string separated by |
    * @return string Sanitized value
    */
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

    /**
    * String magic quotes in case magic_quotes_gpc = on
    *
    * @return BRequest
    */
    public function stripMagicQuotes()
    {
        static $alreadyRan = false;
        if (get_magic_quotes_gpc() && !$alreadyRan) {
            $process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
            while (list($key, $val) = each($process)) {
                foreach ($val as $k => $v) {
                    unset($process[$key][$k]);
                    if (is_array($v)) {
                        $process[$key][stripslashes($k)] = $v;
                        $process[] = &$process[$key][stripslashes($k)];
                    } else {
                        $process[$key][stripslashes($k)] = stripslashes($v);
                    }
                }
            }
            unset($process);
            $alreadyRan = true;
        }
        return $this;
    }
}

/**
* Facility to handle response to client
*/
class BResponse extends BClass
{
    /**
    * Response content MIME type
    *
    * @var string
    */
    protected $_contentType = 'text/html';

    protected $_contentPrefix;

    protected $_contentSuffix;

    /**
    * Content to be returned to client
    *
    * @var mixed
    */
    protected $_content;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BResponse
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Escape HTML
    *
    * @param string $str
    * @return string
    */
    public static function q($str)
    {
        if (is_null($str)) {
            return '';
        }
        if (!is_scalar($str)) {
            var_dump($str);
            return ' ** ERROR ** ';
        }
        return htmlspecialchars($str);
    }

    /**
    * Alias for BRequest::i()->cookie()
    *
    * @param string $name
    * @param string $value
    * @param int $lifespan
    * @param string $path
    * @param string $domain
    * @return BResponse
    */
    public function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        BRequest::i()->cookie($name, $value, $lifespan, $path, $domain);
        return $this;
    }

    /**
    * Set response content
    *
    * @param mixed $content
    */
    public function set($content)
    {
        $this->_content = $content;
        return $this;
    }

    /**
    * Add content to response
    *
    * @param mixed $content
    */
    public function add($content)
    {
        $this->_content = (array)$this->_content+(array)$content;
        return $this;
    }

    /**
    * Set or retrieve response content MIME type
    *
    * @param string $type 'json' will expand to 'application/json'
    * @return BResponse|string
    */
    public function contentType($type=BNULL)
    {
        if (BNULL===$type) {
            return $this->_contentType;
        }
        $this->_contentType = $type;
        return $this;
    }

    /**
    * Set or retrieve response content prefix string
    *
    * @param string $string
    * @return BResponse|string
    */
    public function contentPrefix($string=BNULL)
    {
        if (BNULL===$string) {
            return $this->_contentPrefix;
        }
        $this->_contentPrefix = $string;
        return $this;
    }

    /**
    * Set or retrieve response content suffix string
    *
    * @param string $string
    * @return BResponse|string
    */
    public function contentSuffix($string=BNULL)
    {
        if (BNULL===$string) {
            return $this->_contentSuffix;
        }
        $this->_contentSuffix = $string;
        return $this;
    }

    /**
    * Send json data as a response (for json API implementation)
    *
    * @param mixed $data
    */
    public function json($data)
    {
        $this->contentType('application/json')->set(BUtil::toJson($data))->render();
    }

    /**
    * Send file download to client
    *
    * @param string $filename
    * @return exit
    */
    public function sendFile($source, $filename=null)
    {
        BSession::i()->close();
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($source));
        header('Last-Modified: ' . date('r'));
        header('Content-Disposition: attachment; filename=' . $filename ? $filename : basename($source));
        $fs = fopen($source, 'rb');
        $fd = fopen('php://output', 'wb');
        while (!feof($fs)) fwrite($fd, fread($fs, 8192));
        fclose($fs);
        fclose($fd);
        $this->shutdown(__METHOD__);
    }

    /**
    * Send text content as a file download to client
    *
    * @param string $content
    * @return exit
    */
    public function sendContent($content, $filename='download.txt')
    {
        BSession::i()->close();
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . strlen($content));
        header('Last-Modified: ' . date('r'));
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $content;
        $this->shutdown(__METHOD__);
    }

    /**
    * Send status response to client
    *
    * @param int $status Status code number
    * @param string $message Message to be sent to client
    * @param bool $output Proceed to output content and exit
    * @return BResponse|exit
    */
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

    /**
    * Output the response to client
    *
    * @param string $type Optional content type
    * @return exit
    */
    public function output($type=null)
    {
        BPubSub::i()->fire('BResponse::output.before', array('content'=>&$this->_content));
        if (!is_null($type)) {
            $this->contentType($type);
        }
        BSession::i()->close();
        header('Content-Type: '.$this->_contentType);
        if ($this->_contentType=='application/json') {
            $this->_content = is_string($this->_content) ? $this->_content : BUtil::toJson($this->_content);
        } elseif (is_null($this->_content)) {
            $this->_content = BLayout::i()->render();
        }

        echo $this->_contentPrefix;
        print_r($this->_content);
        echo $this->_contentSuffix;

        BPubSub::i()->fire('BResponse::output.after', array('content'=>&$this->_content));

        $this->shutdown(__METHOD__);
    }

    /**
    * Alias for output
    *
    */
    public function render()
    {
        $this->output();
    }

    /**
    * Redirect browser to another URL
    *
    * @param string $url URL to redirect
    * @param int $status Default 302, another possible value 301
    */
    public function redirect($url, $status=302)
    {
        BSession::i()->close();
        $this->status($status, null, false);
        header("Location: {$url}");
        $this->shutdown(__METHOD__);
    }

    public function httpsRedirect()
    {
        $this->redirect(str_replace('http://', 'https://', BApp::baseUrl(true)));
    }

    public function shutdown($lastMethod=null)
    {
        BPubSub::i()->fire('BResponse::shutdown', array('last_method'=>$lastMethod));
        BSession::i()->close();
        exit;
    }
}

/**
* Controller Route Node
*/
class BRouteNode
{
    /**
    * Route Children
    *
    * @var BRouteNode
    */
    protected $_children = array();

    /**
    * Route Observers
    *
    * @var BRouteObserver
    */
    protected $_observers = array();

    /**
    * Route match information after it has been found during dispatch
    *
    * @var array
    */
    protected $_match;

    /**
    * Get Route child
    *
    * @param string $type
    * @param string $key
    * @param boolean $create whether to create a new one if not found
    */
    public function child($type, $key=null, $create=false)
    {
        if (is_null($key)) {
            return !empty($this->_children[$type]) ? $this->_children[$type] : array();
        }
        if (empty($this->_children[$type][$key])) {
            if ($create) {
                $node = new BRouteNode();
                $this->_children[$type][$key] = $node;
            } else {
                return null;
            }
        }
        return $this->_children[$type][$key];
    }

    public function children()
    {
        return $this->_children;
    }

    /**
    * Add an observer to the route node
    *
    * @param mixed $callback
    * @param array $args
    * @param boolean $multiple whether to allow multiple observers for the route
    */
    public function observe($callback, $args=null, $multiple=false)
    {
        $observer = new BRouteObserver();
        $observer->callback = $callback;
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $observer->moduleName = $moduleName;
        }
        if (!empty($args)) {
            $observer->args = $args;
        }
        if ($multiple || empty($this->_observers)) {
            $this->_observers[] = $observer;
        } else {
            //$this->_observers = BUtil::arrayMerge($this->_observers[0], $observer);
            $this->_observers[0] = $observer;
        }
        return $this;
    }

    /**
    * Retrieve next valid (not skipped) observer
    *
    * @return BRouteObserver
    */
    public function validObserver()
    {
        foreach ($this->_observers as $o) {
            if (!$o->skip) return $o;
        }
        return null;
    }

    /**
    * Set or retrieve route match data
    *
    * @param array $data
    * @return array|BRouteNode
    */
    public function match($data=null)
    {
        if (is_null($data)) {
            return $this->_match;
        }
        $this->_match = $data;
        if (!empty($data['action'])) {
            $data['observer']->callback .= '.'.$data['action'];
        }
        return $this;
    }

    public function __destruct()
    {
        unset($this->_observers, $this->_children, $this->_match);
    }
}

/**
* Controller route observer
*/
class BRouteObserver
{
    /**
    * Observer callback
    *
    * @var mixed
    */
    public $callback;

    /**
    * Module name that registered this observer
    *
    * @var string
    */
    public $moduleName;

    /**
    * Callback arguments
    *
    * @var array
    */
    public $args;

    /**
    * Whethre to skip the route when trying another
    *
    * @var boolean
    */
    public $skip;
}

/**
* Front controller class to register and dispatch routes
*/
class BFrontController extends BClass
{
    /**
    * Array of routes
    *
    * @var array
    */
    protected $_routes = array();

    /**
    * Default routes if route not found in tree
    *
    * @var array
    */
    protected $_defaultRoutes = array('default'=>array('callback'=>array('BActionController', 'noroute')));

    /**
    * Current route, empty if front controller dispatch wasn't run yet
    *
    * @var mixed
    */
    protected $_currentRoute;

    /**
    * Tree of routes
    *
    * @var array
    */
    protected $_routeTree = array();

    /**
    * Templates to generate URLs based on routes
    *
    * @var array
    */
    protected $_urlTemplates = array();

    /**
    * Current controller name
    *
    * @var string
    */
    protected $_controllerName;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BFrontController
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Save RESTful route in tree
    *
    * @param string $route "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
    * @param mixed $callback PHP callback
    * @param array $args Route arguments
    * @param mixed $multiple Allow multiple callbacks for the same route
    */
    public function saveRoute($route, $callback=null, $args=null, $multiple=false)
    {
        list($method, $route) = explode(' ', $route, 2);
        if (($module = BModuleRegistry::i()->currentModule())) {
            $route = trim($module->url_prefix, '/').$route;
        }
        $route = ltrim($route, '/');

        if (!isset($this->_routeTree[$method])) {
            $this->_routeTree[$method] = new BRouteNode();
        }
        /** @var BRouteNode */
        $node = $this->_routeTree[$method];
        $routeArr = explode('/', $route);
        foreach ($routeArr as $r) {
            if ($r!=='' && ($r[0]===':' || $r[0]==='*' || $r[0]==='.')) {
                $node = $node->child($r[0], $r, true);
            } else {
                $node = $node->child('/', $r==='' ? '__EMPTY__' : $r, true);
            }
        }
        $node->observe($callback, $args, $multiple);

        return $this;
    }

    /**
    * Find a route in the tree
    *
    * @param string $route RESTful route
    * @return array|null Route node or null if not found
    */
    public function findRoute($route=null)
    {
        if (is_null($route)) {
            $route = BRequest::i()->rawPath();
        }
        if (strpos($route, ' ')===false) {
            $method = BRequest::i()->method();
        } else {
            list($method, $route) = explode(' ', $route, 2);
        }
        if (empty($this->_routeTree[$method])) {
            return null;
        }
        $requestArr = $route=='' ? array('') : explode('/', ltrim($route, '/'));
        $node = $this->_routeTree[$method];
        $routeName = array($method.' ');
        $params = array();
        $dynAction = null;
        $observer = null;

        foreach ($requestArr as $i=>$r) {
            $r1 = $r==='' ? '__EMPTY__' : $r; // adjusted url route part
            $nextR = isset($requestArr[$i+1]) ? $requestArr[$i+1] : null; // next url route part
            $nextR = $nextR==='' ? '__EMPTY__' : $nextR; // adjusted

            if ($r1==='__EMPTY__' && is_null($nextR) // if last route part, url ends with slash
                && ($child = $node->child('/', $r1)) // there's route child
                && ($observer = $child->validObserver()) // and a valid observer
            ) {
                $node = $child; // found the route node
                $routeName[] = $r;
                break; // this is a final node
            }
            if (($children = $node->child('*'))) { // if has children of type 'anything'
                foreach ($children as $k=>$n) { // iterate children
                    if (!($observer = $n->validObserver())) continue; // if no valid observers found, next
                    $params[substr($k, 1)] = join('/', array_slice($requestArr, $i)); // combine rest of request string into param
                    $node = $n; // found the route node
                    break 2; // this is a final node
                }
            }
            if (($children = $node->child('.'))) { // if route has children of type 'actions'
                foreach ($children as $k=>$n) { // iterate children
                    if (($n->child(':') || $n->child('/', $nextR)) // if has children 'param' or matching route
                        || (is_null($nextR) && ($observer = $n->validObserver())) // OR final part and valid observer
                    ) {
                        $node = $n; // found the route node
                        $dynAction = $r==='' ? 'index' : $r; // assign dynamic controller action name
                        continue 2; // if $nextR continue to next child, otherwise final node
                    }
                }
            }
            if (($children = $node->child(':'))) { // if route has children of type 'param'
                foreach ($children as $k=>$n) { // iterate children
                    if ((!is_null($nextR) && ($n->child(':') || $n->child('/', $nextR))) // if has children of 'param' or matching route
                        || (is_null($nextR) && ($observer = $n->validObserver())) // OR final part and valid observer
                    ) {
                        $params[substr($k, 1)] = $r; // assign parameter
                        $node = $n; // found the route node
                        continue 2; // if $nextR continue to next child, otherwise final node
                    }
                }
            }
            if (!$dynAction && ($child = $node->child('/', $r1))) { // if no dynamic action and route has matching route child
                $node = $child; // found next route node
                $routeName[] = $r; // add url part to route name
                if (is_null($nextR)) { // is this the final url route part?
                    if (!($observer = $node->validObserver())) { // is there a valid observer
                        return null; // no valid observer found, no route found
                    }
                    break; // this is a final route
                }
                continue; // continue to next url route part
            }
            return null; // none of the cases above match, no route found
        }

        $node->match(array('route_name'=>join('/', $routeName), 'params'=>$params, 'observer'=>$observer, 'action'=>$dynAction));
        return $node;
    }

    /**
    * Declare RESTful route
    *
    * @param string $route
    *   - "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
    *   - "/prefix/*anything"
    *   - "/prefix/.action" : $args=array('_methods'=>array('create'=>'POST', ...))
    * @param mixed $callback PHP callback
    * @param array $args Route arguments
    * @param string $name optional name for the route for URL templating
    * @return BFrontController for chain linking
    */
    public function route($route, $callback=null, $args=null, $name=null)
    {
        if (is_array($route)) {
            foreach ($route as $a) {
                if (is_null($callback)) {
                    $this->route($a[0], $a[1], isset($a[2])?$a[2]:null, isset($a[3])?$a[3]:null);
                } else {
                    $this->route($a, $callback, $args);
                }
            }
            return;
        }

        $this->saveRoute($route, $callback, $args, false);

        $this->_routes[$route] = $callback; // TODO: remove, for debugging purposes only
        if (!is_null($name)) {
            $this->_urlTemplates[$name] = $route;
        }
        return $this;
    }

    /**
    * Set default route
    *
    * @param mixed $callback PHP callback
    * @param mixed $args Route arguments
    * @param mixed $name optional route name
    * @return BFrontController
    */
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

    /**
    * Retrieve current route node
    *
    */
    public function currentRoute()
    {
        return $this->_currentRoute;
    }

    /**
    * Dispatch current route
    *
    * @param string $route optional route for explicit route dispatch
    * @return BFrontController
    */
    public function dispatch($route=null)
    {
        $attempts = 0;
        $forward = true; // null: no forward, true: try next route, array: forward without new route
        while (($attempts++<100) && $forward) {
            if (true===$forward) { // if first time or try next route
                $node = $this->findRoute($route);

                $this->_currentRoute = $node;

                if (!$node) {
                    $callback = $this->_defaultRoutes['default']['callback'];
                    $params = array();
                    $args = array();
                } else {
                    $match = $node->match();
                    $observer = $match['observer'];
                    $callback = $observer->callback;
                    $params = (array)$match['params'];
                    $args = (array)$observer->args;
                    if ($observer->moduleName) {
                        BModuleRegistry::i()->currentModule($observer->moduleName);
                    }
                }
                if (is_callable($callback)) {
                    call_user_func_array($callback, $args);
                    return;
                }
                if (is_string($callback)) {
                    $r = explode('.', $callback);
                    if (sizeof($r)==2) $callback = $r;
                }
                $controllerName = $callback[0];
                $actionName = $callback[1];
                $request = BRequest::i();
            }
            if (is_array($forward)) {
                list($actionName, $forwardControllerName, $params) = $forward;
                if ($forwardControllerName) {
                    $controllerName = $forwardControllerName;
                }
            }
            $request->initParams($params);
            $controller = BClassRegistry::i()->instance($controllerName, array(), true);
            $controller->dispatch($actionName, $args);
            $forward = $controller->forward();
        }

        if ($attempts==100) {
            throw new BException(BApp::t('Reached 100 route iterations: %s', print_r($callback,1)));
        }
    }

    public function debug()
    {
        echo "<pre>"; print_r($this->_routeTree); echo "</pre>";
    }
}

/**
* Action controller class for route action declarations
*/
class BActionController extends BClass
{
    /**
    * Action parameters
    *
    * @var array
    */
    public $params = array();

    /**
    * Current action name
    *
    * @var string
    */
    protected $_action;

    /**
    * Forward location. If set the dispatch will loop and forward to next action
    *
    * @var string|null
    */
    protected $_forward;

    /**
    * Prefix for action methods
    *
    * @var string
    */
    protected $_actionMethodPrefix = 'action_';

    /**
    * Shortcut for fetching layout views
    *
    * @param string $viewname
    * @return BView
    */
    public function view($viewname)
    {
        return BLayout::i()->view($viewname);
    }

    /**
    * Dispatch action within the action controller class
    *
    * @param string $actionName
    * @param array $args Action arguments
    */
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
        if (!$this->forward()) {
            $this->tryDispatch($actionName, $args);
        }
        if (!$this->forward()) {
            $this->afterDispatch($args);
        }
        return $this;
    }

    /**
    * Try to dispatch action and catch exception if any
    *
    * @param string $actionName
    * @param array $args
    */
    public function tryDispatch($actionName, $args)
    {
        if (is_callable($actionName)) {
            try {
                call_user_func($actionName);
            } catch (DActionException $e) {
                $this->sendError($e->getMessage());
            } catch (Exception $e) {
echo "<pre>"; print_r($e); echo "</pre>";
            }
            return $this;
        }
        $actionMethod = $this->_actionMethodPrefix.$actionName;
        if (!is_callable(array($this, $actionMethod))) {
            $this->forward('noroute');
            return $this;
        }
        try {
            $this->$actionMethod($args);
        } catch (DActionException $e) {
            $this->sendError($e->getMessage());
        } catch (Exception $e) {
echo "<pre>"; print_r($e); echo "</pre>";
        }
        return $this;
    }

    /**
    * Instruct front controller to try the next route
    *
    */
    public function tryNextRoute()
    {
        $front = BFrontController::i();
        $match = $front->currentRoute()->match();
        $match['observer']->skip = true;
        $this->forward(true);
        return $this;
    }

    /**
    * Forward to another action or retrieve current forward
    *
    * @param string $actionName
    * @param string $controllerName
    * @param array $params
    * @return string|null|BActionController
    */
    public function forward($actionName=BNULL, $controllerName=null, array $params=array())
    {
        if (BNULL===$actionName) {
            return $this->_forward;
        }
        if (true===$actionName) {
            $this->_forward = true;
        } else {
            $this->_forward = array($actionName, $controllerName, $params);
        }
        return $this;
    }

    /**
    * Authorize logic for current action controller, based on arguments
    *
    * Use $this->_action to fetch current action
    *
    * @param array $args
    */
    public function authorize($args=array())
    {
        return true;
    }

    /**
    * Execute before dispatch and return resutl
    * If false, do not dispatch action, and either forward or default
    *
    * @return bool
    */
    public function beforeDispatch()
    {
        return true;
    }

    /**
    * Execute after dispatch
    *
    */
    public function afterDispatch()
    {

    }

    /**
    * Send error to the browser
    *
    * @param string $message to be in response
    * @return exit
    */
    public function sendError($message)
    {
        BResponse::i()->set($message)->status(503);
    }

    /**
    * Default unauthorized action
    *
    */
    public function action_unauthorized()
    {
        BResponse::i()->set("Unauthorized")->status(401);
    }

    /**
    * Default not found action
    *
    */
    public function action_noroute()
    {
        BResponse::i()->set("Route not found")->status(404);
    }

    /**
    * Render output
    *
    * Final method to be called in standard action method
    */
    public function renderOutput()
    {
        BResponse::i()->output();
    }
}
