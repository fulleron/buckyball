<?php

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
        BEventRegistry::i()->dispatch('BUser::login.after', array('user'=>$user));
        return true;
    }

    public function logout()
    {
        BSession::i()->data('user_id', false);
        static::$_sessionUser = null;
        BEventRegistry::i()->dispatch('BUser::login.after');
        return $this;
    }
}

/**
* Facility to log errors and events for development and debugging
*
* @todo move all debugging into separate plugin, and override core classes
*/
class BDebug extends BClass
{
    const MODE_DEBUG = 'debug',
        MODE_DEVELOPMENT = 'development',
        MODE_STAGING = 'staging',
        MODE_PRODUCTION = 'production';

    protected $_startTime;
    protected $_events = array();
    protected $_mode = 'development';

    /**
    * Contructor, remember script start time for delta timestamps
    *
    * @return BDebug
    */
    public function __construct()
    {
        $this->_startTime = microtime(true);
        BEventRegistry::i()->observe('BResponse::output.after', array($this, 'afterOutput'));
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

    public function mode($mode=null)
    {
        if (is_null($mode)) {
            return $this->_mode;
        }
        $this->_mode = $mode;
        return $this;
    }

    public function is($modes)
    {
        if (is_string($modes)) $modes = explode(',', $modes);
        return in_array($this->_mode, $modes);
    }

    /**
    * Log event for future analysis
    *
    * @param array $event
    * @return BDebug
    */
    public function log($event)
    {
        if (!$this->is('debug,development')) {
            return $this;
        }
        $event['ts'] = microtime();
        if (($moduleName = BModuleRegistry::currentModuleName())) {
            $event['module'] = $moduleName;
        }
        /*
        if (class_exists('BFireLogger')) {
            BFireLogger::channel('buckyball')->log('debug', $event);
            return $this;
        }
        */
        $this->_events[] = $event;
        return $this;
    }

    public function dumpLog()
    {
        echo '<hr><pre style="border:solid 1px #f00; background:#fff; text-align:left; width:100%">';
        print_r(BORM::get_query_log());
        print_r($this->_events);
        echo "</pre>";
    }

    /**
    * Delta time from start
    *
    * @return float
    */
    public function delta()
    {
        return microtime(true)-$this->_startTime;
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

    public function afterOutput()
    {
        if ($this->_mode=='debug') {
            $this->dumpLog();
        }
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

    /**
    * Translate a string and inject optionally named arguments
    *
    * @param string $string
    * @param array $args
    * @return string|false
    */
    public function t($string, $args=array())
    {
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
        $result = $isObject ? clone $request : $request;
        foreach ($request as $k=>$v) {
            if (!is_null($fields) && !in_array($k, $fields)) continue;
            $r = $this->datetimeLocalToDb($v);
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

class BUnit extends BClass
{
    protected $_currentTest;

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BUnit
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function test($methods)
    {

    }
}
