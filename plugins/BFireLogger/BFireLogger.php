<?php

class BFireLogger extends BClass
{
    static protected $_channels = array();

    public static function bootstrap()
    {
        //BPubSub::i()->on('BResponse::output.before', 'FireLogger::handler');
        self::channel('buckyball')->log('Start');
    }

    public static function channel($name)
    {
        if (empty(self::$_channels)) {
            //define('FIRELOGGER_NO_VERSION_CHECK', 1);
            #$_SERVER['HTTP_X_FIRELOGGER'] = null; // the only way to set shutdown handler in custom place
            #define('FIRELOGGER_NO_PASSWORD_CHECK', 1);
            #define('FIRELOGGER_NO_OUTPUT_HANDLER', 1);
            //define('FIRELOGGER_NO_DEFAULT_LOGGER', 1);
            define('FIRELOGGER_NO_CONFLICT', 1);

            include_once "firelogger.php";
            #FireLogger::$enabled = true;


        }
        if (empty(self::$_channels[$name])) {
            self::$_channels[$name] = new FireLogger($name);
        }
        return self::$_channels[$name];
    }
}