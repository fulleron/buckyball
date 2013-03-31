<?php

class BViewHead_Test extends PHPUnit_Framework_TestCase
{
    public function testTitleSet()
    {
        //every time new object
        $head = BViewHead::i(true);
        $head->setTitle("Test");

        $this->assertEquals('<title>Test</title>', $head->getTitle());
    }

    public function testTitleAdd()
    {
        //every time new object
        $head = BViewHead::i(true);
        $head->setTitleSeparator(" - ");
        $head->addTitle("Test");
        $head->addTitle("Test2");

        $this->assertEquals('<title>Test2 - Test</title>', $head->getTitle());
    }

    public function testMetaTagAdd()
    {
        //every time new object
        $head = BViewHead::i(true);
        $head->addMeta("keywords", "test test test");

        $this->assertEquals('<meta name="keywords" content="test test test" />', $head->getMeta("keywords"));
    }
}
