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

        BLayout::service()->route(array(
            array('GET /test', array('Bucky_Default', 'layout_test')),
            array('GET /test/', array('Bucky_Default', 'layout_test')),
        ));
    }

    public function layout_test($params)
    {
        BLayout::service()->html()->find('body', 0)->innertest = '<div id="test" style="background:green">LAYOUT TEST</div>';
    }
}

class Bucky_Default_Controller extends BActionController
{
    public function action_home()
    {
        BLayout::service()->dispatch();
        BLayout::service()->html()->find('head', 0)->innertext = '<script>alert("TEST");</script>';
        BResponse::service()->output();
    }

    public function action_test()
    {
        BLayout::service()->dispatch();
        BLayout::service()->html()->find('body', 0)->innertext = '<span style="background:red">ACTION TEST</span>';
        BResponse::service()->output();
    }
}