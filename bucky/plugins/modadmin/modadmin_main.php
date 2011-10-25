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

class BuckyModAdmin extends BClass
{
    public static function init()
    {
        BFrontController::i()
            ->route('GET /modadmin', array('BuckyModAdmin_Controller', 'index'))
        ;

        BLayout::i()->allViews('modadmin/view', 'modadmin.');
    }

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BuckyModAdmin
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }
}

class BuckyModAdmin_Controller extends BActionController
{
    public function action_index()
    {
        BLayout::i()->rootView('modadmin.main');
        BResponse::i()->render();
    }
}