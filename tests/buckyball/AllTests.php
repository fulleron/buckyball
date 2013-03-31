<?php

if (!defined('PHPUnit_MAIN_METHOD')) {
    define('PHPUnit_MAIN_METHOD', 'BAllTests::main');
}

require_once __DIR__.'/com/BUtilTest.php';
require_once __DIR__.'/com/BLocaleTest.php';
require_once __DIR__.'/com/BAppTest.php';
require_once __DIR__.'/com/BConfigTest.php';
require_once __DIR__.'/com/BClassRegistryTest.php';
require_once __DIR__.'/com/BPubSubTest.php';
require_once __DIR__.'/com/BLayoutTest.php';
require_once __DIR__.'/com/BViewTest.php';
require_once __DIR__.'/com/BViewHeadTest.php';
require_once __DIR__.'/com/BClassDecoratorTest.php';
require_once __DIR__.'/com/BDbTest.php';
require_once __DIR__.'/com/BValueTest.php';

$testFiles = glob(__DIR__.'/com/*Test.php');
foreach ($testFiles as $test) {
    if(is_readable($test) && !is_dir($test)){
        require_once $test;
    }
}
//print_r($testFiles);
class BAllTests
{
    public static function main()
    {
        PHPUnit_TextUI_TestRunner::run(self::suite());
    }

    /**
     * All tests
     *
     * @return PHPUnit_Framework_TestSuite
     */
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Buckyball - Buckyball');

        // Start tests...
        $suite->addTestSuite('BUtil_Test');
        $suite->addTestSuite('BLocale_Test');
        $suite->addTestSuite('BApp_Test');
        $suite->addTestSuite('BConfig_Test');
        $suite->addTestSuite('BClassRegistry_Test');
        $suite->addTestSuite('BPubSub_Test');
        $suite->addTestSuite('BLayout_Test');
        $suite->addTestSuite('BView_Test');
        $suite->addTestSuite('BViewHead_Test');
        $suite->addTestSuite('BClassDecorator_Test');
        $suite->addTestSuite('BDb_Test');
        $suite->addTestSuite('BValueTest');
        $suite->addTestSuite('BClassTest');
        $suite->addTestSuite('BDataTest');
        $suite->addTestSuite('BFtpClientTest');
        $suite->addTestSuite('BImportTest');

        return $suite;
    }
}

if (PHPUnit_MAIN_METHOD == 'BAllTests::main') {
    BAllTests::main();
}