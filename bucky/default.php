<?php

class Bucky_Default
{
    static public function init()
    {
        /*
        BFrontController::service()->route(array(
            array('GET /', array('Bucky_Default_Controller', 'home')),
            array('GET /test', array('Bucky_Default_Controller', 'test')),
            array('GET /test/', array('Bucky_Default_Controller', 'test')),
        ));
        */
    }
}

class Bucky_Default_Controller extends BActionController
{
    public function action_home()
    {
        BLayout::service()->html()->find('head', 0)->innertext = '<script>alert("TEST");</script>';
        BResponse::service()->output();
    }

    public function action_test()
    {
        BLayout::service()->html()->find('body', 0)->innertext = 'TEST';
        BResponse::service()->output();
    }
}