<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

require_once "../bucky/framework.php";

BApp::init();
BApp::config('protected/config.json');
BApp::load();
BApp::run();
