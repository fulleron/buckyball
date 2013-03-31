<?php

class BDataTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BData
     */
    protected $object;

    protected $sampleData = array(
        1, 2, 'three', 'four' => 4
    );

    protected function setUp()
    {
        $this->object = new BData($this->sampleData);
    }

    /**
     * @covers BData::as_array
     */
    public function testAs_array()
    {
        $this->assertEquals($this->sampleData, $this->object->as_array());
    }

    /**
     * @covers BData::__get
     */
    public function test__get()
    {
        $this->assertNull($this->object->five);
        $this->assertNull($this->object['five']);
        $this->assertEquals(1, $this->object[0]);
        $this->assertEquals(2, $this->object[1]);
        $this->assertEquals('three', $this->object[2]);
        $this->assertEquals(4, $this->object['four']);
    }

    /**
     * @covers BData::__set
     */
    public function test__set()
    {
        $this->assertNull($this->object->five);
        $this->assertNull($this->object['five']);
        $this->object->five = 5;
        $this->assertEquals(5, $this->object->five);
        $this->assertEquals(5, $this->object['five']);
    }

    /**
     * @covers BData::offsetSet
     */
    public function testOffsetSet()
    {
        $this->assertNull($this->object['five']);
        $this->assertNull($this->object[4]);
        $this->object->offsetSet('five', 5);
        $this->object->offsetSet(null, 6);
        $this->assertEquals(5, $this->object['five']);
        $this->assertEquals(6, $this->object[3]);
    }

    /**
     * @covers BData::offsetExists
     */
    public function testOffsetExists()
    {
        $this->assertTrue($this->object->offsetExists(1));
        $this->assertFalse($this->object->offsetExists(10));
    }

    /**
     * @covers BData::offsetUnset
     */
    public function testOffsetUnset()
    {
        $this->assertNull($this->object['five']);
        $this->object['five'] = 5;
        $this->assertTrue(5 == $this->object['five']);
        $this->object->offsetUnset('five');
        $this->assertFalse(5 == $this->object['five']);
        $this->assertNull($this->object['five']);
    }

    /**
     * @covers BData::offsetGet
     */
    public function testOffsetGet()
    {
        $this->assertNull($this->object->offsetGet('five'));
        $this->assertEquals(4, $this->object->offsetGet('four'));
        $this->assertEquals('three', $this->object->offsetGet(2));
    }

    public function testInitWithNonArray()
    {
        $obj = new BData(1);

        $this->assertInstanceOf('BData', $obj);

        $this->assertNull($obj[0]);
        $obj[0] = 1;
        $this->assertNotNull($obj[0]);
    }
}
