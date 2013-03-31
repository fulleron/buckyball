# Buckyball PHP Framework #

## Main goals ##

* PHP is fun again.
* Complication of implementation should be proportional to complexity of specification.
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
* Minimize framework overhead (~3ms on slow server)

## Q&A ##
* Q: Why yet another PHP framework?
* A: Competition is good, it drives innovation and prevents stagnation. Also, I enjoy doing it.

## Performance ##
* Server: Intel(R) Core (TM) 2 CPU E7400 @ 2.80GHz
* Framework overhead:
  * Without idiorm/paris and phpQuery: 3.6ms, 786,432 B
  * Without phpQuery plugin: 6ms, 1.310 MB peak, 1.048 MB after dispatch
  * With phpQuery plugin, without pq usage: 18ms, 2.883 MB
  * With phpQuery plugin, with pq minimal usage: 23ms, 2.883 MB

## Installation ##

This framework was just released and I'm not sure if it deserves even to be called alpha.
So, yada yada.

- cd public_html/dev/
- git clone git://github.com/unirgy/buckyball.git
- cp index.php.dist index.php
- cp storage/private/config/local.json.dist storage/private/config/local.json
- vi storage/private/config/local.json
- chmod 777 storage
- Browse to http://server.com/buckyball/

## Wiki ##

(http://unirgy.com/wiki/buckyball)

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

## 3rd Party Libraries ##
* Already implemented:
  * [Idiorm & Paris](http://j4mie.github.com/idiormandparis/)
  * [phpQuery](http://code.google.com/p/phpquery/)

## Application Demo ##

For demo please see /blog

Make sure to edit protected/config.json to set your own environment details, and run protected/blog.sql on your DB to create tables.

## Walkthrough ##

By widely accepted tradition we start with Hello, World!

helloworld.php
==============
  Hello, World!

Seriously, if all you need is to output "Hello, World!" you don't really need more than that.
BuckyBall framework is build with this concept in mind:
  Complication of implementation should be proportional to complexity of specification.

<?php

include "buckyball.php";

BFrontController::i()->route('GET /', function() {
    echo 'Hello, World!';
});


<?php

BFrontController::i()
    ->route('GET /', 'DemoController.index')
    ->route('GET /test1/:param', 'DemoController.test1')
    ->route('GET /test2/*param', 'DemoController.anything')
    ->route('GET /crud/.action/:param', 'DemoController')
;

class DemoController extends BActionController
{
    public function afterDispatch()
    {
        BResponse::i()->render();
    }

    public function action_index()
    {
        BResponse::i()->set('Hello, World!');
    }

    public function action_test1()
    {
        BResponse::i()->set('Hello, '.BRequest::i()->params('param'));
    }

    public function action_test2()
    {
        BResponse::i()->set('Hello, '.BRequest::i()->params('param'));
    }

    public function action_crud()
    {

    }
}

