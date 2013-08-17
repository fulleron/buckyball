<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

require_once "../../buckyball.php";

BDebug::i()->mode('debug');

#BConfig::i()->addFile('protected/config.json');
BConfig::i()->add(array(
    'db' => array(
        'dbname'=>'fulleron', 'username'=>'web', 'password'=>'',
        'logging'=>true, 'implicit_migration'=>true
    ),
    'module_run_level'=>array('request'=>array('Blog'=>'REQUIRED')),
));

BModuleRegistry::i()->addModule('Blog', array(
    'version' => '0.1.0',
    'bootstrap' => array('file'=>'Blog.php', 'callback'=>'Blog::bootstrap'),
    'migrate' => 'Blog::migrate',
));

BApp::i()->run();