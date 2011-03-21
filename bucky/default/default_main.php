<?php

class Bucky_Default
{
    static public function init()
    {
        BFrontController::service()
            ->route('GET /', array('Bucky_Default_Controller', 'home'));

        BLayout::service()
            ->view('main', array('template'=>'view/main.php'))
            ->view('head', array('view_class'=>'BViewList'))
            ->view('body', array('view_class'=>'BViewList'))
            ->view('congrats', array('template'=>'view/congrats.php'));

        BLayout::service()->view('body')->append('congrats');

        $layout = BLayout::service();
        $body = BLayout::service()->view('body');
        for ($i=0; $i<1000; $i++) {
            $layout->view('test'.$i, array('template'=>'view/congrats.php', 'args'=>array('i'=>$i)));
            $body->append('test'.$i);
        }

    }
}

class Bucky_Default_Controller extends BActionController
{
    public function action_home()
    {
        $this->renderOutput();
    }
}