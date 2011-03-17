<?php

class Bucky_Default
{
    static public function init()
    {
        BFrontController::service()->route(array(
            array('GET /', array('Bucky_Default_Controller', 'home')),
            array('GET /test', array('Bucky_Default_Controller', 'test')),
            array('GET /test/', array('Bucky_Default_Controller', 'test')),
        ));
    }
}

class Bucky_Default_Controller extends BActionController
{
    public function action_home()
    {
        $html = BLayout::service()->html();
        $body = $html->find('head', 0);
        $body->innertext = '<script>alert("TEST");</script>';
        BResponse::service()->output();
    }

    public function action_test()
    {
        BResponse::service()->output();
    }
}