<?php

// Bootstrap class
class Bucky_Demo
{
    // Bootstrap method. Can be a function as well.
    static public function init()
    {
        // Declare routes. Note, that classes are clear for IDE autocompletion.
        // Shown here a shorthand, combining declaration into the same API call with array.
        // Each declaration can be made in a separate call, if desired.
        BFrontController::service()->route(array(

            // RESTful, can specify HTTP methods for different routes (GET, POST, PUT, DELETE)
            array('GET /', array('Bucky_Default_Controller', 'action_home')),

            array('GET /demo', array('Bucky_Default_Controller', 'action_demo')),

            // /test and /test/ are distinct routes
            array('GET /demo/', array('Bucky_Default_Controller', 'action_demo')),

            // can account for parameters in URI
            // with params and without params are distinct routes
            array('GET /demo/:param', array('Bucky_Default_Controller', 'action_demo_param')),
        ));

        // Declare views with default arguments.
        BLayout::service()->view(array(

            // Views are called by the 1st argument.
            // This allows for overriding views, while still keeping all files within module's folder.
            array('child', array('template'=>'view/child.php', 'args'=>array('def'=>'VIEW DEFAULT VAR TEST'))),
        ));

        // Can add all views within a folder, folder name will be removed from their names
        #BLayout::service()->allViews('view');

        // [Optional] Can change main view, by default 'main'
        #BLayout::service()->mainView('main');

        // Declare layout updates. Note that can use the same bootstrap class for callbacks
        BEventRegistry::service()->observe(array(
            array('layout.dispatch', array(__CLASS__, 'layout_all')),
            array('layout.dispatch: GET /demo', array(__CLASS__, 'layout_demo')),
            array('layout.dispatch: GET /demo/', array(__CLASS__, 'layout_demo')),
            array('layout.dispatch: GET /demo/:param', array(__CLASS__, 'layout_demo')),
        ));
    }

    public function layout_all($params)
    {
        // The HTML can be traversed and changed in jQuery style
        BLayout::service()->find('body')->append('<div id="test" style="background:cyan">LAYOUT ALL TEST</div>');
    }

    // Callback for layout update test, accepts arguments overridden in this order:
    // Route default arguments, URI params, layout dispatch params.
    public function layout_demo($params)
    {
        // The HTML can be traversed and changed in jQuery style
        BLayout::service()->find('body')->append('<div id="test" style="background:green">LAYOUT TEST</div>');
    }

    // Callback for event observer, accepts arguments overridden in this order:
    // Event default arguments, observer default arguments, event dispatch arguments
    public function event_demo($params)
    {
        BLayout::service()->find('body')->append('<div id="event_test" style="background:blue">EVENT TEST</div>');
    }
}

// Controller is the only class at this moment that requires to be extended from framework
class Bucky_Default_Controller extends BActionController
{
    // all the action methods should start with 'action_' prefix
    public function action_home()
    {
        // Dispatch layout updates for the current route. Runs 'GET *' routes as well
        BLayout::service()->dispatch();

        // add HEAD entries
        BLayout::service()->find('head')->append('<script>alert("TEST");</script>');

        // Output resulting page
        BResponse::service()->output();
    }

    public function action_test()
    {
        $layout = BLayout::service();

        // Dispatch layout updates
        $layout->prepare();
/*
        // Append some HTML into a specific HTML element
        $time = microtime(true);
        for ($i=0; $i<1000; $i++) {
            $layout->find('#test')->append('<span style="background:red">ACTION TEST</span>');
        }
        $layout->find('body')->append('<div>'.(microtime(true)-$time).'</div>');
*/
        // Dispatch event
        BEventRegistry::service()->dispatch('test_event', array('dsp_var'=>'EVENT DISPATCH VAR TEST'));

        // Output resulting page
        BResponse::service()->output();
    }
}