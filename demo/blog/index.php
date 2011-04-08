<?php

ini_set('display_errors', 1);
error_reporting(E_ALL | E_STRICT);

require_once "../../bucky/framework.php";

BApp::i()->config('protected/config.json')->load()->run();
