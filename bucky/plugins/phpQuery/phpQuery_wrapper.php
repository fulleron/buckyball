<?php


/**
* Wrapper for phpQuery API
*
* @see http://code.google.com/p/phpquery/
*/
class BphpQuery extends BClass
{
    protected $_doc;
    protected $_html;

    static public function init()
    {
        BEventRegistry::i()->observe('layout.render.after', array(__CLASS__, 'observer_layout_render_after'));
    }

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BphpQuery
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function observer_layout_render_after($args)
    {
        $this->_html = $args['output'];# : '<!DOCTYPE html><html><head></head><body></body></html>';

        BEventRegistry::i()->dispatch('phpQuery.render', $args);

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