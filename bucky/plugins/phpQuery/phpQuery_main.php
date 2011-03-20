<?php

require_once "phpQuery/phpQuery.php";

/**
* Wrapper for phpQuery API
*
* @see http://code.google.com/p/phpquery/
*/
class BphpQuery
{
    protected $_doc;

    static public function init()
    {
        BApp::service('phpQuery', 'BphpQuery');
    }

    static public function service()
    {
        return BApp::service('phpQuery');
    }

    public function doc($html=null)
    {
        if (!is_null($html) || is_null($this->_doc)) {
            if (is_null($html) && is_null($this->_doc)) {
                $html = '<!DOCTYPE html><html><head></head><body></body></html>';
            } elseif (!is_null($html) && !is_null($this->_doc)) {
                unset($this->_doc);
            }
            $this->_doc = phpQuery::newDocument($html);
            phpQuery::selectDocument($this->_doc);
        }
        return $this->_doc;
    }

    public function file($filename)
    {
        return $this->doc(file_get_contents($filename));
    }

    public function find($selector)
    {
        if (!$this->_doc) {
            $this->doc();
        }
        return pq($selector);
    }
}