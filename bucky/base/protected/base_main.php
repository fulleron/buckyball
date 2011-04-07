<?php

class Bucky_Base
{
    static public function init()
    {
        BFrontController::i()
            ->route('GET /', array('Bucky_Base_Controller', 'home'));

        BLayout::i()->allViews('protected/view')
            ->view('head', array('view_class'=>'BViewHead'))
            ->view('body', array('view_class'=>'BViewList'))
        ;
    }
}

class Bucky_Base_Controller extends BActionController
{
    public function action_home()
    {
        BLayout::i()->view('body')->append('home');
        BResponse::i()->output();
    }
}