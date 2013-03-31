<?php

class BUtil_Test extends PHPUnit_Framework_TestCase
{
    public function testToJson()
    {
        $data = array('key' => 'value');
        $json = BUtil::toJson($data);
        $this->assertTrue(is_string($json));
    }

    public function testFromJson()
    {
        $json = '{"key":"value"}';
        $data = BUtil::fromJson($json);
        $this->assertTrue(is_array($data));
        $this->assertTrue(isset($data['key']));
    }

    public function testToJavascript()
    {
        $data = array('key' => 'value');
        $json = BUtil::toJavaScript($data);
        $this->assertTrue(is_string($json));
    }

    public function testObjectToArray()
    {
        $obj = new stdClass();
        $obj->key = 'value';
        $array = BUtil::objectToArray($obj);
        $this->assertTrue(is_array($array));
        $this->assertTrue(isset($array['key']));
    }

    public function testArrayToObject()
    {
        $array = array('key' => 'value');
        $obj = BUtil::arrayToObject($array);

        $this->assertTrue(is_object($obj));
        $this->assertTrue(isset($obj->key));
        $this->assertEquals('value', $obj->key);
    }

    public function testSprintfn()
    {
        $format = 'Say %hi$s %bye$s!';
        $args = array('hi' => 'Hi', 'bye' => 'Goodbye');
        $string = BUtil::sprintfn($format, $args);
        $this->assertEquals('Say Hi Goodbye!', $string);
    }

    public function testInjectVars()
    {
        $str = 'One :two :three';
        $args = array('two' => 2, 'three' => 3);
        $string = BUtil::injectVars($str, $args);
        $this->assertEquals('One 2 3', $string);
    }

    public function testArrayCompare()
    {
        $a1 = array(1,2,array(3,4,5));
        $a2 = array(1,2,array(3,4,5,6));
        $res = BUtil::arrayCompare($a2, $a1);
        // 0 - number of parameter with difference
        // 2 - first dimenstion of array
        // 3 - second dimenstion of array
        $expected = array('0' => array('2' => array('3' => 6)));
        $this->assertEquals($expected, $res);

        $a1 = array(1,2,array(3,4,5));
        $a2 = array(1,2,array(3,4,5,6));
        $res = BUtil::arrayCompare($a1, $a2);
        //order of parameters was changed, so we expected '1' as array key
        $expected = array('1' => array('2' => array('3' => 6)));
        $this->assertEquals($expected, $res);
    }

    public function testArrayMerge()
    {
        $a1 = array(1,2,array(3,4,5));
        $a2 = array(1,2,array(3,4,5,6));
        $res = BUtil::arrayMerge($a1, $a2);
        $expected = array(1, 2, array(3,4,5), array(3,4,5,6));
        $this->assertEquals($expected, $res);

        $a1 = array(1,2,array(3,4,5), 6);
        $a2 = array(1,2,array(3,4,5,6), 7);
        $res = BUtil::arrayMerge($a1, $a2);
        $expected = array(1, 2, array(3,4,5), 6, array(3,4,5,6), 7);
        $this->assertEquals($expected, $res);
    }

    public function testRandomStrng()
    {
        $str = Butil::randomString();
        $this->assertTrue(is_string($str));

        $str = Butil::randomString(4, 'a');
        $this->assertEquals('aaaa', $str);
    }

    public function testRandomPattern()
    {
        $pattern = "{U10}-{L5}-{D2}";
        $res = BUtil::randomPattern($pattern);
        list($upper, $lower, $digits) = explode("-", $res);
        $this->assertTrue(strtoupper($upper) == $upper);
        $this->assertTrue(strtolower($lower) == $lower);
        $this->assertTrue(is_numeric($digits));
    }

    public function testUnparseUrl()
    {
        $urlInfo = array(
            'scheme' => 'http',
            'user' => 'utest',
            'pass' => 'ptest',
            'host' => 'google.com',
            'port' => 80,
            'path' => '/i/test/',
            'query' => 'a=b&c=d',
            'fragment' => 'start'
        );
        $url = Butil::unparseUrl($urlInfo);
        $this->assertEquals('http://utest:ptest@google.com:80/i/test/?a=b&c=d#start', $url);
    }

    public function testSetUrlQuery()
    {
        $url = "http://google.com?a=b&c=d";
        $urlNew = BUtil::setUrlQuery($url, array('f' => 'e'));
        $this->assertEquals($url.'&f=e', $urlNew);

        $urlNew = BUtil::setUrlQuery($url, array('c' => 'd2'));
        $this->assertEquals("http://google.com?a=b&c=d2", $urlNew);
    }

    public function testPreviewText()
    {
        $text = 'abc abc abc abc abc';
        $textPreview = BUtil::previewText($text, 10);
        $this->assertEquals("abc abc ", $textPreview);

        $textPreview = BUtil::previewText($text, 13);
        $this->assertEquals("abc abc abc ", $textPreview);
    }
}