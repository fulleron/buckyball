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
* Wrapper for Ganon API
*
* @see http://code.google.com/p/ganon/
*/
class BGanon extends BClass
{
    protected $_doc;
    protected $_html;

    static public function bootstrap()
    {
        BPubSub::i()->on('BLayout::render.after', 'BGanon.onLayoutRenderAfter');
    }

    /**
     * Shortcut to help with IDE autocompletion
     *
     * @param bool  $new
     * @param array $args
     * @return BGanon
     */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function onLayoutRenderAfter($args)
    {
        $this->_html = $args['output'];# : '<!DOCTYPE html><html><head></head><body></body></html>';
        //$args['doc'] = $this->doc();
        $args['current_path'] = BRequest::i()->rawPath();
        BPubSub::i()->fire('BGanon::render', $args);
        BPubSub::i()->fire('BGanon::render.'.$args['current_path'], $args);

        if ($this->_doc) {
            $args['output'] = (string)$this->_doc;
        }
    }

    public function ready($callback, $args=array())
    {
        if (empty($args['on_path'])) {
            BPubSub::i()->on('BGanon::render', $callback, $args);
        } else {
            foreach ((array)$args['on_path'] as $path) {
                BPubSub::i()->on('BGanon::render.'.$path, $callback, $args);
            }
        }
        return $this;
    }

    public function doc($html=null)
    {
        if (is_null($this->_doc)) {
            require_once "ganon.php";
        }
        if (!is_null($html) || is_null($this->_doc)) {
            if (is_null($html) && is_null($this->_doc)) {
                $html = $this->_html;
                unset($this->_html);
            } elseif (!is_null($html) && !is_null($this->_doc)) {
                unset($this->_doc);
            }
            $a = new HTML_Parser_HTML5($html);
            $this->_doc = $a->root;
        }
        return $this->_doc;
    }

    public function file($filename)
    {
        return $this->doc(file_get_contents($filename));
    }

    public function find($selector, $idx=null)
    {
        $root = $this->doc();
        return $root($selector, $idx);
    }
}