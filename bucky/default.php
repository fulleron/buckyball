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

        BLayout::service()->view(array(
            array('main.php', 'view/main.php', array('def'=>'VIEW DEFAULT VAR TEST')),
        ));

        BEventRegistry::service()->observe(array(
            array('test_event', array('Bucky_Default', 'event_test')),
        ));
    }

    public function layout_test($params)
    {
        BLayout::service()->find('body')->append('<div id="test" style="background:green">LAYOUT TEST</div>');
    }

    public function event_test($params)
    {
        BLayout::service()->find('body')->append('<div id="event_test" style="background:blue">EVENT TEST</div>');
    }
}

class Bucky_Default_Controller extends BActionController
{
    public function action_home()
    {
        BLayout::service()->dispatch();

        BLayout::service()->find('head')->append('<script>alert("TEST");</script>');

        BResponse::service()->output();
    }

    public function action_test()
    {
        $layout = BLayout::service();

        $layout->doc(BLayout::service()->renderView('main.php', array('var'=>'VIEW LOCAL VAR TEST')));

        $layout->dispatch();

        $layout->find('#test')->append('<span style="background:red">ACTION TEST</span>');

        BEventRegistry::service()->dispatch('test_event');

        BResponse::service()->output();
    }
}