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
    public static function ip()
    {
        return !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
    * Server local IP
    *
    * @return string
    */
    public static function serverIp()
    {
        return !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
    }

    /**
    * Server host name
    *
    * @return string
    */
    public static function serverName()
    {
        return !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
    }

    /**
    * Host name from request headers
    *
    * @return string
    */
    public static function httpHost()
    {
        return !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
    }

    /**
    * Whether request is SSL
    *
    * @return bool
    */
    public static function https()
    {
        return !empty($_SERVER['HTTPS']);
    }

    public static function scheme()
    {
        return static::https() ? 'https' : 'http';
    }

    /**
    * Whether request is AJAX
    *
    * @return bool
    */
    public static function xhr()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest';
    }

    /**
    * Request method:
    *
    * @return string GET|POST|HEAD|PUT|DELETE
    */
    public static function method()
    {
        return !empty($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    }

    /**
    * Web server document root dir
    *
    * @return string
    */
    public static function docRoot()
    {
        return !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : null;
    }

    /**
    * Web root path for current application
    *
    * If request is /folder1/folder2/index.php, return /folder1/folder2/
    *
    * @param $parent if required a parent of current web root, specify depth
    * @return string
    */
    public static function webRoot($parentDepth=null)
    {
        $root = !empty($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : null;
        if ($parentDepth) {
            $arr = explode('/', $root);
            $len = sizeof($arr)-$parentDepth;
            $root = $len>1 ? join('/', array_slice($arr, 0, $len)) : '/';
        }
        return $root;
    }

    /**
    * Full base URL, including scheme and domain name
    *
    * @param null|boolean $forceSecure - if not null, force scheme
    * @param boolean $includeQuery - add origin query string
    * @return string
    */
    public static function baseUrl($forceSecure=null, $includeQuery=false)
    {
        if (is_null($forceSecure)) {
            $scheme = static::https() ? 'https' : 'http';
        } else {
            $scheme = $forceSecure ? 'https' : 'http';
        }
        $url = $scheme.'://'.static::serverName().static::webRoot();
        if ($includeQuery && ($query = static::rawGet())) {
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
    public static function path($offset, $length=null)
    {
        if (empty($_SERVER['PATH_INFO'])) {
            return null;
        }
        $path = explode('/', ltrim($_SERVER['PATH_INFO'], '/'));
        if (is_null($length)) {
            return isset($path[$offset]) ? $path[$offset] : null;
        }
        return join('/', array_slice($path, $offset, true===$length ? null : $length));
    }

    /**
    * Raw path string
    *
    * @return string
    */
    public static function rawPath()
    {
        return !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
    }

    /**
    * Request query variables
    *
    * @param string $key
    * @return array|string|null
    */
    public static function get($key=null)
    {
        return is_null($key) ? $_GET : (isset($_GET[$key]) ? $_GET[$key] : null);
    }

    /**
    * Request query as string
    *
    * @return string
    */
    public static function rawGet()
    {
        return !empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    }

    /**
    * Request POST variables
    *
    * @param string|null $key
    * @return array|string|null
    */
    public static function post($key=null)
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
    public static function rawPost()
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
    public static function json($asObject=false)
    {
        return BUtil::fromJson(static::rawPost(), $asObject);
    }

    /**
    * Request variable (GET|POST|COOKIE)
    *
    * @param string|null $key
    * @return array|string|null
    */
    public static function request($key=null)
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
    public static function cookie($name, $value=null, $lifespan=null, $path=null, $domain=null)
    {
        if (is_null($value)) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
        if (false===$value) {
            return static::cookie($name, '', -1000);
        }

        $config = BConfig::i()->get('cookie');
        $lifespan = !is_null($lifespan) ? $lifespan : $config['timeout'];
        $path = !is_null($path) ? $path : (!empty($config['path']) ? $config['path'] : static::webRoot());
        $domain = !is_null($domain) ? $domain : (!empty($config['domain']) ? $config['domain'] : static::httpHost());

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
    public static function referrer($default=null)
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $default;
    }

    /**
    * Check whether the request can be CSRF attack
    *
    * Uses HTTP_REFERER header to compare with current host and path.
    * By default only POST, DELETE, PUT requests are protected
    * Only these methods should be used for data manipulation.
    *
    * The following specific cases will return csrf true:
    * - posting from different host or web root path
    * - posting from https to http
    *
    * @see http://en.wikipedia.org/wiki/Cross-site_request_forgery
    *
    * @param array $methods Methods to check for CSRF attack
    * @return boolean
    */
    public static function csrf($methods=array('POST','DELETE','PUT'))
    {
        if (!in_array(static::method(), $methods)) {
            return false; // not one of checked methods, pass
        }
        if (!($ref = static::referrer())) {
            return true; // no referrer sent, high prob. csrf
        }
        $p = parse_url($ref);
        $p['path'] = preg_replace('#/+#', '/', $p['path']); // ignore duplicate slashes
        if ($p['host']!==static::httpHost() || strpos($p['path'], static::webRoot())!==0) {
            return true; // referrer host or doc root path do not match, high prob. csrf
        }
        return false; // not csrf
    }

    public static function currentUrl()
    {
        return static::scheme().'://'.static::httpHost().static::webRoot().static::rawPath()
            .(($q = static::rawGet()) ? '?'.$q : '');
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
    * @param boolean $fallbackToGet
    * @return array|string|null
    */
    public function params($key=null, $fallbackToGet=false)
    {
        if (is_null($key)) {
            return $this->_params;
        } elseif (isset($this->_params[$key]) && ''!==$this->_params[$key]) {
            return $this->_params[$key];
        } elseif ($fallbackToGet && !empty($_GET[$key])) {
            return $_GET[$key];
        } else {
            return null;
        }
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
    * @todo replace with filter_var_array
    *
    * @param array|object $data Array to be sanitized
    * @param array $config Configuration for sanitizing
    * @param bool $trim Whether to return only variables specified in config
    * @return array Sanitized result
    */
    public static function sanitize($data, $config, $trim=true)
    {
        $data = (array)$data;
        if ($trim) {
            $data = array_intersect_key($data, $config);
        }
        foreach ($data as $k=>&$v) {
            $filter = is_array($config[$k]) ? $config[$k][0] : $config[$k];
            $v = static::sanitizeOne($v, $filter);
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
    public static function sanitizeOne($v, $filter)
    {
        if (is_array($v)) {
            foreach ($v as $k=>&$v1) {
                $v1 = static::sanitizeOne($v1, $filter);
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
    public static function stripMagicQuotes()
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
    }

    public static function modRewriteEnabled()
    {
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $modRewrite = in_array('mod_rewrite', $modules);
        } else {
            $modRewrite =  getenv('HTTP_MOD_REWRITE')=='On' ? true : false;
        }
        return $modRewrite;
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
        BRequest::cookie($name, $value, $lifespan, $path, $domain);
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
    * @deprecated
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

    public function setContentType($type)
    {
        $this->_contentType = $type;
        return $this;
    }

    public function getContentType()
    {
        return $this->_contentType;
    }

    /**
    * Set or retrieve response content prefix string
    *
    * @deprecated
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

    public function setContentPrefix($type)
    {
        $this->_contentPrefix = $type;
        return $this;
    }

    public function getContentPrefix()
    {
        return $this->_contentPrefix;
    }

    /**
    * Set or retrieve response content suffix string
    *
    * @deprecated
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

    public function setContentSuffix($type)
    {
        $this->_contentSuffix = $type;
        return $this;
    }

    public function getContentSuffix()
    {
        return $this->_contentSuffix;
    }

    /**
    * Send json data as a response (for json API implementation)
    *
    * @param mixed $data
    */
    public function json($data)
    {
        $this->setContentType('application/json')->set(BUtil::toJson($data))->render();
    }

    public function fileContentType($fileName)
    {
        $type = 'application/octet-stream';
        switch (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            case 'jpeg': case 'jpg': $type = 'image/jpg'; break;
            case 'png': $type = 'image/png'; break;
            case 'gif': $type = 'image/gif'; break;
        }
        return $type;
    }

    /**
    * Send file download to client
    *
    * @param string $filename
    * @return exit
    */
    public function sendFile($source, $fileName=null, $disposition='attachment')
    {
        BSession::i()->close();

        if (!$fileName) {
            $fileName = basename($source);
        }

        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Length: ' . filesize($source));
        header('Last-Modified: ' . date('r'));
        header('Content-Type: '. $this->fileContentType($fileName));
        header('Content-Disposition: '.$disposition.'; filename=' . $fileName);

        //echo file_get_contents($source);
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
    public function sendContent($content, $fileName='download.txt', $disposition='attachment')
    {
        BSession::i()->close();
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: '.$this->fileContentType($fileName));
        header('Content-Length: ' . strlen($content));
        header('Last-Modified: ' . date('r'));
        header('Content-Disposition: '.$disposition.'; filename=' . $fileName);
        echo $content;
        $this->shutdown(__METHOD__);
    }

    /**
    * Send status response to client
    *
    * @param int $status Status code number
    * @param string $message Message to be sent to client
    * @param bool|string $output Proceed to output content and exit
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
        if (is_string($output)) {
            echo $output;
            exit;
        } elseif ($output) {
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
        BPubSub::i()->fire('BResponse::output.before', array('content'=>&$this->_content));

        echo $this->_contentPrefix;
        print_r($this->_content);
        echo $this->_contentSuffix;

        BPubSub::i()->fire('BResponse::output.after', array('content'=>$this->_content));

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
        $this->redirect(str_replace('http://', 'https://', BRequest::i()->currentUrl()));
    }

    /**
    * Send HTTP STS header
    *
    * @see http://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security
    */
    public function httpSTS()
    {
        header('Strict-Transport-Security: max-age=500; includeSubDomains');
        return $this;
    }

    public function nocache()
    {
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        return $this;
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
    * Route flags
    *
    * @var array
    */
    protected $_flags = array();

    /**
    * Route Children
    *
    * @var array(BRouteNode)
    */
    protected $_children = array();

    /**
    * Route Observers
    *
    * @var array(BRouteObserver)
    */
    protected $_observers = array();

    /**
    * Route match information after it has been found during dispatch
    *
    * @var array
    */
    protected $_match;

    /**
    * Set route flag
    *
    * - ? - allow trailing slash
    *
    * @todo make use of it
    *
    * @param string $flag
    * @param mixed $value
    * @return BRouteNode
    */
    public function flag($flag, $value=true)
    {
        $this->_flags[$flag] = $value;
        return $this;
    }

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
    * Partial route changes
    *
    * @var array
    */
    protected static $_routeChanges = array();

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
    * Change route part (usually 1st)
    *
    * @param string $partValue
    * @param mixed $options
    */
    public function changeRoute($from, $opt)
    {
        if (is_array($opt)) {
            $opt = array('to'=>$opt);
        }
        $type = !empty($opt['type']) ? $opt['type'] : 'first';
        unset($opt['type']);
        $this->_routeChanges[$type][$from] = $opt;
        return $this;
    }

    public static function processHref($href)
    {
        $href = ltrim($href, '/');
        if (!empty(static::$_routeChanges['first'])) {
            $rules = static::$_routeChanges['first'];
            $parts = explode('/', $href, 2);
            if (!empty($rules[$parts[0]])) {
                $href = $rules[$parts[0]]['to'].(isset($parts[1]) ? '/'.$parts[1] : '');
            }
        }
        return $href;
    }

    public function processRoutePath($route, $args=array())
    {
        if (!empty($args['module_name'])) {
            $module = BModuleRegistry::i()->module($args['module_name']);
            if ($module && ($prefix = $module->url_prefix)) {
                $route = $prefix.$route;
            }
        }
        #$route = static::processHref($route); // slower than alternative (replacing keys on saveRoute)
        return $route;
    }

    /**
    * Save RESTful route in tree
    *
    * @param string|array $fullRoute "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
    *           OR array('GET', '/part1/part2')
    *           Accepts multiple methods separated with pipe: 'GET|POST /part1/part2'
    * @param mixed $callback PHP callback
    * @param array $args Route arguments
    * @param mixed $multiple Allow multiple callbacks for the same route
    */
    public function saveRoute($fullRoute, $callback=null, $args=null, $multiple=false)
    {
        if (is_string($fullRoute)) {
            $fullRoute = explode(' ', $fullRoute, 2);
        }
        list($method, $route) = $fullRoute;
        if (empty($args['_processed'])) {
            $route = $this->processRoutePath($route, $args);
            $route = ltrim($route, '/');
        }

        if (strpos($method, '|')) {
            $args['_processed'] = true;
            foreach (explode('|', $method) as $m) {
                $this->saveRoute(array($m, $route), $callback, $args, $multiple);
            }
            return $this;
        }

        if (!isset($this->_routeTree[$method])) {
            $this->_routeTree[$method] = new BRouteNode();
        }
        /** @var BRouteNode */
        $node = $this->_routeTree[$method];
        $routeArr = explode('/', $route);

        if (!empty(static::$_routeChanges['first'][$routeArr[0]])) {
            $routeArr[0] = static::$_routeChanges['first'][$routeArr[0]];
        }

        foreach ($routeArr as $r) {
            $r0 = $r!=='' ? $r[0] : false;
            if ($r0==='?') {
                $node->flag($r0);
                $r = substr($r, 1);
                $r0 = $r!=='' ? $r[0] : false;
            }
            if ($r0===':' || $r0==='*' || $r0==='.') {
                $node = $node->child($r0, $r, true);
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

        if (empty($args['module_name'])) {
            $args['module_name'] = BModuleRegistry::currentModuleName();
        }
        BDebug::debug('ROUTE '.$route.': '.print_r($args,1));
        $this->_routes[$route][] = array('cb'=>$callback, 'args'=>$args, 'name'=>$name);

        if (!is_null($name)) {
            $this->_urlTemplates[$name] = $route;
        }
        return $this;
    }

    public function forward($from, $to, $args=array())
    {
        $args['target'] = $to;
        $this->route($from, array($this, '_forwardCallback'), $args);
        /*
        $this->route($from, function($args) {
            return array('forward'=>$this->processRoutePath($args['target'], $args));
        }, $args);
        */
        return $this;
    }

    protected function _forwardCallback($args)
    {
        return array('forward'=>$this->processRoutePath($args['target'], $args));
    }

    public function redirect($from, $to, $args=array())
    {
        $args['target'] = $to;
        $this->route($from, array($this, '_redirectCallback'), $args);
        /*
        $this->route($from, function($args) {
            if (!empty($args['module_name'])) {
                $module = BModuleRegistry::i()->module($args['module_name']);
                $baseUrl = $module ? $module->baseHref() : BApp::i()->baseUrl();
            } else {
                $baseUrl = BApp::i()->baseHref();
            }
            BResponse::i()->redirect($baseUrl.'/'.$args['target']);
        }, $args);
        */
        return $this;
    }

    protected function _redirectCallback($args)
    {#print_r($args); exit;
        if (!empty($args['module_name'])) {
            $module = BModuleRegistry::i()->module($args['module_name']);
            $baseUrl = $module ? $module->baseHref() : BApp::i()->baseUrl();
        } else {
            $baseUrl = BApp::i()->baseHref();
        }
        BResponse::i()->redirect($baseUrl.'/'.$args['target']);
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
    * Convert collected routes into tree
    *
    * @return BFrontController
    */
    public function processRoutes()
    {
        foreach ($this->_routes as $name=>$routes) {
            foreach ($routes as $route) {
                $this->saveRoute($name, $route['cb'], $route['args'], false);
            }
        }
        return $this;
    }

    /**
    * Dispatch current route
    *
    * @param string $route optional route for explicit route dispatch
    * @return BFrontController
    */
    public function dispatch($route=null)
    {
        BPubSub::i()->fire(__METHOD__.'.before');

        $this->processRoutes();

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
                    BModuleRegistry::i()->currentModule(null);
                    $result = call_user_func($callback, $args);
                    $forward = is_array($result) && !empty($result['forward']) ? $result['forward'] : false;
                    continue;
                }
                if (is_string($callback)) {
                    foreach (array('.', '->') as $sep) {
                        $r = explode($sep, $callback);
                        if (sizeof($r)==2) {
                            $callback = $r;
                            break;
                        }
                    }
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
            BDebug::debug('DISPATCH: '.$controllerName.'.'.$actionName.' '.print_r($params,1));
            $request->initParams($params);
            $controller = BClassRegistry::i()->instance($controllerName, array(), true);
            $controller->dispatch($actionName, $args);
            $forward = $controller->forward();
        }

        if ($attempts==100) {
            throw new BException(BLocale::_('Reached 100 route iterations: %s', print_r($callback,1)));
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
        }
        $authenticated = $this->authenticate($args);
        if (!$authenticated && $actionName!=='unauthenticated') {
            $this->forward('unauthenticated');
            return $this;
        }
        if ($authenticated && !$this->authorize($args) && $actionName!=='unauthorized') {
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
            } catch (Exception $e) {
                BDebug::exceptionHandler($e);
                $this->sendError($e->getMessage());
            }
            return $this;
        }
        $actionMethod = $this->_actionMethodPrefix.$actionName;
        if (($reqMethod = BRequest::i()->method())!=='GET') {
            $tmpMethod = $actionMethod.'__'.$reqMethod;
            if (is_callable(array($this, $tmpMethod))) {
                $actionMethod = $tmpMethod;
            }
        }
        if (!is_callable(array($this, $actionMethod))) {
            $this->forward('noroute');
            return $this;
        }
        try {
            $this->$actionMethod($args);
        } catch (Exception $e) {
            BDebug::exceptionHandler($e);
            $this->sendError($e->getMessage());
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
    public function forward($actionName=null, $controllerName=null, array $params=array())
    {
        if (is_null($actionName)) {
            return $this->_forward;
        } elseif (true===$actionName) {
            $this->_forward = true;
        } else {
            $this->_forward = array($actionName, $controllerName, $params);
        }
        return $this;
    }

    public function getForward()
    {
        return $this->_forward;
    }

    /**
    * Authenticate logic for current action controller, based on arguments
    *
    * Use $this->_action to fetch current action
    *
    * @param array $args
    */
    public function authenticate($args=array())
    {
        return true;
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
        BPubSub::i()->fire(__METHOD__);
        return true;
    }

    /**
    * Execute after dispatch
    *
    */
    public function afterDispatch()
    {
        BPubSub::i()->fire(__METHOD__);
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
    public function action_unauthenticated()
    {
        BResponse::i()->set("Unauthenticated")->status(401);
    }

    /**
    * Default unauthorized action
    *
    */
    public function action_unauthorized()
    {
        BResponse::i()->set("Unauthorized")->status(403);
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

    /**
    * Translate string within controller action
    *
    * @param string $string
    * @param array $params
    * @param string $module if null, try to get current controller module
    */
    public function _($string, $params=array(), $module=null)
    {
        if (empty($module)) {
            $module = BModuleRegistry::currentModuleName();
        }
        return BLocale::_($string, $params, $module);
    }
}
