<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

require_once "../../bucky/buckyball.php";

BDebug::i()->mode('debug');

BConfig::i()->addFile('protected/config.json');

BModuleRegistry::i()->module('Blog', array(
    'version' => '0.1.0',
    'bootstrap' => array('file'=>'protected/Blog.php', 'callback'=>'Blog::init'),
));

BApp::i()->run();