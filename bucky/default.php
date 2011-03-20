<?php

// Bootstrap class
class Bucky_Default
{
    // Bootstrap method. Can be a function as well.
    static public function init()
    {
        // Declare routes. Note, that classes are clear for IDE autocompletion.
        // Shown here a shorthand, combining declaration into the same API call with array.
        // Each declaration can be made in a separate call, if desired.
        BFrontController::service()->route(array(

            // RESTful, can specify HTTP methods for different routes (GET, POST, PUT, DELETE)
            array('GET /', array('Bucky_Default_Controller', 'home')),

            array('GET /test', array('Bucky_Default_Controller', 'test')),

            // /test and /test/ are distinct routes
            array('GET /test/', array('Bucky_Default_Controller', 'test')),

            // can account for parameters in URI
            // with params and without params are distinct routes
            array('GET /test/:param', array('Bucky_Default_Controller', 'test')),
        ));

        // Declare layout updates. Note that can use the same bootstrap class for callbacks
        BLayout::service()->route(array(
            array('GET *', array(__CLASS__, 'layout_all')),
            array('GET /test', array(__CLASS__, 'layout_test')),
            array('GET /test/:param', array(__CLASS__, 'layout_test')),
        ));

        // Declare views with default arguments.
        BLayout::service()->view(array(

            // Views are called by the 1st argument.
            // This allows for overriding views, while still keeping all files within module's folder.
            array('main.php', 'view/main.php', array('def'=>'VIEW DEFAULT VAR TEST')),
        ));

        // Can add all views within a folder, folder name will be removed from their names
        BLayout::service()->allViews('view');

        // [Optional] Declare events with default arguments
        BEventRegistry::service()->event(array(
            array('test_event', array('def_event'=>'EVENT DEFAULT VAR TEST')),
        ));

        // Declare event observers with default arguments
        BEventRegistry::service()->observe(array(
            array('test_event', array(__CLASS__, 'event_test'), array('dev_obs'=>'OBSERVER DEFAULT VAR TEST')),
        ));
    }

    public function layout_all($params)
    {
        // The HTML can be traversed and changed in jQuery style
        BLayout::service()->find('body')->append('<div id="test" style="background:cyan">LAYOUT ALL TEST</div>');
    }

    // Callback for layout update test, accepts arguments overridden in this order:
    // Route default arguments, URI params, layout dispatch params.
    public function layout_test($params)
    {
        // The HTML can be traversed and changed in jQuery style
        BLayout::service()->find('body')->append('<div id="test" style="background:green">LAYOUT TEST</div>');
    }

    // Callback for event observer, accepts arguments overridden in this order:
    // Event default arguments, observer default arguments, event dispatch arguments
    public function event_test($params)
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

        // Load a view as initial HTML document
        $layout->doc(BLayout::service()->renderView('main.php', array(
            'var'=>'VIEW LOCAL VAR TEST',
            'param'=>BRequest::service()->params('param'),
        )));

        // Dispatch layout updates
        $layout->dispatch();

        // Append some HTML into a specific HTML element
        $layout->find('#test')->append('<span style="background:red">ACTION TEST</span>');

        // Dispatch event
        BEventRegistry::service()->dispatch('test_event', array('dsp_var'=>'EVENT DISPATCH VAR TEST'));

        // Output resulting page
        BResponse::service()->output();
    }
}