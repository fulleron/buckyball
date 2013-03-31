<?php

class BImportTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BImport
     */
    protected $object;

    protected function setUp()
    {
        $this->object = BImportDouble::i(true); // using Product import for test purposes since it
    }

    /**
     * @covers BImport::getFieldData
     */
    public function testGetFieldData()
    {
        $this->assertEquals($this->object->getFields(), $this->object->getFieldData());
    }

    /**
     * @covers BImport::getFieldOptions
     */
    public function testGetFieldOptions()
    {
        $expected = array(
            'field1' => 'field1',
            'field2' => 'field2',
            'field3' => 'field3',
            'field4' => 'field4',
        );

        $this->assertEquals($expected, $this->object->getFieldOptions());
    }

    /**
     * @covers BImport::getImportDir
     */
    public function testGetImportDir()
    {
        $dir = '/storage/import/'.$this->object->getDir();

        $this->assertEquals($dir, $this->object->getImportDir());
    }

    /**
     * @covers BImport::updateFieldsDueToInfo
     */
    public function testUpdateFieldsDueToInfo()
    {
        $this->assertNotContains('field5', array_keys($this->object->getFieldData()));
        $this->object->updateFieldsDueToInfo(array('field5' => 'f5')); // there is no documentation, how should $info look like, so I use this implementation
        $this->assertContains('field5', array_keys($this->object->getFieldData()));
    }

    /**
     * @covers BImport::getFileInfo
     */
    public function testGetFileInfo()
    {
        $file = dirname(__FILE__) . '/ftp/test.txt';
        $info = array(
            'delim' => ',',
            'skip_first' => false,
            'first_row' => array("test1","test2","test3","test4","test5"),
        );
        $result = $this->object->getFileInfo($file);
        $this->assertFalse($result); // if file does not match csv, return is false
        $files = glob(dirname(__FILE__) . '/ftp/test*csv.txt');
        foreach ($files as $file) {
            preg_match('#(comma|pipe|tab|semicolon)#', $file, $matches);
            switch ($matches[1]) {
                case 'pipe':
                    $info['delim'] = '|';
                    break;
                case 'tab':
                    $info['delim'] = "\t";
                    $info['skip_first'] = true;
                    $info['columns'][5] = 'field4';
                    $info['first_row'][5] = 'f4';
                    break;
                case 'semicolon':
                    $info['delim'] = ';';
                    $info['skip_first'] = true;
                    $info['columns'][5] = 'field4';
                    $info['first_row'][5] = 'f4';
                    break;
            }
            $result = $this->object->getFileInfo($file);
            $this->assertTrue(is_array($result));
            $this->assertEquals($info, $result);
        }

    }

    /**
     * @covers BImport::config
     */
    public function testConfig()
    {
        BConfig::i()->add(
            array(
                 'fs' => array(
                     'root_dir' => realpath(__DIR__ . DIRECTORY_SEPARATOR . '..')
                 ),
            )
        );
        BSession::i()->open('test');
        // initially config has no value
        $this->assertFalse($this->object->config());
        // prepare base config
        $config = array('test' => 'value');
        // set config, returns true
        $this->assertTrue($this->object->config($config));

        // check that current config matches expected value, 'status' key is added in method
        $config += array('status' => 'idle');
        $this->assertEquals($config, $this->object->config());

        // test that status key can be set from external config, change external config a bit and check that it is in use
        $config['status'] = 'request';
        unset($config['test']);
        $this->assertTrue($this->object->config($config));
        $this->assertEquals($config, $this->object->config());

        // test update part, status should remain in stored config,
        unset($config['status']);
        $config['test'] = 'value2';
        $this->assertTrue($this->object->config($config, true));
        $this->assertEquals($config + array('status' => 'request'), $this->object->config());

        // test removing config
        $this->assertTrue($this->object->config(false));
        $this->assertFalse($this->object->config());
    }

    /**
     * @covers BImport::run
     */
    public function testRunThrowsExceptionWithNoModel()
    {
        $this->setExpectedException('BException');
        $this->object->run();
    }

    /**
     * @covers BImport::run
     */
    public function testRunThrowsExceptionWithWrongModel()
    {
        $this->setExpectedException('BException');
        $this->object->setModel('BClass');
        $this->object->run();
    }

    /**
     * @covers BImport::run
     */
    public function testRun()
    {
        $this->object->setModel('ModelDouble');
        $config = array(
            'filename' => 'test_semicolon_csv.txt',
            'batch_size' => '10',
            'multivalue_separator' => '|',
            'nesting_separator' => '>',
            'defaults' => array('f1' => 'v1'),
        );
        $config += $this->object->getFileInfo($this->object->getImportDir() . "/$config[filename]");
        $this->object->config($config);
        $this->object->run();
    }
}

class BImportDouble extends BImport
{
    protected $fields = array(
        'field1' => array('pattern' => 'f1'),
        'field2' => array('pattern' => 'f2'),
        'field3' => array('pattern' => 'f3'),
        'field4' => array('pattern' => 'f4'),
    );

    public function updateFieldsDueToInfo($info)
    {
        foreach ($info as $field => $pattern) {
            if(!is_string($pattern) || strlen($pattern) < 2){
                continue;
            }
            $this->fields[$field]['pattern'] = $pattern;
        }
    }

    /**
     * @return string
     */
    public static function getOrigClass()
    {
        return self::$_origClass;
    }

    public function getDir()
    {
        return $this->dir;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }
}

class ModelDouble extends BClass
{
    public function import($data)
    {
        return true;
    }
}