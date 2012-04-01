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
*/

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
        BPubSub::i()->on('layout.render.after', array(__CLASS__, 'observer_layout_render_after'));
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

    public function ready($callback, $args=array())
    {
        BPubSub::i()->on('BphpQuery.render', $callback, $args);
        return $this;
    }

    public function observer_layout_render_after($args)
    {
        $this->_html = $args['output'];# : '<!DOCTYPE html><html><head></head><body></body></html>';
        $args['doc'] = $this->doc();

        BPubSub::i()->fire('BphpQuery.render', $args);

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