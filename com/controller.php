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

    /**
    * Server protocol (HTTP/1.0 or HTTP/1.1)
    *
    * @return string
    */
    public static function serverProtocol()
    {
        $protocol = "HTTP/1.0";
        if(isset($_SERVER['SERVER_PROTOCOL']) && stripos($_SERVER['SERVER_PROTOCOL'],"HTTP") >= 0){
            $protocol = $_SERVER['SERVER_PROTOCOL'];
        }
        return $protocol;
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
        return !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : null;
    }

    /**
    * Entry point script web path
    *
    * @return string
    */
    public static function scriptName()
    {
        return !empty($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : null;
    }

    /**
    * Entry point script file name
    *
    * @return string
    */
    public static function scriptFilename()
    {
        return !empty($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']) : null;
    }

    /**
    * Entry point directory name
    *
    * @return string
    */
    public static function scriptDir()
    {
        return ($script = static::scriptFilename()) ? dirname($script) : null;
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
        if (empty($_SERVER['SCRIPT_NAME'])) {
            return null;
        }
        $root = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
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
    * @todo optional omit http(s):
    * @param null|boolean $forceSecure - if not null, force scheme
    * @param boolean $includeQuery - add origin query string
    * @return string
    */
    public static function baseUrl($forceSecure=null, $includeQuery=false)
    {
        if (is_null($forceSecure)) {
            $scheme = static::https() ? 'https:' : 'http:';
        } else {
            $scheme = $forceSecure ? 'https:' : 'http:';
        }
        $url = $scheme.'//'.static::serverName().static::webRoot();
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
#echo "<pre>"; print_r($_SERVER); exit;
        return !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] :
            (!empty($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '/');
            /*
                (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] :
                    (!empty($_SERVER['SERVER_URL']) ? $_SERVER['SERVER_URL'] : '/')
                )
            );*/
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

    public static function receiveFiles($source, $targetDir, $typesRegex=null)
    {
        if (is_string($source) && !empty($_FILES[$source])) {
            $source = $_FILES[$source];
        }
        if (empty($source)) {
            return;
        }
        $result = array();
        foreach ($source['error'] as $key=>$error) {
            if ($error==UPLOAD_ERR_OK) {
                $tmpName = $source['tmp_name'][$key];
                $name = $source['name'][$key];
                $type = $source['type'][$key];
                if (!is_null($typesRegex) && !preg_match('#'.$typesRegex.'#i', $type)) {
                    $result[$key] = array('error'=>'invalid_type', 'type'=>$type, 'name'=>$name);
                    continue;
                }
                move_uploaded_file($tmpName, $targetDir.'/'.$name);
                $result[$key] = array('name'=>$name, 'type'=>$type, 'target'=>$targetDir.'/'.$name);
            } else {
                $result[$key] = array('error'=>$error);
            }
        }
        return $result;
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
        return static::scheme().'://'.static::httpHost().static::webRoot()
            .static::rawPath().(($q = static::rawGet()) ? '?'.$q : '');
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
    public function param($key=null, $fallbackToGet=false)
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
    * Alias for legacy code
    *
    * @deprecated
    * @param mixed $key
    * @param mixed $fallbackToGet
    */
    public function params($key=null, $fallbackToGet=false)
    {
        return $this->param($key, $fallbackToGet);
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
    protected static $_httpStatuses = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authorative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version Not Supported',
    );
    /**
    * Response content MIME type
    *
    * @var string
    */
    protected $_contentType = 'text/html';

    protected $_charset = 'UTF-8';

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
    * @param string $type
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
            if (!empty(static::$_httpStatuses[$status])) {
                $message = static::$_httpStatuses[$status];
            } else {
                $message = 'Unknown';
            }
        }
        $protocol = BRequest::i()->serverProtocol();
        header("{$protocol} {$status} {$message}");
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
        //BSession::i()->close();
        header('Content-Type: '.$this->_contentType.'; charset='.$this->_charset);
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
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // Current time
        header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        header("Pragma: no-cache");
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

    protected $_routesRegex = array();

    /**
    * Partial route changes
    *
    * @var array
    */
    protected static $_routeChanges = array();

    /**
    * Current route node, empty if front controller dispatch wasn't run yet
    *
    * @var mixed
    */
    protected $_currentRoute;

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

    public function __construct()
    {
        $this->route('_ /noroute', 'BActionController.noroute', array(), null, false);
    }

    /**
    * Change route part (usually 1st)
    *
    * @param string $partValue
    * @param mixed $options
    */
    public function changeRoute($from, $opt)
    {
        if (!is_array($opt)) {
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
                $href = ($part0 = $rules[$parts[0]]['to'])
                    .($part0 && isset($parts[1]) ? '/' : '')
                    .(isset($parts[1]) ? $parts[1] : '');
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
    public function route($route, $callback=null, $args=null, $name=null, $multiple=true)
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
        if (empty($this->_routes[$route])) {
            $this->_routes[$route] = new BRouteNode(array('route_name'=>$route));
        }
        $this->_routes[$route]->observe($callback, $args, $multiple);

        if (!is_null($name)) {
            $this->_urlTemplates[$name] = $route;
        }
        return $this;
    }

    public function findRoute($requestRoute=null)
    {
        if (is_null($requestRoute)) {
            $requestRoute = BRequest::i()->rawPath();
        }
        if (strpos($requestRoute, ' ')===false) {
            $requestRoute = BRequest::i()->method().' '.$requestRoute;
        }
        if (!empty($this->_routes[$requestRoute])) {
            BDebug::debug('DIRECT ROUTE: '.$requestRoute);
            return $this->_routes[$requestRoute];
        }

        BDebug::debug('FIND ROUTE: '.$requestRoute);
        foreach ($this->_routes as $route) {
            if ($route->match($requestRoute)) {
                return $route;
            }
        }
        return null;
    }

    /**
    * Convert collected routes into tree
    *
    * @return BFrontController
    */
    public function processRoutes()
    {
        uasort($this->_routes, function($a, $b) {
            $a1 = $a->num_parts;
            $b1 = $b->num_parts;
            return $a1<$b1 ? 1 : ($a1>$b1 ? -1 : 0);
        });
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
        return $this->processRoutePath($args['target'], $args);
    }

    public function redirect($from, $to, $args=array())
    {
        $args['target'] = $to;
        $this->route($from, array($this, '_redirectCallback'), $args);
        return $this;
    }

    protected function _redirectCallback($args)
    {
        BResponse::i()->redirect(BApp::href($args['target']));
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
    * @param string $requestRoute optional route for explicit route dispatch
    * @return BFrontController
    */
    public function dispatch($requestRoute=null)
    {
        BPubSub::i()->fire(__METHOD__.'.before');

        $this->processRoutes();

        $attempts = 0;
        $forward = true; // null: no forward, true: try next route, array: forward without new route
#echo "<pre>"; print_r($this->_routes); exit;
        while (($attempts++<100) && $forward) {
            $route = $this->findRoute($requestRoute);
            if (!$route) {
                $route = $this->findRoute('_ /noroute');
            }
            $this->_currentRoute = $route;
            $forward = $route->dispatch();

            if (is_array($forward)) {
                list($actionName, $forwardCtrlName, $params) = $forward;
                $controllerName = $forwardCtrlName ? $forwardCtrlName : $route->controller_name;
                $requestRoute = '_ /forward';
                $this->route($requestRoute, $controllerName.'.'.$actionName, array(), null, false);
            }
        }

        if ($attempts>=100) {
            echo "<pre>"; print_r($route); echo "</pre>";
            BDebug::error(BLocale::_('BFrontController: Reached 100 route iterations: %s', print_r($route,1)));
        }
    }

    public function debug()
    {
        echo "<pre>"; print_r($this->_routes); echo "</pre>";
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
    * Route Observers
    *
    * @var array(BRouteObserver)
    */
    protected $_observers = array();

    public $controller_name;
    public $action_idx;
    public $action_name;
    public $route_name;
    public $regex;
    public $num_parts; // specificity for sorting
    public $params;
    public $params_values;

    public function __construct($args=array())
    {
        foreach ($args as $k=>$v) {
            $this->$k = $v;
        }

        // convert route name into regex and save param references
        if ($this->route_name[0]==='^') {
            $this->regex = '#'.$this->route_name.'#';
            return;
        }
        $a = explode(' ', $this->route_name);
        if ($a[1]==='/') {
            $this->regex = '#^('.$a[0].') (/)$#';
        } else {
            $a1 = explode('/', trim($a[1], '/'));
            $this->num_parts = sizeof($a1);
            $paramId = 2;
            foreach ($a1 as $i=>$k) {
                $k0 = $k[0];
                if ($k0===':') { // optional param
                    $this->params[++$paramId] = substr($k, 1);
                    $a1[$i] = '([^/]*)';
                }
                if ($k0==='!') { // required param
                    $this->params[++$paramId] = substr($k, 1);
                    $a1[$i] = '([^/]+)';
                }
                elseif ($k0==='*') { // param until end of url
                    $this->params[++$paramId] = substr($k, 1);
                    $a1[$i] = '(.*)';
                }
                elseif ($k0==='.') { // dynamic action
                    $this->params[++$paramId] = substr($k, 1);
                    $this->action_idx = $paramId;
                    $a1[$i] = '([^/]+)';
                }
            }
            $this->regex = '#^('.$a[0].') (/'.join('/', $a1).'/?)$#'; // #...#i option?
        }
    }

    public function match($route)
    {
        if (!preg_match($this->regex, $route, $match)) {
            return false;
        }
        if (!$this->validObserver()) {
            return false;
        }
        if ($this->action_idx) {
            $this->action_name = $match[$this->action_idx];
        }
        if ($this->route_name[0]==='^') {
            $this->params_values = $match;
        } elseif ($this->params) {
            $this->params_values = array();
            foreach ($this->params as $i=>$p) {
                $this->params_values[$p] = $match[$i];
            }
        }
        return true;
    }

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
    * Add an observer to the route node
    *
    * @param mixed $callback
    * @param array $args
    * @param boolean $multiple whether to allow multiple observers for the route
    */
    public function observe($callback, $args=null, $multiple=true)
    {
        $observer = new BRouteObserver(array(
            'callback' => $callback,
            'args' => $args,
            'route_node' => $this,
        ));
        if ($multiple || empty($this->_observers)) {
            $this->_observers[] = $observer;
        } else {
            //$this->_observers = BUtil::arrayMerge($this->_observers[0], $observer);
            $this->_observers = array($observer);
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
    * Try to dispatch valid observers
    *
    * Will try to call observers in this node in order of save
    *
    * @return array|boolean forward info
    */
    public function dispatch()
    {
        $attempts = 0;
        $observer = $this->validObserver();
        while ((++$attempts<100) && $observer) {
            $forward = $observer->dispatch();
            if (is_array($forward)) {
                return $forward;
            } elseif ($forward===true) {
                $observer->skip = true;
                $observer = $this->validObserver();
            } else {
                return false;
            }
        }
        if ($attempts>=100) {
            BDebug::error(BLocale::_('BRouteNode: Reached 100 route iterations: %s', print_r($observer,1)));
        }
        return true;
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
    * Callback arguments
    *
    * @var array
    */
    public $args;

    /**
    * Whether to skip the route when trying another
    *
    * @var boolean
    */
    public $skip;

    /**
    * Parent route node
    *
    * @var BRouteNode
    */
    public $route_node;

    public function __construct($args)
    {
        foreach ($args as $k=>$v) {
            $this->$k = $v;
        }
    }

    /**
    * Dispatch route node callback
    *
    * @return forward info
    */
    public function dispatch()
    {
        BModuleRegistry::i()->currentModule(!empty($this->args['module_name']) ? $this->args['module_name'] : null);

        $node = $this->route_node;
        BRequest::i()->initParams((array)$node->params_values);
        if (is_string($this->callback) && $node->action_name) {
            $this->callback .= '.'.$node->action_name;
        }
        if (is_callable($this->callback)) {
            return call_user_func($this->callback, $this->args);
        }
        if (is_string($this->callback)) {
            foreach (array('.', '->') as $sep) {
                $r = explode($sep, $this->callback);
                if (sizeof($r)==2) {
                    $this->callback = $r;
                    break;
                }
            }
        }
        $controllerName = $this->callback[0];
        $node->controller_name = $controllerName;
        $actionName = $this->callback[1];
        /** @var BActionController */
        $controller = BClassRegistry::i()->instance($controllerName, array(), true);
        return $controller->dispatch($actionName, $this->args);
    }

    public function __destruct()
    {
        unset($this->route_node, $this->callback, $this->args, $this->params);
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

    public function __construct()
    {

    }

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
    * @return forward information
    */
    public function dispatch($actionName, $args=array())
    {
        $this->_action = $actionName;
        $this->_forward = null;

        if (!$this->beforeDispatch($args)) {
            return true;
        } elseif ($this->_forward) {
            return $this->_forward;
        }

        $authenticated = $this->authenticate($args);
        if (!$authenticated && $actionName!=='unauthenticated') {
            $this->forward('unauthenticated');
            return $this->_forward;
        }

        if ($authenticated && !$this->authorize($args) && $actionName!=='unauthorized') {
            $this->forward('unauthorized');
            return $this->_forward;
        }

        $this->tryDispatch($actionName, $args);

        if (!$this->_forward) {
            $this->afterDispatch($args);
        }
        return $this->_forward;
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
            if (method_exists($this, $tmpMethod)) {
                $actionMethod = $tmpMethod;
            }
        }
        if (!method_exists($this, $actionMethod)) {
            $this->forward(true);
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
    * Forward to another action or retrieve current forward
    *
    * @param string $actionName
    * @param string $controllerName
    * @param array $params
    * @return string|null|BActionController
    */
    public function forward($actionName=null, $controllerName=null, array $params=array())
    {
        if (true===$actionName) {
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
