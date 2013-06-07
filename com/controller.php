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

        if (!empty($_SERVER['ORIG_SCRIPT_NAME'])) {
            $_SERVER['ORIG_SCRIPT_NAME'] = str_replace('/index.php/index.php', '/index.php', $_SERVER['ORIG_SCRIPT_NAME']);
        }
        if (!empty($_SERVER['ORIG_SCRIPT_FILENAME'])) {
            $_SERVER['ORIG_SCRIPT_FILENAME'] = str_replace('/index.php/index.php', '/index.php', $_SERVER['ORIG_SCRIPT_FILENAME']);
        }
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
    * Origin host name from request headers
    *
    * @return string
    */
    public static function httpOrigin()
    {
        return !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
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
     * Retrive language based on HTTP_ACCEPT_LANGUAGE
     * @return string
     */
    static public function language()
    {
        $langs = array();

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

            if (count($lang_parse[1])) {
                // create a list like "en" => 0.8
                $langs = array_combine($lang_parse[1], $lang_parse[4]);

                // set default to 1 for any without q factor
                foreach ($langs as $lang => $val) {
                    if ($val === '') $langs[$lang] = 1;
                }

                // sort list based on value
                arsort($langs, SORT_NUMERIC);
            }
        }

        //if no language detected return false
        if (empty($langs)) {
            return false;
        }

        list($toplang) = each($langs);
        //return en, de, es, it.... first two characters of language code
        return substr($toplang, 0, 2);
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
        return !empty($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) :
            (!empty($_SERVER['ORIG_SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['ORIG_SCRIPT_NAME']) : null);
    }

    /**
    * Entry point script file name
    *
    * @return string
    */
    public static function scriptFilename()
    {
        return !empty($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']) :
            (!empty($_SERVER['ORIG_SCRIPT_FILENAME']) ? str_replace('\\', '/', $_SERVER['ORIG_SCRIPT_FILENAME']) : null);
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
        $scriptName = static::scriptName();
        if (empty($scriptName)) {
            return null;
        }
        $root = rtrim(str_replace(array('//', '\\'), array('/', '/'), dirname($scriptName)), '/');
        if ($parentDepth) {
            $arr = explode('/', rtrim($root, '/'));
            $len = sizeof($arr)-$parentDepth;
            $root = $len>1 ? join('/', array_slice($arr, 0, $len)) : '/';
        }
        return $root ? $root : '/';
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
        $pathInfo = !empty($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] :
            (!empty($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : null);
        if (empty($pathInfo)) {
            return null;
        }
        $path = explode('/', ltrim($pathInfo, '/'));
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
     * PATH_TRANSLATED
     *
     */
    public static function pathTranslated()
    {
        return !empty($_SERVER['PATH_TRANSLATED']) ? $_SERVER['PATH_TRANSLATED'] :
            (!empty($_SERVER['ORIG_PATH_TRANSLATED']) ? $_SERVER['ORIG_PATH_TRANSLATED'] : '/');
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

    public static function headers($key=null)
    {
        $key = strtoupper($key);
        return is_null($key) ? $_SERVER : (isset($_SERVER[$key]) ? $_SERVER[$key] : null);
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
        if (is_string($source)) {
            if (!empty($_FILES[$source])) {
                $source = $_FILES[$source];
            } else {
                //TODO: missing enctype="multipart/form-data" ?
                throw new BException('Missing enctype="multipart/form-data"?');
            }
        }
        if (empty($source)) {
            return;
        }
        $result = array();
        if (is_array($source['error'])) {
            foreach ($source['error'] as $key=>$error) {
                if ($error==UPLOAD_ERR_OK) {
                    $tmpName = $source['tmp_name'][$key];
                    $name = $source['name'][$key];
                    $type = $source['type'][$key];
                    if (!is_null($typesRegex) && !preg_match('#'.$typesRegex.'#i', $type)) {
                        $result[$key] = array('error'=>'invalid_type', 'tp'=>1, 'type'=>$type, 'name'=>$name);
                        continue;
                    }
                    move_uploaded_file($tmpName, $targetDir.'/'.$name);
                    $result[$key] = array('name'=>$name, 'tp'=>2, 'type'=>$type, 'target'=>$targetDir.'/'.$name);
                } else {
                    $result[$key] = array('error'=>$error, 'tp'=>3);
                }
            }
        } else {
            $error = $source['error'];
            if ($error==UPLOAD_ERR_OK) {
                $tmpName = $source['tmp_name'];
                $name = $source['name'];
                $type = $source['type'];
                if (!is_null($typesRegex) && !preg_match('#'.$typesRegex.'#i', $type)) {
                    $result = array('error'=>'invalid_type', 'tp'=>4, 'type'=>$type, 'pattern'=>$typesRegex, 'source'=>$source, 'name'=>$name);
                } else {
                    move_uploaded_file($tmpName, $targetDir.'/'.$name);
                    $result = array('name'=>$name, 'type'=>$type, 'target'=>$targetDir.'/'.$name);
                }
            } else {
                $result = array('error'=>$error, 'tp'=>5);
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
    public static function csrf()
    {
        $c = BConfig::i();

        $m = $c->get('web/csrf_methods');
        $methods = $m ? (is_string($m) ? explode(',', $m) : $m) : array('POST','PUT','DELETE');
        $whitelist = $c->get('web/csrf_whitelist');

        if (is_array($methods) && !in_array(static::method(), $methods)) {
            return false; // not one of checked methods, pass
        }
        if ($whitelist) {
            $path = static::rawPath();
            foreach ((array)$whitelist as $pattern) {
                if (preg_match($pattern, $path)) {
                    return false;
                }
            }
        }
        if (!($ref = static::referrer())) {
            return true; // no referrer sent, high prob. csrf
        }
        $p = parse_url($ref);
        $p['path'] = preg_replace('#/+#', '/', $p['path']); // ignore duplicate slashes
        $webRoot = static::webRoot();
        if ($p['host']!==static::httpHost() || $webRoot && strpos($p['path'], $webRoot)!==0) {
            return true; // referrer host or doc root path do not match, high prob. csrf
        }
        return false; // not csrf
    }

    /**
    * Verify that HTTP_HOST or HTTP_ORIGIN
    *
    * @param string $method (HOST|ORIGIN|OR|AND)
    * @param string $explicitHost
    * @return boolean
    */
    public static function verifyOriginHostIp($method='OR', $host=null)
    {
        $ip = static::ip();
        if (!$host) {
            $host = static::httpHost();
        }
        $origin = static::httpOrigin();
        $hostIPs = gethostbynamel($host);
        $hostMatches = $host && $method!='ORIGIN' ? in_array($ip, (array)$hostIPs) : false;
        $originIPs = gethostbynamel($origin);
        $originMatches = $origin && $method!='HOST' ? in_array($ip, (array)$originIPs) : false;
        switch ($method) {
            case 'HOST': return $hostMatches;
            case 'ORIGIN': return $originMatches;
            case 'AND': return $hostMatches && $originMatches;
            case 'OR': return $hostMatches || $originMatches;
        }
        return false;
    }

    /**
    * Get current request URL
    *
    * @return string
    */
    public static function currentUrl()
    {
        $webroot = rtrim(static::webRoot(), '/');
        $url = static::scheme().'://'.static::httpHost();
        if (!BConfig::i()->get('web/hide_script_name')) {
            $url = rtrim($url, '/') . '/' . ltrim(str_replace('//', '/', static::scriptName()), '/');
        } else {
            $url = rtrim($url, '/') . '/' . ltrim(str_replace('//', '/', $webroot), '/');;
        }
        $url .= static::rawPath().(($q = static::rawGet()) ? '?'.$q : '');
        return $url;
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
            $modRewrite =  strtolower(getenv('HTTP_MOD_REWRITE'))=='on' ? true : false;
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
            $this->setContentType($type);
        }
        //BSession::i()->close();
        header('Content-Type: '.$this->_contentType.'; charset='.$this->_charset);
        if ($this->_contentType=='application/json') {
            if (!empty($this->_content)) {
                $this->_content = is_string($this->_content) ? $this->_content : BUtil::toJson($this->_content);
            }
        } elseif (is_null($this->_content)) {
            $this->_content = BLayout::i()->render();
        }
        BEvents::i()->fire('BResponse::output.before', array('content'=>&$this->_content));

        if ($this->_contentPrefix) {
            echo $this->_contentPrefix;
        }
        if ($this->_content) {
            echo $this->_content;
        }
        if ($this->_contentSuffix) {
            echo $this->_contentSuffix;
        }

        BEvents::i()->fire('BResponse::output.after', array('content'=>$this->_content));

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
        header("Location: {$url}", null, $status);
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

    /**
    * Enable CORS (Cross-Origin Resource Sharing)
    *
    * @param array $options
    * @return BResponse
    */
    public function cors($options=array())
    {
        if (empty($options['origin'])) {
            $options['origin'] = BRequest::i()->httpOrigin();
        }
        header('Access-Control-Allow-Origin: '.$options['origin']);
        if (!empty($options['methods'])) {
            header('Access-Control-Allow-Methods: '.$options['methods']);
        }
        if (!empty($options['credentials'])) {
            header('Access-Control-Allow-Credentials: true');
        }
        if (!empty($options['headers'])) {
            header('Access-Control-Allow-Headers: '.$options['headers']);
        }
        if (!empty($options['expose-headers'])) {
            header('Access-Control-Expose-Headers: '.$options['expose-headers']);
        }
        if (!empty($options['age'])) {
            header('Access-Control-Max-Age: '.$options['age']);
        }
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

    public static function startLongResponse()
    {
        // improve performance by not processing debug log
        if (BDebug::is('DEBUG')) {
            BDebug::mode('DEVELOPMENT');
        }
        // redundancy: avoid memory leakage from debug log
        BDebug::level(BDebug::MEMORY, false);
        // remove process timeout limitation
        set_time_limit(0);
        // output in real time
        @ob_end_flush();
        ob_implicit_flush();
        // enable garbage collection
        gc_enable();
        // remove session lock
        session_write_close();
        // bypass initial webservice buffering
        echo str_pad('', 2000, ' ');
        // continue in background if the browser request was interrupted
        //ignore_user_abort(true);
    }

    public function shutdown($lastMethod=null)
    {
        BEvents::i()->fire('BResponse::shutdown', array('last_method'=>$lastMethod));
        BSession::i()->close();
        exit;
    }
}

/**
* Front controller class to register and dispatch routes
*/
class BRouting extends BClass
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
    * @return BRouting
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
     * Declare route
     *
     * @param string $route
     *   - "{GET|POST|DELETE|PUT|HEAD} /part1/part2/:param1"
     *   - "/prefix/*anything"
     *   - "/prefix/.action" : $args=array('_methods'=>array('create'=>'POST', ...))
     * @param mixed  $callback PHP callback
     * @param array  $args Route arguments
     * @param string $name optional name for the route for URL templating
     * @param bool   $multiple
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
            return $this;
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

    /**
     * Shortcut to $this->route() for GET http verb
     * @param mixed  $route
     * @param mixed  $callback
     * @param array  $args
     * @param string $name
     * @param bool   $multiple
     * @return BFrontController
     */
    public function get($route, $callback = null, $args = null, $name = null, $multiple = true)
    {
        return $this->_route($route, 'get', $callback, $args, $name, $multiple);
    }

    /**
     * Shortcut to $this->route() for POST http verb
     * @param mixed  $route
     * @param mixed  $callback
     * @param array  $args
     * @param string $name
     * @param bool   $multiple
     * @return BFrontController
     */
    public function post($route, $callback = null, $args = null, $name = null, $multiple = true)
    {
        return $this->_route($route, 'post', $callback, $args, $name, $multiple);
    }

    /**
     * Shortcut to $this->route() for PUT http verb
     * @param mixed $route
     * @param null  $callback
     * @param null  $args
     * @param null  $name
     * @param bool  $multiple
     * @return $this|BFrontController
     */
    public function put($route, $callback = null, $args = null, $name = null, $multiple = true)
    {
        return $this->_route($route, 'put', $callback, $args, $name, $multiple);
    }

    /**
     * Shortcut to $this->route() for GET|POST|DELETE|PUT|HEAD http verbs
     * @param mixed $route
     * @param null  $callback
     * @param null  $args
     * @param null  $name
     * @param bool  $multiple
     * @return $this|BFrontController
     */
    public function any($route, $callback = null, $args = null, $name = null, $multiple = true)
    {
        return $this->_route($route, 'any', $callback, $args, $name, $multiple);
    }

    /**
     * Process shortcut methods
     * @param mixed  $route
     * @param string $verb
     * @param null   $callback
     * @param null   $args
     * @param null   $name
     * @param bool   $multiple
     * @return $this|BFrontController
     */
    protected function _route($route, $verb, $callback = null, $args = null, $name = null, $multiple = true)
    {
        BDebug::debug('ROUTE ' . $route . ':' . $verb . ': ' . print_r($args, 1));
        if (is_array($route)) {
            foreach ($route as $a) {
                if (is_null($callback)) {
                    $this->_route($a[0], $verb, $a[1], isset($a[2]) ? $a[2] : null, isset($a[3]) ? $a[3] : null);
                } else {
                    $this->any($a, $verb, $callback, $args);
                }
            }
            return $this;
        }
        $verb = strtoupper($verb);
        $isRegex = false;
        if ($route[0]==='^') {
            $isRegex = true;
            $route = substr($route, 1);
        }
        if ($verb==='GET' || $verb==='POST' || $verb==='PUT') {
            $route = $verb.' '.$route;
        } else {
            if ($isRegex) {
                $route = '(GET|POST|DELETE|PUT|HEAD) '.$route;
            } else {
                $route = 'GET|POST|DELETE|PUT|HEAD '.$route;
            }
        }
        if ($isRegex) {
            $route = '^'.$route;
        }

        return $this->route($route, $callback, $args, $name, $multiple);
    }

    public function findRoute($requestRoute=null)
    {
        if (is_null($requestRoute)) {
            $requestRoute = BRequest::i()->rawPath();
        }

        if (strpos($requestRoute, ' ')===false) {
            $requestRoute = BRequest::i()->method().' '.$requestRoute;
        }

        if (!empty($this->_routes[$requestRoute]) && $this->_routes[$requestRoute]->validObserver()) {
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
            $res = $a1<$b1 ? 1 : ($a1>$b1 ? -1 : 0);
            if ($res != 0) {
#echo ' ** ('.$a->route_name.'):('.$b->route_name.'): '.$res.' ** <br>';
                return $res;
            }
            $ap = (strpos($a->route_name, '/*') ? 10 : 0)+(strpos($a->route_name, '/.') ? 5 : 0)+(strpos($a->route_name, '/:') ? 1 : 0);
            $bp = (strpos($b->route_name, '/*') ? 10 : 0)+(strpos($b->route_name, '/.') ? 5 : 0)+(strpos($b->route_name, '/:') ? 1 : 0);
#echo $a->route_name.' ('.$ap.'), '.$b->route_name.'('.$bp.')<br>';
            return $ap === $bp ? 0 : ($ap < $bp ? -1 : 1 );
        });
#echo "<pre>"; print_r($this->_routes); echo "</pre>";
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
        $this->route($from, array($this, 'redirectCallback'), $args);
        return $this;
    }

    public function redirectCallback($args)
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
        BEvents::i()->fire(__METHOD__.'.before');

        $this->processRoutes();

        $attempts = 0;
        $forward = false; // null: no forward, false: try next route, array: forward without new route
#echo "<pre>"; print_r($this->_routes); exit;
        while (($attempts++<100) && (false===$forward || is_array($forward))) {
            $route = $this->findRoute($requestRoute);
#echo "<pre>"; print_r($route); echo "</pre>";
            if (!$route) {
                $route = $this->findRoute('_ /noroute');
            }
            $this->_currentRoute = $route;
            $forward = $route->dispatch();
#var_dump($forward); exit;
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
* Alias for BRouting for older implementations
*
* @deprecated by BRouting
*/
class BFrontController extends BRouting {}

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
    public $multi_method;

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
        if (sizeof($a)<2) {
            throw new BException('Invalid route format: '.$this->route_name);
        }
        $this->multi_method = strpos($a[0], '|') !== false;
        if ($a[1]==='/') {
            $this->regex = '#^('.$a[0].') (/)$#';
        } else {
            $a1 = explode('/', trim($a[1], '/'));
            $this->num_parts = sizeof($a1);
            $paramId = 2;
            foreach ($a1 as $i=>$k) {
                $k0 = $k[0];
                $part = '';
                if ($k0==='?') {
                    $k = substr($k, 1);
                    $k0 = $k[0];
                    $part = '?';
                }
                if ($k0===':') { // optional param
                    $this->params[++$paramId] = substr($k, 1);
                    $part .= '([^/]*)';
                } elseif ($k0==='!') { // required param
                    $this->params[++$paramId] = substr($k, 1);
                    $part .= '([^/]+)';
                } elseif ($k0==='*') { // param until end of url
                    $this->params[++$paramId] = substr($k, 1);
                    $part .= '(.*)';
                } elseif ($k0==='.') { // dynamic action
                    $this->params[++$paramId] = substr($k, 1);
                    $this->action_idx = $paramId;
                    $part .= '([a-zA-Z0-9_]*)';
                } else {
                    //$part .= preg_quote($a1[$i]);
                }
                if (''!==$part) {
                    $a1[$i] = $part;
                }
            }
            $this->regex = '#^('.$a[0].') (/'.join('/', $a1).'/?)$#'; // #...#i option?
#echo $this->regex.'<hr>';
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
            $this->action_name = !empty($match[$this->action_idx]) ? $match[$this->action_idx] : 'index';
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
            } elseif ($forward===false) {
                $observer->skip = true;
                $observer = $this->validObserver();
            } else {
                return null;
            }
        }
        if ($attempts>=100) {
            BDebug::error(BLocale::_('BRouteNode: Reached 100 route iterations: %s', print_r($observer,1)));
        }
        return false;
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
            // prevent envoking action_index__POST methods directly
            $actionNameArr = explode('__', $node->action_name, 2);
            $this->callback .= '.'.$actionNameArr[0];
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

        $actionName = '';
        $controllerName = '';
        if (is_array($this->callback)) {
            $controllerName = $this->callback[0];
            $node->controller_name = $controllerName;
            $actionName = $this->callback[1];
        }
#var_dump($controllerName, $actionName);
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
            return false;
        }
        if (!is_null($this->_forward)) {
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

        if (is_null($this->_forward)) {
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
        $reqMethod = BRequest::i()->method();
        if ($reqMethod !== 'GET') {
            $tmpMethod = $actionMethod.'__'.$reqMethod;
            if (method_exists($this, $tmpMethod)) {
                $actionMethod = $tmpMethod;
            } elseif (BRouting::i()->currentRoute()->multi_method) { 
                $this->forward(false); // If route has multiple methods, require method suffix
            }
        }
        //echo $actionMethod;exit;
        if (!method_exists($this, $actionMethod)) {
            $this->forward(false);
            return $this;
        }
        try {
            $this->$actionMethod($args);
        } catch (Exception $e) {
            //BDebug::exceptionHandler($e);
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
        if (false===$actionName) {
            $this->_forward = false;
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
        BEvents::i()->fire(__METHOD__);
        return true;
    }

    /**
    * Execute after dispatch
    *
    */
    public function afterDispatch()
    {
        BEvents::i()->fire(__METHOD__);
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

    public function getAction()
    {
        return $this->_action;
    }

    public function getController()
    {
        return self::origClass();
    }

    public function viewProxy($viewPrefix, $defaultView='index')
    {
        $viewPrefix = trim($viewPrefix, '/').'/';
        $page = BRequest::i()->params('view');
        if (!$page) {
            $page = $defaultView;
        }
        if (!$page || !($view = $this->view($viewPrefix.$page))) {
            $this->forward(false);
            return false;
        }
        BLayout::i()->applyLayout('view-proxy')->applyLayout($viewPrefix.$page);
        $view->render();
        $metaData = $view->param('meta_data');
        if ($metaData && ($head = $this->view('head'))) {
            foreach ($metaData as $k=>$v) {
                $k = strtolower($k);
                switch ($k) {
                case 'title':
                    $head->addTitle($v); break;
                case 'meta_title': case 'meta_description': case 'meta_keywords':
                    $head->meta(str_replace('meta_','',$k), $v); break;
                }
            }
        }
        BLayout::i()->hookView('main', $viewPrefix.$page);
        return $page;
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
