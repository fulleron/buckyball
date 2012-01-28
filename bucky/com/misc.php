<?php

/**
* Utility class to parse and construct strings and data structures
*/
class BUtil
{
    /**
    * IV for mcrypt operations
    *
    * @var string
    */
    protected static $_mcryptIV;

    /**
    * Encryption key from configuration (encrypt/key)
    *
    * @var string
    */
    protected static $_mcryptKey;

    /**
    * Default hash algorithm
    *
    * @var string default sha512 for strength and slowness
    */
    protected static $_hashAlgo = 'sha256';

    /**
    * Default number of hash iterations
    *
    * @var int
    */
    protected static $_hashIter = 3;

    /**
    * Default full hash string separator
    *
    * @var string
    */
    protected static $_hashSep = '$';

    /**
    * Convert any data to JSON string
    *
    * @param mixed $data
    * @return string
    */
    public static function toJson($data)
    {
        return json_encode($data);
    }

    /**
    * Parse JSON into PHP data
    *
    * @param string $json
    * @param bool $asObject if false will attempt to convert to array,
    *                       otherwise standard combination of objects and arrays
    */
    public static function fromJson($json, $asObject=false)
    {
        $obj = json_decode($json);
        return $asObject ? $obj : static::objectToArray($obj);
    }

    /**
    * Convert data to JavaScript string
    *
    * Notable difference from toJson: allows raw function callbacks
    *
    * @param mixed $val
    * @return string
    */
    public static function toJavaScript($val)
    {
        if (is_null($val)) {
            return 'null';
        } elseif (is_bool($val)) {
            return $val ? 'true' : 'false';
        } elseif (is_string($val)) {
            if (preg_match('#^\s*function\s*\(#', $val)) {
                return $val;
            } else {
                return "'".addslashes($val)."'";
            }
        } elseif (is_int($val) || is_float($val)) {
            return $val;
        } elseif (($isObj = is_object($val)) || is_array($val)) {
            $out = array();
            if (!empty($val) && ($isObj || array_keys($val) !== range(0, count($val)-1))) { // assoc?
                foreach ($val as $k=>$v) {
                    $out[] = "'".addslashes($k)."':".static::toJavaScript($v);
                }
                return '{'.join(',', $out).'}';
            } else {
                foreach ($val as $k=>$v) {
                    $out[] = static::toJavaScript($v);
                }
                return '['.join(',', $out).']';
            }
        }
        return '"UNSUPPORTED TYPE"';
    }

    /**
    * Convert object to array recursively
    *
    * @param object $d
    * @return array
    */
    public static function objectToArray($d)
    {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }
        if (is_array($d)) {
            return array_map('BUtil::objectToArray', $d);
        }
        return $d;
    }

    /**
    * Convert array to object
    *
    * @param mixed $d
    * @return object
    */
    public static function arrayToObject($d)
    {
        if (is_array($d)) {
            return (object) array_map('BUtil::objectToArray', $d);
        }
        return $d;
    }

    /**
     * version of sprintf for cases where named arguments are desired (php syntax)
     *
     * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
     *
     * with sprintfn: sprintfn('second: %second$s ; first: %first$s', array(
     *  'first' => '1st',
     *  'second'=> '2nd'
     * ));
     *
     * @see http://www.php.net/manual/en/function.sprintf.php#94608
     * @param string $format sprintf format string, with any number of named arguments
     * @param array $args array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    public static function sprintfn($format, $args = array())
    {
        $args = (array)$args;

        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)([a-zA-Z_]\w*)(?=\$)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (! array_key_exists($arg_key, $arg_nums)) {
                user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
                return false;
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key], $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        return vsprintf($format, array_values($args));
    }

    /**
    * Inject vars into string template
    *
    * Ex: echo BUtil::injectVars('One :two :three', array('two'=>2, 'three'=>3))
    * Result: "One 2 3"
    *
    * @param string $str
    * @param array $vars
    * @return string
    */
    public static function injectVars($str, $vars)
    {
        $from = array(); $to = array();
        foreach ($vars as $k=>$v) {
            $from[] = ':'.$k;
            $to[] = $v;
        }
        return str_replace($from, $to, $str);
    }

    /**
     * Merges any number of arrays / parameters recursively, replacing
     * entries with string keys with values from latter arrays.
     * If the entry or the next value to be assigned is an array, then it
     * automagically treats both arguments as an array.
     * Numeric entries are appended, not replaced, but only if they are
     * unique
     *
     * calling: result = BUtil::arrayMerge(a1, a2, ... aN)
     *
     * @param array $array1
     * @param array $array2...
     * @return array
     **/
     public static function arrayMerge() {
         $arrays = func_get_args();
         $base = array_shift($arrays);
         if (!is_array($base))  {
             $base = empty($base) ? array() : array($base);
         }
         foreach ($arrays as $append) {
             if (!is_array($append)) {
                 $append = array($append);
             }
             foreach ($append as $key => $value) {
                 if (is_numeric($key)) {
                     if (!in_array($value, $base)) {
                        $base[] = $value;
                     }
                 } elseif (!array_key_exists($key, $base)) {
                     $base[$key] = $value;
                 } elseif (is_array($value) && is_array($base[$key])) {
                     $base[$key] = static::arrayMerge($base[$key], $append[$key]);
                 } else {
                     $base[$key] = $value;
                 }
             }
         }
         return $base;
     }

    /**
    * Compare 2 arrays recursively
    *
    * @param array $array1
    * @param array $array2
    */
    public static function arrayCompare(array $array1, array $array2)
    {
        $diff = false;
        // Left-to-right
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key,$array2)) {
                $diff[0][$key] = $value;
            } elseif (is_array($value)) {
                if (!is_array($array2[$key])) {
                    $diff[0][$key] = $value;
                    $diff[1][$key] = $array2[$key];
                } else {
                    $new = static::arrayCompare($value, $array2[$key]);
                    if ($new !== false) {
                        if (isset($new[0])) $diff[0][$key] = $new[0];
                        if (isset($new[1])) $diff[1][$key] = $new[1];
                    }
                }
            } elseif ($array2[$key] !== $value) {
                 $diff[0][$key] = $value;
                 $diff[1][$key] = $array2[$key];
            }
        }
        // Right-to-left
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key,$array1)) {
                $diff[1][$key] = $value;
            }
            // No direct comparsion because matching keys were compared in the
            // left-to-right loop earlier, recursively.
        }
        return $diff;
    }

    /**
    * Create IV for mcrypt operations
    *
    * @return string
    */
    static public function mcryptIV()
    {
        if (!self::$_mcryptIV) {
            self::$_mcryptIV = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_DEV_URANDOM);
        }
        return self::$_mcryptIV;
    }

    /**
    * Fetch default encryption key from config
    *
    * @return string
    */
    static public function mcryptKey()
    {
        if (is_null(static::$_mcryptKey)) {
            static::$_mcryptKey = BConfig::i()->get('encrypt/key');
        }
        return static::$_mcryptKey;

    }

    /**
    * Encrypt using AES256
    *
    * Requires PHP extension mcrypt
    *
    * @param string $value
    * @param string $key
    * @param boolean $base64
    * @return string
    */
    static public function encrypt($value, $key=null, $base64=true)
    {
        if (is_null($key)) $key = BUtil::mcryptKey();
        $enc = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $value, MCRYPT_MODE_ECB, self::mcryptIV());
        return $base64 ? trim(base64_encode($enc)) : $enc;
    }

    /**
    * Decrypt using AES256
    *
    * Requires PHP extension mcrypt
    *
    * @param string $value
    * @param string $key
    * @param boolean $base64
    * @return string
    */
    static public function decrypt($value, $key=null, $base64=true)
    {
        if (is_null($key)) $key = BUtil::mcryptKey();
        $enc = $base64 ? base64_decode($value) : $value;
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $enc, MCRYPT_MODE_ECB, self::mcryptIV()));
    }

    /**
    * Set or retrieve current hash algorithm
    *
    * @param string $algo
    */
    public static function hashAlgo($algo=null)
    {
        if (is_null($algo)) {
            return static::$_hashAlgo;
        }
        static::$_hashAlgo = $algo;
    }

    public static function hashIter($iter=null)
    {
        if (is_null($iter)) {
            return static::$_hashIter;
        }
        static::$iter = $iter;
    }

    /**
    * Generate random string
    *
    * @param int $strLen length of resulting string
    * @param string $chars allowed characters to be used
    */
    public static function randomString($strLen=8, $chars='abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ23456789')
    {
        $charsLen = strlen($chars)-1;
        $str = '';
        for ($i=0; $i<$strLen; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];
        }
        return $str;
    }

    /**
    * Generate random string based on pattern
    *
    * Syntax: {ULD10}-{U5}
    * - U: upper case letters
    * - L: lower case letters
    * - D: digits
    *
    * @param string $pattern
    * @return string
    */
    public static function randomPattern($pattern)
    {
        static $chars = array('L'=>'bcdfghjkmnpqrstvwxyz', 'U'=>'BCDFGHJKLMNPQRSTVWXYZ', 'D'=>'123456789');

        while (preg_match('#\{([ULD]+)([0-9]+)\}#i', $pattern, $m)) {
            for ($i=0, $c=''; $i<strlen($m[1]); $i++) $c .= $chars[$m[1][$i]];
            $pattern = preg_replace('#'.preg_quote($m[0]).'#', BUtil::randomString($m[2], $c), $pattern, 1);
        }
        return $pattern;
    }

    /**
    * Generate salted hash
    *
    * @param string $string original text
    * @param mixed $salt
    * @param mixed $algo
    * @return string
    */
    public static function saltedHash($string, $salt, $algo=null)
    {
        $algo = !is_null($algo) ? $algo : static::$_hashAlgo;
        return hash($algo, $salt.$string);
    }

    /**
    * Generate fully composed salted hash
    *
    * Ex: $sha512$2$<salt1>$<salt2>$<double-hashed-string-here>
    *
    * @param string $string
    * @param string $salt
    * @param string $algo
    * @param integer $iter
    */
    public static function fullSaltedHash($string, $salt=null, $algo=null, $iter=null)
    {
        $algo = !is_null($algo) ? $algo : static::$_hashAlgo;
        $iter = !is_null($iter) ? $iter : static::$_hashIter;
        $s = static::$_hashSep;
        $hash = $s.$algo.$s.$iter;
        for ($i=0; $i<$iter; $i++) {
            $salt1 = !is_null($salt) ? $salt : static::randomString();
            $hash .= $s.$salt1;
            $string = static::saltedHash($string, $salt1, $algo);
        }
        return $hash.$s.$string;
    }

    /**
    * Validate salted hash against original text
    *
    * @param string $string original text
    * @param string $storedHash fully composed salted hash
    */
    public static function validateSaltedHash($string, $storedHash)
    {
        $sep = $storedHash[0];
        $arr = explode($sep, $storedHash);
        array_shift($arr);
        $algo = array_shift($arr);
        $iter = array_shift($arr);
        $verifyHash = $string;
        for ($i=0; $i<$iter; $i++) {
            $salt = array_shift($arr);
            $verifyHash = static::saltedHash($verifyHash, $salt, $algo);
        }
        $knownHash = array_shift($arr);
        return $verifyHash===$knownHash;
    }

    /**
    * Return only specific fields from source array
    *
    * @param array $source
    * @param array|string $fields
    * @param boolean $inverse if true, will return anything NOT in $fields
    * @result array
    */
    public static function maskFields($source, $fields, $inverse=false)
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        $result = array();
        if (!$inverse) {
            foreach ($fields as $k) $result[$k] = isset($source[$k]) ? $source[$k] : null;
        } else {
            foreach ($source as $k=>$v) if (!in_array($k, $fields)) $result[$k] = $v;
        }
        return $result;
    }

    /**
    * Send simple POST request to external server and retrieve response
    *
    * @param string $url
    * @param array $data
    * @return string
    */
    public static function post($url, $data) {
        $request = http_build_query($data);
        $opts = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                    ."Content-Length: ".strlen($request)."\r\n",
                'content' => $request,
                'timeout' => 5,
            ),
        );
        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);
        parse_str($response, $result);
        return $result;
    }

    public static function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        if (strpos($path, '/..')!==false) {
            $a = explode('/', $path);
            $b = array();
            foreach ($a as $p) {
                if ($p==='..') array_pop($b); else $b[] = $p;
            }
            $path = join('/', $b);
        }
        return $path;
    }

    public static function globRecursive($pattern, $flags=0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::globRecursive($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }

    public static function isPathAbsolute($path)
    {
        return !empty($path) && $path[0]==='/' || !empty($path[2]) && $path[2]===':';
    }

    public static function ensureDir($dir)
    {
        if (!is_dir($dir) && !is_file($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

/**
* Basic user authentication and authorization class
*/
class BUser extends BModel
{
    protected static $_sessionUser;

    public function sessionUserId()
    {
        $userId = BSession::i()->data('user_id');
        return $userId ? $userId : false;
    }

    public function sessionUser($reset=false)
    {
        if (!static::isLoggedIn()) {
            return false;
        }
        $session = BSession::i();
        if ($reset || !static::$_sessionUser) {
            static::$_sessionUser = $this->load($this->sessionUserId());
        }
        return static::$_sessionUser;
    }

    public function isLoggedIn()
    {
        return $this->sessionUserId() ? true : false;
    }

    public function password($password)
    {
        $this->password_hash = BUtil::fullSaltedHash($password);
        return $this;
    }

    public function authenticate($username, $password)
    {
        if (empty(static::$_table)) {
            return $username=='admin' && $password=='admin';
        }
        $user = $this->load($username, 'email');
        if (!BUtil::validateSaltedHash($password, $user->password_hash)) {
            return false;
        }
        return $user;
    }

    public function authorize($role, $args=null)
    {
        if (is_null($args)) {
            // check authorization
            return true;
        }
        // set authorization
        return $this;
    }

    public function login($username, $password)
    {
        if (empty(static::$_table)) {
            return $this->altAuthenticate($username, $password);
        }

        $user = $this->authenticate($username, $password);
        if (!$user) {
            return false;
        }

        BSession::i()->data('user_id', $user->id);

        if ($user->locale) {
            setlocale(LC_ALL, $user->locale);
        }
        if ($user->timezone) {
            date_default_timezone_set($user->timezone);
        }
        BPubSub::i()->fire('BUser::login.after', array('user'=>$user));
        return true;
    }

    public function logout()
    {
        BSession::i()->data('user_id', false);
        static::$_sessionUser = null;
        BPubSub::i()->fire('BUser::login.after');
        return $this;
    }
}

class BErrorException extends Exception
{
    public $context;
    public $stackPop;

    public function __construct($code, $message, $file, $line, $context=null, $stackPop=1)
    {
        parent::__construct($message, $code);
        $this->file = $file;
        $this->line = $line;
        $this->context = $context;
        $this->stackPop = $stackPop;
    }
}

/**
* Facility to log errors and events for development and debugging
*
* @todo move all debugging into separate plugin, and override core classes
*/
class BDebug extends BClass
{
    const EMERGENCY = 0,
        ALERT       = 1,
        CRITICAL    = 2,
        ERROR       = 3,
        WARNING     = 4,
        NOTICE      = 5,
        INFO        = 6,
        DEBUG       = 7;

    static protected $_levelLabels = array(
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT     => 'ALERT',
        self::CRITICAL  => 'CRITICAL',
        self::ERROR     => 'ERROR',
        self::WARNING   => 'WARNING',
        self::NOTICE    => 'NOTICE',
        self::INFO      => 'INFO',
        self::DEBUG     => 'DEBUG',
    );

    const MEMORY  = 0,
        FILE      = 1,
        SYSLOG    = 2,
        EMAIL     = 4,
        OUTPUT    = 8,
        STOP      = 4096;

    const MODE_DEBUG     = 'debug',
        MODE_DEVELOPMENT = 'development',
        MODE_STAGING     = 'staging',
        MODE_PRODUCTION  = 'production';


    /**
    * Trigger levels for different actions
    *
    * - memory: remember in immedicate script memory
    * - file: write to debug log file
    * - email: send email notification to admin
    * - output: display error in output
    * - exception: stop script execution by throwing exception
    *
    * Default are production values
    *
    * @var array
    */
    static protected $_level;

    static protected $_levelPreset = array(
        self::MODE_PRODUCTION => array(
            self::MEMORY    => false,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::ERROR,
            self::OUTPUT    => self::CRITICAL,
            self::STOP      => self::ALERT,
        ),
        self::MODE_STAGING => array(
            self::MEMORY    => false,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::ERROR,
            self::OUTPUT    => self::CRITICAL,
            self::STOP      => self::ALERT,
        ),
        self::MODE_DEVELOPMENT => array(
            self::MEMORY    => self::INFO,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::STOP      => self::ERROR,
        ),
        self::MODE_DEBUG => array(
            self::MEMORY    => self::DEBUG,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::STOP      => self::ERROR,
        ),
    );

    static protected $_modules = array();

    static protected $_mode = 'development';

    static protected $_startTime;
    static protected $_events = array();

    static protected $_logDir = null;
    static protected $_logFile = array(
        self::EMERGENCY => 'error.log',
        self::ALERT     => 'error.log',
        self::CRITICAL  => 'error.log',
        self::ERROR     => 'error.log',
        self::WARNING   => 'debug.log',
        self::NOTICE    => 'debug.log',
        self::INFO      => 'debug.log',
        self::DEBUG     => 'debug.log',
    );

    static protected $_adminEmail = null;

    static protected $_phpErrorMap = array(
        E_ERROR => self::ERROR,
        E_WARNING => self::WARNING,
        E_NOTICE => self::NOTICE,
        E_USER_ERROR => self::ERROR,
        E_USER_WARNING => self::WARNING,
        E_USER_NOTICE => self::NOTICE,
        E_STRICT => self::NOTICE,
        E_RECOVERABLE_ERROR => self::ERROR,
    );

    static protected $_verboseBacktrace = array();

    /**
    * Contructor, remember script start time for delta timestamps
    *
    * @return BDebug
    */
    public function __construct()
    {
        self::$_startTime = microtime(true);
        BPubSub::i()->on('BResponse::output.after', 'BDebug::afterOutput');
    }

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDebug
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public static function registerErrorHandlers()
    {
        set_error_handler('BDebug::errorHandler');
        set_exception_handler('BDebug::exceptionHandler');
        register_shutdown_function('BDebug::shutdownHandler');
    }

    public static function errorHandler($code, $message, $file, $line, $context=null)
    {
        static::trigger(self::$_phpErrorMap[$code], $message, 1);
        //throw new BErrorException(self::$_phpErrorMap[$code], $message, $file, $line, $context);
    }

    public static function exceptionHandler($e)
    {
        //static::trigger($e->getCode(), $e->getMessage(), $e->stackPop+1);
        static::trigger(self::ERROR, $e->getMessage());
    }

    public static function shutdownHandler()
    {
        $e = error_get_last();
        if ($e && ($e['type']===E_ERROR || $e['type']===E_PARSE || $e['type']===E_COMPILE_ERROR || $e['type']===E_COMPILE_WARNING)) {
            static::trigger(self::CRITICAL, $e['message'], 1);
        }
    }

    public static function level($type, $level=null)
    {
        if (!isset(static::$_level[$type])) {
            throw new BException('Invalid debug level type');
        }
        if (is_null($level)) {
            if (is_null(static::$_level)) {
                static::$_level = static::$_levelPreset[self::$_mode];
            }
            return static::$_level[$type];
        }
        static::$_level[$type] = $level;
    }

    public static function logDir($dir)
    {
        self::$_logDir = $dir;
    }

    public static function log($msg, $file='debug.log')
    {
        error_log($msg."\n", 3, self::$_logDir.'/'.$file);
    }

    public static function adminEmail($email)
    {
        self::$_adminEmail = $email;
    }

    public static function mode($mode=null, $setLevels=true)
    {
        if (is_null($mode)) {
            return self::$_mode;
        }
        self::$_mode = $mode;
        if ($setLevels) {
            self::$_level = self::$_levelPreset[$mode];
        }
    }

    public static function backtraceOn($msg)
    {
        foreach ((array)$msg as $m) {
            static::$_verboseBacktrace[$m] = true;
        }
    }

    public static function trigger($level, $msg, $stackPop=0)
    {
        $e = is_scalar($msg) ? array('msg'=>$msg) : $msg;

        //$stackPop++;
        $bt = debug_backtrace(true);
        $e['level'] = self::$_levelLabels[$level];
        if (isset($bt[$stackPop]['file'])) $e['file'] = $bt[$stackPop]['file'];
        if (isset($bt[$stackPop]['line'])) $e['line'] = $bt[$stackPop]['line'];
        //$o = $bt[$stackPop]['object'];
        //$e['object'] = is_object($o) ? get_class($o) : $o;

        $e['ts'] = BDb::now();
        $e['t'] = microtime(true)-self::$_startTime;
        $e['d'] = null;
        $e['c'] = null;
        $e['mem'] = memory_get_usage();

        if (!empty(static::$_verboseBacktrace[$e['msg']])) {
            foreach ($bt as $t) {
                $e['msg'] .= "\n".$t['file'].':'.$t['line'];
            }
        }

        $message = "{$e['level']}: {$e['msg']}".(isset($e['file'])?" ({$e['file']}:{$e['line']})":'');

        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $e['module'] = $moduleName;
        }

        if (is_null(static::$_level)) {
            static::$_level = static::$_levelPreset[self::$_mode];
        }

        $l = self::$_level[self::MEMORY];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            self::$_events[] = $e;
            $id = sizeof(self::$_events)-1;
        }

        $l = self::$_level[self::SYSLOG];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            error_log($message, 0, self::$_logDir);
        }

        if (!is_null(self::$_logDir)) { // require explicit enable of file log
            $l = self::$_level[self::FILE];
            if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
                /*
                if (is_null(self::$_logDir)) {
                    self::$_logDir = sys_get_temp_dir();
                }
                */
                $file = self::$_logDir.'/'.self::$_logFile[$level];
                if (is_writable(self::$_logDir) || is_writable($file)) {
                    error_log("{$e['ts']} {$message}\n", 3, $file);
                } else {
                    //TODO: anything needs to be done here?
                }
            }
        }

        if (!is_null(self::$_adminEmail)) { // require explicit enable of email
            $l = self::$_level[self::EMAIL];
            if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
                error_log(print_r($e, 1), 1, self::$_adminEmail);
            }
        }

        $l = self::$_level[self::OUTPUT];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            echo '<div style="text-align:left; border:solid 1px red; font-family:monospace;">';
            ob_start();
            echo $message."\n";
            debug_print_backtrace();
            echo nl2br(htmlspecialchars(ob_get_clean()));
            echo '</div>';
        }

        $l = self::$_level[self::STOP];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            static::dumpLog();
            die;
        }

        return isset($id) ? $id : null;
    }

    public static function alert($msg, $stackPop=0)
    {
        return self::trigger(self::ALERT, $msg, $stackPop+1);
    }

    public static function critical($msg, $stackPop=0)
    {
        return self::trigger(self::CRITICAL, $msg, $stackPop+1);
    }

    public static function error($msg, $stackPop=0)
    {
        return self::trigger(self::ERROR, $msg, $stackPop+1);
    }

    public static function warning($msg, $stackPop=0)
    {
        return self::trigger(self::WARNING, $msg, $stackPop+1);
    }

    public static function notice($msg, $stackPop=0)
    {
        return self::trigger(self::NOTICE, $msg, $stackPop+1);
    }

    public static function info($msg, $stackPop=0)
    {
        return self::trigger(self::INFO, $msg, $stackPop+1);
    }

    public static function debug($msg, $stackPop=0)
    {
        if ('debug'!==self::$_mode) return; // to speed things up
        return self::trigger(self::DEBUG, $msg, $stackPop+1);
    }

    public static function profile($id)
    {
        if ($id && !empty(self::$_events[$id])) {
            self::$_events[$id]['d'] = microtime(true)-self::$_startTime-self::$_events[$id]['t'];
            self::$_events[$id]['c']++;
        }
    }

    public static function is($modes)
    {
        if (is_string($modes)) $modes = explode(',', $modes);
        return in_array(self::$_mode, $modes);
    }

    public static function dumpLog($return=false)
    {
        if ((self::$_mode!==self::MODE_DEBUG && self::$_mode!==self::MODE_DEVELOPMENT)
            || BResponse::i()->contentType()!=='text/html'
            || BRequest::i()->xhr()
        ) {
            return;
        }
        ob_start();
?><style>
#buckyball-debug-trigger { position:fixed; top:0; right:0; font:normal 10px Verdana; cursor:pointer; z-index:10001; background:#ffc; }
#buckyball-debug-console { position:fixed; overflow:auto; top:10px; left:10px; bottom:10px; right:10px; border:solid 2px #f00; padding:4px; text-align:left; opacity:1; background:#FFC; font:normal 10px Verdana; z-index:10000; }
#buckyball-debug-console table { border-collapse: collapse; }
#buckyball-debug-console th, #buckyball-debug-console td { font:normal 10px Verdana; border: solid 1px #ccc; padding:2px 5px;}
#buckyball-debug-console th { font-weight:bold; }
</style>
<div id="buckyball-debug-trigger" onclick="var el=document.getElementById('buckyball-debug-console');el.style.display=el.style.display?'':'none'">[DBG]</div><div id="buckyball-debug-console" style="display:none"><?php
        echo "DELTA: ".BDebug::i()->delta().', PEAK: '.memory_get_peak_usage(true).', EXIT: '.memory_get_usage(true);
        echo "<pre>";
        print_r(BORM::get_query_log());
        //BPubSub::i()->debug();
        echo "</pre>";
        //print_r(self::$_events);
?><table cellspacing="0"><tr><th>Message</th><th>Rel.Time</th><th>Profile</th><th>Memory</th><th>Level</th><th>Relevant Location</th><th>Module</th></tr><?php
        foreach (self::$_events as $e) {
            if (empty($e['file'])) { $e['file'] = ''; $e['line'] = ''; }
            $profile = $e['d'] ? number_format($e['d'], 6).($e['c']>1 ? ' ('.$e['c'].')' : '') : '';
            echo "<tr><td><xmp style='margin:0'>".$e['msg']."</xmp></td><td>".number_format($e['t'], 6)."</td><td>".$profile."</td><td>".number_format($e['mem'], 0)."</td><td>{$e['level']}</td><td>{$e['file']}:{$e['line']}</td><td>".(!empty($e['module'])?$e['module']:'')."</td></tr>";
        }
?></table></div><?php
        $html = ob_get_clean();
        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }

    /**
    * Delta time from start
    *
    * @return float
    */
    public static function delta()
    {
        return microtime(true)-self::$_startTime;
    }

    public static function dump($var)
    {
        if (is_array($var) && current($var) instanceof Model) {
            foreach ($var as $k=>$v) {
                echo '<hr>'.$k.':';
                static::dump($v);
            }
        } elseif ($var instanceof Model) {
            echo '<pre>'; print_r($var->as_array()); echo '</pre>';
        } else {
            echo '<pre>'; print_r($var); echo '</pre>';
        }
    }

    public static function afterOutput($args)
    {
        static::dumpLog();
        //$args['content'] = str_replace('</body>', static::dumpLog(true).'</body>', $args['content']);
    }
}

/**
* Stub for cache class
*/
class BCache extends BClass
{
    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BCache
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Stub
    *
    */
    public function init()
    {

    }
}

/**
* Facility to handle l10n and i18n
*/
class BLocale extends BClass
{
    static protected $_domainPrefix = 'fulleron/';
    static protected $_domainStack = array();
    /**
    * Default timezone
    *
    * @var string
    */
    protected $_defaultTz = 'America/Los_Angeles';

    /**
    * Default locale
    *
    * @var string
    */
    protected $_defaultLocale = 'en_US';

    /**
    * Cache for DateTimeZone objects
    *
    * @var DateTimeZone
    */
    protected $_tzCache = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BLocale
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Constructor, set default timezone and locale
    *
    */
    public function __construct()
    {
        date_default_timezone_set($this->_defaultTz);
        setlocale(LC_ALL, $this->_defaultLocale);
        $this->_tzCache['GMT'] = new DateTimeZone('GMT');
    }

    public function language($lang=null)
    {
        if (is_null($lang)) {
            return $this->_curLang;
        }
        putenv('LANGUAGE='.$lang);
        putenv('LANG='.$lang);
        setlocale(LC_ALL, $lang.'.utf8', $lang.'.UTF8', $lang.'.utf-8', $lang.'.UTF-8');
        return $this;
    }

    public function module($domain, $file=null)
    {
        if (is_null($file)) {
            if (!is_null($domain)) {
                $domain = static::$_domainPrefix.$domain;
                $oldDomain = textdomain(null);
                if ($oldDomain) {
                    array_push(static::$_domainStack, $domain!==$oldDomain ? $domain : false);
                }
            } else {
                $domain = array_pop(static::$_domainStack);
            }
            if ($domain) {
                textdomain($domain);
            }
        } else {
            $domain = static::$_domainPrefix.$domain;
            bindtextdomain($domain, $file);
            bind_textdomain_codeset($domain, "UTF-8");
        }
        return $this;
    }

    /**
    * Translate a string and inject optionally named arguments
    *
    * @param string $string
    * @param array $args
    * @return string|false
    */
    public function translate($string, $args=array(), $domain=null)
    {
        if (!is_null($domain)) {
            $string = dgettext($domain, $string);
        } else {
            $string = gettext($string);
        }
        return BUtil::sprintfn($string, $args);
    }

    /**
    * Get server timezone
    *
    * @return string
    */
    public function serverTz()
    {
        return date('e'); // Examples: UTC, GMT, Atlantic/Azores
    }

    /**
    * Get timezone offset in seconds
    *
    * @param stirng|null $tz If null, return server timezone offset
    * @return int
    */
    public function tzOffset($tz=null)
    {
        if (is_null($tz)) { // Server timezone
            return date('O') * 36; //  x/100*60*60; // Seconds from GMT
        }
        if (empty($this->_tzCache[$tz])) {
            $this->_tzCache[$tz] = new DateTimeZone($tz);
        }
        return $this->_tzCache[$tz]->getOffset($this->_tzCache['GMT']);
    }

    /**
    * Convert local datetime to DB (GMT)
    *
    * @param string $value
    * @return string
    */
    public function datetimeLocalToDb($value)
    {
        if (is_array($value)) {
            return array_map(array($this, __METHOD__), $value);
        }
        if (!$value) return $value;
        return gmstrftime('%F %T', strtotime($value));
    }

    /**
    * Parse user formatted dates into db style within object or array
    *
    * @param array|object $request fields to be parsed
    * @param null|string|array $fields if null, all fields will be parsed, if string, will be split by comma
    * @return array|object clone of $request with parsed dates
    */
    public function parseRequestDates($request, $fields=null)
    {
        if (is_string($fields)) $fields = explode(',', $fields);
        $isObject = is_object($request);
        if ($isObject) $result = clone $request;
        foreach ($request as $k=>$v) {
            if (is_null($fields) || in_array($k, $fields)) {
                $r = $this->datetimeLocalToDb($v);
            } else {
                $r = $v;
            }
            if ($isObject) $result->$k = $r; else $result[$k] = $r;
        }
        return $result;
    }

    /**
    * Convert DB datetime (GMT) to local
    *
    * @param string $value
    * @param bool $full Full format or short
    * @return string
    */
    public function datetimeDbToLocal($value, $full=false)
    {
        return strftime($full ? '%c' : '%x', strtotime($value));
    }
}
