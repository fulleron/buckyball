<?php

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
        BLayout::i()->mainView('modadmin.main');
        BResponse::i()->render();
    }
}