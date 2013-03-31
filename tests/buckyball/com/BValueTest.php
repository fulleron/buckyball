<?php

class BValueTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BValue
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new BValue('test');
    }

    /**
     * @covers BValue::toPlain
     */
    public function testToPlain()
    {
        $this->assertEquals('test', $this->object->toPlain());
    }

    /**
     * @covers BValue::__toString
     */
    public function test__toString()
    {

        $this->assertEquals('test', (string)$this->object);
    }

    /**
     * @covers BValue::toPlain
     */
    public function testToPlainArray()
    {
        $this->object = new BValue(array(1));
        $this->assertEquals(array(1), $this->object->toPlain());
    }

    /**
     * @covers BValue::__toString
     */
    public function test__toStringArray()
    {
        $this->object = new BValue(array(1));
        $this->assertEquals('Array', (string)$this->object);
    }

    /**
     * @covers BValue::toPlain
     */
    public function testToPlainStdObj()
    {
        $this->object = new BValue((object)array(1));
        $this->assertEquals((object)array(1), $this->object->toPlain());
    }

    /**
     * @covers BValue::__toString
     */
    public function test__toStringStdObj()
    {
        $this->object = new BValue((object)array(1));
        $this->assertEquals('', (string)$this->object);
    }

    /**
     * @covers BValue::toPlain
     */
    public function testToPlainCustomObj()
    {
        $this->object = new BValue(new VO(array(1)));
        $this->assertEquals('1', $this->object->toPlain());
    }

    /**
     * @covers BValue::__toString
     */
    public function test__toStringCustomObj()
    {
        $this->object = new BValue(new VO(array(1)));
        $this->assertEquals('1', (string)$this->object);
    }
}

class VO
{
    protected $_val;

    public function __construct($v)
    {
        $this->_val = $v;
    }

    public function __toString()
    {
        if(is_string($this->_val)){
            return $this->_val;
        }
        if(is_array($this->_val)){
            return implode(', ', $this->_val);
        }

        return (string) $this->_val;
    }
}