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
* bucky/buckyball.php
*
* This file is the first bootstrap to initialize BuckyBall PHP Framework
*/

define('BUCKYBALL_VERSION', '0.5.0');

define('BUCKYBALL_ROOT_DIR', __DIR__);

/**
* Load all components immediately
*/

$comDir = __DIR__.'/com/';
require_once $comDir.'core.php';
require_once $comDir.'misc.php';
require_once $comDir.'lib/idiorm.php';
require_once $comDir.'lib/paris.php';
require_once $comDir.'db.php';
require_once $comDir.'cache.php';
require_once $comDir.'module.php';
require_once $comDir.'controller.php';
require_once $comDir.'layout.php';
require_once $comDir.'import.php';

/**
* Minify all components into 1 compact file.
*
* Syntax: php buckyball.php -c
* Output: buckyball.min.php
*
* @deprecated Is there a point for that?
*/

if (getopt('c')) {
    $minified = array();
    foreach (array('core','misc','lib/idiorm','lib/paris','db','cache','module','controller','layout','cache') as $f) {
        list(, $minified[]) = explode(' ', php_strip_whitespace($comDir . $f . '.php'), 2);
    }
    $contents = "<?php define('BUCKYBALL_VERSION', '" . BUCKYBALL_VERSION . "'); " . join(' ', $minified);
    file_put_contents('buckyball.min.php', $contents);
}
