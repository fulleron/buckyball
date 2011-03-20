# Buckyball PHP Framework #

## Main goals ##

* PHP is fun again.
* Decoupling everything that should not be coupled.
* Providing more flexibility than Magento framework, but much more efficiently.
* In development mode logging which module/file changes what.
* On frontend access info about and enable/disable modules.
* Everything non essential is a module, and can be disabled.
* Module files are confined to one folder.
* Keep folder structure and number of files as minimal as possible.
* Module requires only bootstrap callback. Everything else is up to the developer.
* Bootstrap callback only injects module's callbacks into request.
* Do not force to use classes.
* IDE friendly (autocomplete, phpdoc, etc)
* Developer friendly (different file names in IDE tabs)
* Debug friendly (concise print_r, debugbacktrace, debug augmentation GUI on frontend)
* Versioning system friendly (module confined to 1 folder)
* Do not fight or stronghand with the developer, do not try to force, limit, etc.
* Developer will find ways to work around or use undocumented API.
* Conserve memory by not storing unnecessary data or configuration more than needed.
* Minimize framework overhead (~10ms on slow server)

## Installation ##

This framework was just released and I'm not sure if it deserves even to be called alpha.
So, yada yada.

- cd public_html/dev/
- git clone git://github.com/unirgy/bucky.git
- cp index.php.dist index.php
- cp storage/private/config/local.json.dist storage/private/config/local.json
- vi storage/private/config/local.json
- chmod 777 storage
- Browse to http://server.com/bucky/

## Concepts ##

### Application ###
* Application can run within few modes:
  * "Development": everything is loaded dynamically, without caching/real-time optimizations, errors are displayed
  * "Staging": Caching/real-time optimizations manually enabled/disabled, errors are displayed/sent/logged
  * "Production": Caching/real-time optimiations are enabled, errors are logged/sent to admin

### Modules ###
* Module is a distinct set of functionality with a well defined feature set
* All module's files and folders should be within the same folder.
* Inner file/folder structure within the module folder is up to the developer.
* Module can be easily disabled, resulting in disabling all functionality defined in the module.
* Module can depend on other modules, optionally limited by version range.
* Dependent modules will be loaded after module they depend on.
* Circular dependency is not allowed.
* Missing or out of range version dependency can be handled in few ways:
  * "error": throw error on load and stop execution (dev mode)
  * "silent": do not throw error, do not load dependent module
  * "ignore": load dependent module, used to define module loading sort order
* Module is declared by manifest.json file, which contains:
  * name
  * version
  * bootstrap: file and callback
  * dependencies
* Module bootstrap callback will be ran during application initialization, and should contain reference to all functionality within Buckyball framework.
* Only manifest.json and bootstrap file are required for Bucky module. Existing applications/libraries can be easily made as modules, by adding bootstrap wrapper.

### Services ###
* Service is a globally accessible singletons that contains distinct functionality.
* Services can be overridden by extending original class, but this is discouraged.

### Debugging ###
* All the declarations and actions should be logged in dev mode, to be analyzed if needed.
* All the errors should be logged and/or sent in staging or production modes.

## Application Demo ##

### index.php ###
    <?php
    // load framework file, doesn't have to be within application folder
    require_once "bucky/framework.php";

    // initialize framework services
    BApp::init();

    // load local configuration (db, enabled modules, etc)
    BApp::config('storage/private/config/local.json');

    // load
    BApp::load('bucky/plugins/*,bucky');
    // Same, order doesn't matter, modules will be loaded in order based on dependencies:
    // BApp::load(array('bucky/plugins/*', 'bucky'));
    // BApp::load('bucky/plugins/*'); BApp::load('bucky');

    // Dispatch the application
    BApp::run();

### bucky/manifest.json ###

    {
        "modules": {
            "bucky_default": {
                "bootstrap": {"file":"default.php", "callback":"Bucky_Default::init"},
                "version": "0.0.1",
                "depends": {
                    "modules": {
                        "phpQuery": {"action":"error"}
                    }
                }
            }
        }
    }

### bucky/default.php (bootstrap file) ###

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