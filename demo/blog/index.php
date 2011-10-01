<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

require_once "../../bucky/framework.php";

#BDebug::i()->mode('debug');

BConfig::i()->addFile('protected/config.json');

BModuleRegistry::i()->module('demo.blog', array(
    'version' => '0.1.0',
    'bootstrap' => array('file'=>'protected/blog_main.php', 'callback'=>'Blog::init'),
));

BApp::i()->run();