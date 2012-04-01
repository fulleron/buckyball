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

$comDir = __DIR__.'/com/';
define('BUCKYBALL_ROOT_DIR', __DIR__);

/**
* Load all components immediately
*/

require $comDir.'core.php';
require $comDir.'lib/idiorm.php';
require $comDir.'lib/paris.php';
require $comDir.'db.php';
require $comDir.'module.php';
require $comDir.'controller.php';
require $comDir.'layout.php';
require $comDir.'misc.php';

/**
* Minify all components into 1 compact file.
*
* Syntax: php buckyball.php -c
* Output: buckyball.min.php
*/

if (getopt('c')) {
    $minified = array();
    foreach (array('core','lib/idiorm','lib/paris','db','module','controller','layout','misc') as $f) {
        list(, $minified[]) = explode(' ', php_strip_whitespace($comDir.$f.'.php'), 2);
    }
    file_put_contents('buckyball.min.php', '<?php '.join(' ', $minified));
}

