<?php

class BClassTest extends PHPUnit_Framework_TestCase
{

    /**
     * @covers BClass::origClass
     */
    public function testOrigClass()
    {
        $obj = new AClass();
        $this->assertEquals(get_class($obj), AClass::origClass());
    }

    /**
     * @covers BClass::i
     */
    public function testI()
    {
        $obj = AClass::i();
        $this->assertInstanceOf('BClass', $obj);

        $objTwo = AClass::i();
        $this->assertSame($obj, $objTwo);

        $objThree = BClass::i($obj);
        $this->assertSame($obj, $objThree);

        $objFour = AClass::i(true);
        $this->assertEquals(get_class($obj), get_class($objFour));
        $this->assertNotSame($obj, $objFour);
    }

    /**
     * @covers BClass::__call
     */
    public function test__call()
    {
        $obj = AClass::i(true);
        $this->assertEquals(1, $obj->__call('method', array()));
    }

    /**
     * @covers BClass::__callStatic
     */
    public function test__callStatic()
    {
        $this->assertTrue(1 == AClass::__callStatic('method', array()));
    }
}


class AClass extends BClass
{
    protected static $_origClass = __CLASS__;

    public static function method()
    {
        return 1;
    }
}