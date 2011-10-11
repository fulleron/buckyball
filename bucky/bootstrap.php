<?php
/**
* Copyright 2011 Unirgy LLC
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/

/**
* bucky/bootstrap.php
*
* This file is the first bootstrap to initialize BuckyBall PHP Framework
*/

$comDir = __DIR__.'/com/';

/**
* Auto load components
*/
spl_autoload_register(function($name) use($comDir) {
    switch ($name) {
        case 'BClass': case 'BApp': case 'BException': case 'BConfig':
        case 'BClassRegistry': case 'BClassDecorator':
        case 'BEventRegistry': case 'BSession': case 'BUtil':
            require $comDir.'core.php';
            break;

        case 'BPDO': case 'BDb': case 'BORM': case 'BModel':
            /**
            * @see http://j4mie.github.com/idiormandparis/
            */
            require $comDir.'lib/idiorm.php';
            require $comDir.'lib/paris.php';
            require $comDir.'db.php';
            break;

        case 'BModule': case 'BModuleRegistry':
        case 'BDbModule': case 'BDbModuleConfig':
            require $comDir.'module.php';
            break;

        case 'BRequest': case 'BResponse':
        case 'BFrontController': case 'BActionController':
        case 'BRouteNode': case 'BRouteObserver':
            require $comDir.'controller.php';
            break;

        case 'BLayout': case 'BView': case 'BViewHead': case 'BViewList':
            require $comDir.'view.php';
            break;

        case 'BCache': case 'BDebug': case 'BLocale': case 'BUnit': case 'BUser':
            require $comDir.'misc.php';
            break;
    }
}, false);

/**
* Load all components immediately
*/
/*
require $comDir.'core.php';
require $comDir.'db.php';
require $comDir.'module.php';
require $comDir.'controller.php';
require $comDir.'view.php';
require $comDir.'misc.php';
*/

/**
* Minify all components into 1 compact file.
*
* Syntax: php bootstrap.php -c
* Output: buckyball.min.php
*/

if (getopt('c')) {
    $minified = array();
    foreach (array('core','lib/idiorm','lib/paris','db','module','controller','view','misc') as $f) {
        list(, $minified[]) = explode(' ', php_strip_whitespace($comDir.$f.'.php'), 2);
    }
    file_put_contents('buckyball.min.php', '<?php '.join(' ', $minified));
}

