<?php

require_once 'lib/PHPTAL.php';

class BPHPTAL extends BClass
{
    protected static $_singletons = array();

    protected static $_nocache = false;

    protected static $_outputMode = PHPTAL::HTML5;

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
        $tal->setOutputMode(static::$_outputMode);

        $tal->addPreFilter(static::singleton('BPHPTAL_PreFilter'));
        $tal->setPostFilter(static::singleton('BPHPTAL_PostFilter'));
        #$tal->setTranslator(static::singleton('BPHPTAL_TranslationService'));
        $tal->setTranslator(new BPHPTAL_TranslationService);

        if (static::$_nocache) {
            $tal->setForceReparse(true);
        }
        BPubSub::i()->fire(__METHOD__, array('tal'=>$tal, 'tpl'=>$tpl));
        return $tal;
    }
}

class BPHPTAL_PreFilter extends PHPTAL_PreFilter
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
        $this->_currentDomain = $domain;
    }

    public function setVar($key, $value)
    {
        $this->_context[$key] = $value;
    }

    public function translate($key, $htmlescape=true)
    {
        $result = BLocale::_($key, $this->_context, $this->_currentDomain);
        if ($htmlescape) {
            $result = htmlspecialchars($result);
        }
        return $result;
    }

    public function setEncoding($encoding)
    {

    }
}