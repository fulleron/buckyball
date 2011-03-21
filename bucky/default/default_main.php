<?php

class Bucky_Default
{
    static public function init()
    {
        BFrontController::service()
            ->route('GET /', array('Bucky_Default_Controller', 'action_home'));

        BLayout::service()
            ->view('main', array('template'=>'view/main.php'))
            ->view('head', array('view_class'=>'BViewList'))
            ->view('body', array('view_class'=>'BViewList'))
            ->view('congrats', array('template'=>'view/congrats.php'));

        BLayout::service()->view('body')->append('congrats');
    }
}

class Bucky_Default_Controller extends BActionController
{
    public function action_home()
    {
        $this->renderOutput();
    }
}