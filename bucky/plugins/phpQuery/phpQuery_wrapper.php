<?php


/**
* Wrapper for phpQuery API
*
* @see http://code.google.com/p/phpquery/
*/
class BphpQuery
{
    protected $_doc;
    protected $_html;

    static public function init()
    {
        BApp::service('phpQuery', 'BphpQuery');
        BEventRegistry::service()->observe('layout.render.after', array(__CLASS__, 'event_layout_render_after'));
    }

    static public function service()
    {
        return BApp::service('phpQuery');
    }

    public function event_layout_render_after($args)
    {
        $this->_html = $args['output'];# : '<!DOCTYPE html><html><head></head><body></body></html>';

        BEventRegistry::service()->dispatch('phpQuery.render', $args);

        if ($this->_doc) {
            $args['output'] = (string)$this->_doc;
        }
    }

    public function doc($html=null)
    {
        if (is_null($this->_doc)) {
            require_once "phpQuery/phpQuery.php";
        }
        if (!is_null($html) || is_null($this->_doc)) {
            if (is_null($html) && is_null($this->_doc)) {
                $html = $this->_html;
                unset($this->_html);
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