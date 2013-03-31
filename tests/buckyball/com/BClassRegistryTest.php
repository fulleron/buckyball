<?php

class BClassRegistry_Test extends PHPUnit_Framework_TestCase
{
    public function SetUp()
    {
        BClassRegistry::i()->unsetInstance();
    }

    public function tearDown()
    {
        BClassRegistry::i()->unsetInstance();
    }

    public function testOverrideClass()
    {
        $class = 'BClassRegistry_Test_A';
        $newClass = 'BClassRegistry_Test_B';
        BClassRegistry::i()->overrideClass($class, $newClass);
        $a = $class::i();
        $this->assertEquals("B", $a->me());
    }

    //fixed: need to understand why overrideMethod doesn't work
    public function testOverrideMethod()
    {
        $class = 'BClassRegistry_Test_A';
        $method = 'sayA';

        BClassRegistry::i()->overrideMethod($class, $method, array('BClassRegistry_Test_B', 'sayB'));

        $a = $class::i();

        $this->assertEquals("B", $a->sayA());
    }

    //fixed: need to understand why augmentMethod doesn't work
    public function testAugmentMethod()
    {
        $class = 'BClassRegistry_Test_A';
        $method = 'augmentA';

        BClassRegistry::i()->augmentMethod($class, $method, array('BClassRegistry_Test_B', 'augmentB'));

        $a = $class::i();

        $this->assertEquals("B", $a->augmentA());
    }

    //todo: why don't work
    public function testAugmentPropertySet()
    {
        BClassRegistry::i()->augmentProperty('BClassRegistry_Test_A', 'foo', 'set',
                'override', 'BClassRegistry_Test_AugmentProperty::newSetter');

        $a = BClassRegistry_Test_A::i();
        $a->foo = 1;

        //todo: uncomment
        $this->assertEquals(6, $a->foo);
    }

    //todo: why don't work
    public function testAugmentPropertyGet()
    {
        BClassRegistry::i()->augmentProperty('BClassRegistry_Test_A', 'foo', 'get',
                'after', 'BClassRegistry_Test_AugmentProperty::newGetter');

        $a = BClassRegistry_Test_A::i();

        //todo: uncomment
        $this->assertEquals(10, $a->foo);
    }
}

class BClassRegistry_Test_A extends BClass
{
    public $foo = 0;
    public function me()
    {
        return 'A';
    }

    static public function sayA()
    {
        return 'A';
    }

    static public function augmentA()
    {
        return 'A';
    }
}

class BClassRegistry_Test_B extends BClass
{
    public function me()
    {
        return 'B';
    }

    static public function sayB($origObject=null)
    {
        return 'B';
    }

    static public function augmentB($result, $origObject)
    {
        $result = 'B';
        return $result;
    }
}

class BClassRegistry_Test_AugmentProperty extends BClass
{
    static public function newSetter($object, $property, $value)
    {
        $object->$property = $value + 5;
    }

    static public function newGetter($object, $property, $prevResult)
    {
        return $prevResult+10;
    }
}