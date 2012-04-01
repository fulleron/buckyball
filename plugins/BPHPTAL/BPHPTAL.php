<?php

require_once 'lib/PHPTAL.php';

class BPHPTAL extends BClass
{
    protected static $_singletons = array();

    protected static $nocache = false;

    protected static $outputMode = PHPTAL::HTML5;

    public static function bootstrap()
    {

    }

    public static function singleton($class)
    {
        if (empty(static::$_singletons[$class])) {
            static::$_singletons[$class] = new $class;
        }
        return static::$_singletons[$class];
    }

    public static function factory($tpl=null)
    {
        $tal = new PHPTAL($tpl);
        $tal->setOutputMode(static::$outputMode);

        $tal->addPreFilter(static::singleton('BPHPTAL_PreFilter'));
        $tal->setPostFilter(static::singleton('BPHPTAL_PostFilter'));
        $tal->setTranslator(static::singleton('BPHPTAL_TranslationService'));

        if (static::$nocache) {
            $tal->setForceReparse(true);
        }
    }
}

class BPHPTAL_PreFilter implements PHPTAL_PreFilter
{
    public function filter($source)
    {
        BPubSub::i()->fire(__METHOD__, array('source'=>&$source));
        return $source;
    }

    public function filterDOM(PHPTAL_Dom_Element $element)
    {
        BPubSub::i()->fire(__METHOD__, array('element'=>$element));
    }
}

class BPHPTAL_PostFilter implements PHPTAL_Filter
{
    public function filter($html)
    {
        BPubSub::i()->fire(__METHOD__, array('html'=>&$html));
        return $html;
    }
}

class BPHPTAL_TranslationService implements PHPTAL_TranslationService
{
    protected $_currentLang = 'en_US';

    protected $_currentDomain;
    protected $_domains = array();

    private $_context = array();

    public function setLanguage()
    {
        $langs = func_get_args();
        foreach($langs as $lang){
            // if $lang known use it and stop the loop
            $this->_currentLang = $lang;
            break;
        }
        return $this->_currentLang;
    }

    public function useDomain($domain)
    {
        if (!array_key_exists($domain, $this->_domains)){
            $file = "domains/$this->_currentLang/$domain.php";
            $this->_domains[$domain] = include($file);
        }
        $this->_currentDomain = $this->_domains[$domain];
    }

    public function setVar($key, $value)
    {
        $this->_context[$key] = $value;
    }

    public function translate($key)
    {

        $value = $this->_currentDomain[$key];

        // interpolate ${myvar} using context associative array
        while (preg_match('/\${(.*?)\}/sm', $value, $m)){
            list($src,$var) = $m;
            if (!array_key_exists($var, $this->_context)){
                $err = sprintf('Interpolation error, var "%s" not set',
                               $var);
                throw new Exception($err);
            }
            $value = str_replace($src, $this->_context[$var], $value);
        }

        return $value;
    }

    public function setEncoding($encoding)
    {

    }
}