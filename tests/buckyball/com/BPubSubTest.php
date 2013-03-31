<?php

class BPubSub_Test extends PHPUnit_Framework_TestCase
{
    public function testFire()
    {
        $eventName = 'testEvent';
        BPubSub::i()->event($eventName);
        BPubSub::i()->on($eventName, 'BPubSub_Test_Callback::callback');
        $result = BPubSub::i()->fire($eventName);

        $this->assertContains(10, $result);
    }
}

class BPubSub_Test_Callback
{
    static public function callback()
    {
        return 10;
    }
}