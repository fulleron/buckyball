<?php

class BDb_Test extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $db = BDb::i();
        $vo = new Entity();
        $db->run("DROP TABLE IF EXISTS {$vo->fTable}");
        $db->run("DROP TABLE IF EXISTS {$vo->table}");
        $db->ddlClearCache();
        BConfig::i()->add(
        // config is being reset in its tests, so we have to load default config used to be able to test
            array(
                 'db' => array(
                     'host'     => 'localhost',
                     'dbname'   => 'fulleron_test',
                     'username' => 'pp',
                     'password' => '111111',
                 ),
            )
        );
    }

    /**
     * @covers BDb::i
     */
    public function testGetDBInstance()
    {
        $db = BDb::i(true);
        $this->assertInstanceOf('BDb', $db);
        $db = BDb::i();
        $this->assertInstanceOf('BDb', $db);
    }

    /**
     * @covers BDb::connect
     */
    public function testNoNameConnectionIsDefaultConnection()
    {
        BDbDouble::resetConnections();
        $db          = BDbDouble::i(true);
        $connNoName  = $db->connect();
        $connDefault = $db->connect('DEFAULT');

        $this->assertSame($connDefault, $connNoName);
    }


    /**
     * @covers BDb::connect
     */
    public function testEmptyConfigWillThrowBException()
    {
        BConfig::i()->unsetConfig();

        $this->setExpectedException('BException');

        BDb::i(true)->connect('bogus');
    }

    /**
     * @covers BDb::connect
     */
    public function testWhenUseClauseIsPresentShouldSetCurrentConnectionToUse()
    {

        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );

        BConfig::i()->set('db/named/test/use', 'DEFAULT');

        $connOne = BDb::connect('test');
        $connTwo = BDb::connect('DEFAULT');

        $this->assertSame($connOne, $connTwo);

        BConfig::i()->set('db/named/test/use', null);
    }

    /**
     * @covers BDb::connect
     */
    public function testSwitchingConnections()
    {
        BDbDouble::resetConnections();
        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );


        $connOne = BDbDouble::connect('test');
        $connTwo = BDbDouble::connect('DEFAULT');
        $connThree = BDbDouble::connect('test');
        $connFour = BDbDouble::connect('DEFAULT');

        $this->assertSame($connOne, $connThree);
        $this->assertSame($connTwo, $connFour);
    }

    /**
     * @covers BDb::connect
     */
    public function testConnectWithoutDbnameThrowsException()
    {
        BDbDouble::resetConnections();
        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );

        BConfig::i()->set('db/named/test/dbname', null);
        $this->setExpectedException('BException');
        BDb::connect('test');
    }

    /**
     * @covers BDb::connect
     */
    public function testConnectWithNonSupportedDbEngineThrowsException()
    {
        BDbDouble::resetConnections();
        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );

        BConfig::i()->set('db/named/test/engine', 'pgsql');
        $this->setExpectedException('BException');
        BDb::connect('test');
    }

    /**
     * @covers BDb::connect
     */
    public function testConnectWithDsn()
    {

        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );

        BConfig::i()->set('db/named/test/dbname', null);
        BConfig::i()->set('db/named/test/dsn', "mysql:host=localhost;dbname=fulleron_test;charset=UTF8");
        $this->assertInstanceOf('BPDO', BDb::connect('test'));
    }

    /**
     * @covers BDb::connect
     */
    public function testConnectingMoreThanOnceWithSameNameReturnsSameConnection()
    {
        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );

        $connOne = BDb::connect('test');
        $connTwo = BDb::connect('test');

        $this->assertSame($connOne, $connTwo);
    }

    /**
     * @covers BDb::connect
     */
    public function testGetConnectionObject()
    {
        $conn = BDb::connect();
        $this->assertInstanceOf('PDO', $conn);
    }

    /**
     * @covers BDb::now
     */
    public function testNow()
    {
        $now = date('Y-m-d H:i:s');

        $this->assertEquals($now, BDb::now());
    }

    /**
     * @covers BDb::run
     */
    public function testRunReturnsArrayAndIsNotEmpty()
    {
        BDbDouble::resetConnections();
        $db = BDbDouble::i();
        $sql = "CREATE TABLE IF NOT EXISTS `test_table_2`(
        id int(10) not null auto_increment primary key,
        test varchar(100) null
        )";
        $result = $db->run($sql);

        $this->assertTrue(is_array($result));
        $this->assertNotEmpty($result);
    }

    /**
     * @covers BDb::run
     */
    public function testRunQueryWithParamsReturnsAffectedRows()
    {
        BDbDouble::resetConnections();
        $db = BDbDouble::i();
        $query = "INSERT INTO `test_table_2`(test) VALUES(?),(?)";
        $result = $db->run($query, array(2,3));

        $this->assertTrue($result[0]);
    }

    /**
     * @covers BDb::run
     */
    public function testRunOptionEchoEchoesOutput()
    {
        BDbDouble::resetConnections();
        $db = BDbDouble::i();
        $query = "DROP TABLE IF EXISTS `test_table_2`";
        ob_start();
        $db->run($query, null, array('echo' => true));
        $echo = ob_get_clean();

        $this->assertEquals('<hr><pre>' . $query . '<pre>', $echo);
    }

    /**
     * @covers BDb::run
     */
    public function testRunExceptionMessageEchoed()
    {
        BDbDouble::resetConnections();
        $db = BDbDouble::i();
        $query = "DROP TABLE IF EXISTS ";
        ob_start();
        $db->run($query, null, array('try' => true));
        $echo = ob_get_clean();
        $this->assertContains('<hr>', $echo);
    }

    /**
     * @covers BDb::run
     */
    public function testRunExceptionReThrown()
    {
        BDbDouble::resetConnections();
        $db = BDbDouble::i();
        $query = "DROP TABLE IF EXISTS ";
        ob_start();
        $this->setExpectedException('Exception');
        $echo = ob_get_clean();
        $db->run($query);
        $this->assertContains('<hr>', $echo);
    }

    /**
     * @covers BDb::transaction
     */
    public function testTransactionPutsDbObjectInTransaction()
    {
        BDb::transaction();
        $this->assertTrue(BORM::get_db()->inTransaction() == 1);
    }

    /**
     * @covers BDb::transaction
     */
    public function testTransactionPutsDbObjectInTransactionWithConnection()
    {
        BDb::transaction('test');
        $this->assertTrue(BORM::get_db()->inTransaction() == 1);
    }

    /**
     * @covers BDb::commit
     */
    public function testCommitWithConnection()
    {
        BDb::commit('test');
        $this->assertTrue(BORM::get_db()->inTransaction() == 0);
    }

    /**
     * @covers BDb::transaction
     * @covers BDb::rollback
     * @covers BDb::run
     */
    public function testRollBackInTransaction()
    {
        BDb::transaction('test');

        $sql = "CREATE TABLE IF NOT EXISTS `test_table_2`(
        id int(10) not null auto_increment primary key,
        test varchar(100) null
        )";
        BDb::run($sql);
        $query = "INSERT INTO `test_table_2`(test) VALUES(?),(?)";
        BDb::run($query, array(2,3));
        BDb::rollback('test');
        $this->assertTrue(BORM::get_db()->inTransaction() == 0);
    }

    /**
     * @covers BDb::cleanForTable
     * @covers BDb::run
     */
    public function testCleanForTableReturnsCorrectArray()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `test_table_2`(
        id int(10) not null auto_increment primary key,
        test varchar(100) null
        )";
        BDb::run($sql);

        $orig = array(
            'id' => 1,
            'test' => 'test'
        );

        $dirty = $orig + array('test2' => 'test', 2,3,4);
        $result = BDb::cleanForTable('test_table_2', $dirty);

        $this->assertEquals($orig, $result);
    }

    /**
     * @covers BDb::transaction
     * @covers BDb::commit
     */
    public function testCommitAfterTransaction()
    {
        $this->markTestSkipped("Todo: implement run tests");
        BDb::transaction();
        /* @var PDO $conn */
        $conn = BORM::get_db();
        $this->assertTrue($conn->inTransaction() == 1);
        BDb::commit();
        $this->assertFalse($conn->inTransaction() == 0);
    }

    protected $dbConfig = array(
        'host'         => 'localhost',
        'dbname'       => 'fulleron_test',
        'username'     => 'pp',
        'password'     => '111111',
        'table_prefix' => null,
    );

    /**
     * @covers BDb::connect
     * @covers BDb::t
     */
    public function testGetTableNameWithoutPrefix()
    {
        $table = 'test_table';
        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );
        BDb::connect('test');
        $this->assertEquals($table, BDb::t($table));
    }

    /**
     * @covers BDb::connect
     * @covers BDb::t
     */
    public function testGetTableNameWithoutPrefixFromInstance()
    {
        $table = 'test_table';
        BConfig::i()->add(
            array(
                 'db' => array(
                     'named' => array(
                         'test' => $this->dbConfig
                     )
                 )
            )
        );
        $dbi = BDb::i(true);
        $dbi->connect('test');
        $this->assertEquals($table, $dbi->t($table));
    }

    /**
     * @covers BDb::connect
     * @covers BDb::t
     */
    public function testGetTableNameWithPrefix()
    {
        $table  = 'test_table';
        $config = BConfig::i()->get('db');
        $this->assertEquals($config['table_prefix'] . $table, BDb::t($table));
    }

    /**
     * @covers BDb::connect
     * @covers BDb::t
     */
    public function testGetTableNameWithPrefixFromInstance()
    {
        BDbDouble::resetConnections();
        $table = 'test_table';
        $dbi   = BDbDouble::i(true);
        BConfig::i()->add(
            array(
                 'db' => array(
                     'table_prefix' => 'fl',
                 ),
            )
        );
        $config = BConfig::i()->get('db');
        BDbDouble::connect('DEFAULT');
        $this->assertEquals($config['table_prefix'] . $table, $dbi->t($table));
    }

    /**
     * @covers BDb::where
     */
    public function testVariousWhereConditions()
    {
        $expected       = "f1 is null";
        $expectedParams = array();
        $w              = BDb::where("f1 is null");
        $this->assertEquals($expected, $w[0]);
        $this->assertEquals($expectedParams, $w[1]);

        $expected       = "(f1=?) AND (f2=?)";
        $expectedParams = array('V1', 'V2');
        $w              = BDb::where(array('f1' => 'V1', 'f2' => 'V2'));
        $this->assertEquals($expected, $w[0]);
        $this->assertEquals($expectedParams, $w[1]);

        $expected       = "(f1=?) AND (f2 LIKE ?)";
        $expectedParams = array(5, '%text%');
        $w              = BDb::where(array('f1' => 5, array('f2 LIKE ?', '%text%')));
        $this->assertEquals($expected, $w[0]);
        $this->assertEquals($expectedParams, $w[1]);

        $expected       = "((f1!=?) OR (f2 BETWEEN ? AND ?))";
        $expectedParams = array(5, 10, 20);
        $w              = BDb::where(array('OR' => array(array('f1!=?', 5), array('f2 BETWEEN ? AND ?', 10, 20))));
        $this->assertEquals($expected, $w[0]);
        $this->assertEquals($expectedParams, $w[1]);

        $expected       = "(f1 IN (?,?,?)) AND NOT (((f2 IS NULL) OR (f2=?)))";
        $expectedParams = array(1, 2, 3, 10);
        $w              = BDb::where(
            array(
                 'f1'  => array(1, 2, 3),
                 'NOT' => array('OR' => array("f2 IS NULL", 'f2' => 10))
            )
        );
        $this->assertEquals($expected, $w[0]);
        $this->assertEquals($expectedParams, $w[1]);

        $expected       = "(((A) OR (B)) AND ((C) OR (D)))";
        $expectedParams = array();
        $w              = BDb::where(
            array(
                 'AND' => array(
                     array('OR', 'A', 'B'), array('OR', 'C', 'D')
                 )
            )
        );
        $this->assertEquals($expected, $w[0]);
        $this->assertEquals($expectedParams, $w[1]);
    }

    /**
     * @covers BDb::dbName
     */
    public function testGetDbName()
    {
        $db     = BDb::i(true);
        $dbName = $db->dbName();
        $this->assertNotNull($dbName);
    }

    // todo, there is no way currently to reset BDb config so exception condition cannot be tested.
    /**
     * @covers BORM::i
     * @covers BORM::raw_query
     * @covers BORM::execute
     */
    public function testBORMRawQuery()
    {
        $result = BORM::i()->raw_query("SHOW TABLES")->execute()->fetchAll();

        $this->assertTrue(is_array($result));
        $this->assertNotEmpty($result);
    }

    /**
     * @covers BDb::ddlTableDef
     * @covers BDb::ddlTableExists
     */
    public function testDdlTableDefCanCreateTable()
    {
        BDbDouble::resetConnections();
        $db = BDbDouble::i();
        BDbDouble::connect();
        $vo = new Entity();
        $this->assertFalse($db->ddlTableExists($vo->table));
        $this->assertFalse($db->ddlTableExists('fulleron_test.' . $vo->table));

        $db->ddlTableDef($vo->table, $vo->tableFields);

        $this->assertTrue($db->ddlTableExists($vo->table));
        $this->assertTrue($db->ddlTableExists('fulleron_test.' . $vo->table));
    }

    /**
     * @covers BDb::ddlTableExists
     * @covers BDb::ddlTableDef
     */
    public function testDdlCanCheckTableExists()
    {
        $db = BDb::i();
        $vo = new Entity();
        if (!$db->ddlTableExists($vo->table)) {
            $db->ddlTableDef($vo->table, $vo->tableFields);
        }
        $this->assertTrue($db->ddlTableExists($vo->table));
        $this->assertTrue($db->ddlTableExists('fulleron_test.' . $vo->table));
    }

    /**
     * @covers BDb::ddlTableDef
     */
    public function testDdlTableDefNewTableWithNoFieldsShouldThrowException()
    {
        BDbDouble::resetConnections();
        BDbDouble::connect();
        $this->setExpectedException('BException', 'Missing fields definition for new table');
        BDbDouble::ddlTableDef('non_existing', array());
    }

    /**
     * @covers BDb::ddlTableDef
     * @covers BDb::ddlTableExists
     * @covers BDb::ddlFieldInfo
     * @covers BDb::ddlIndexInfo
     */
    public function testDdlCanDropColumnAndIndex()
    {
        $db = BDb::i();

        $vo = new Entity();
        BDbDouble::resetConnections();
        if (!$db->ddlTableExists($vo->table)) {
            $db->ddlTableDef($vo->table, $vo->tableFields);
        }
        $update = array(
            'COLUMNS' => array(
                'test_char' => 'DROP'
            ),
            'KEYS'    => array(
                'test_char' => 'DROP'
            ),
        );

        $db->ddlTableDef($vo->table, $update);

        $columns = $db->ddlFieldInfo($vo->table);
        $indexes = $db->ddlIndexInfo($vo->table);

        $this->assertNotContains('test_char', array_keys($columns));
        $this->assertNotContains('test_char', array_keys($indexes));
    }

    /**
     * @covers BDb::ddlTableDef
     * @covers BDb::ddlTableExists
     */
    public function testDdlTableDefForeignKeyCreation()
    {
        $db = BDb::i();
        $vo = new Entity();
        BDbDouble::resetConnections();
        if (!$db->ddlTableExists($vo->table)) {
            $db->ddlTableDef($vo->table, $vo->tableFields);
        }

        $db->ddlTableDef($vo->fTable, $vo->fkTableFields);
        $db->ddlClearCache($vo->fTable);
        $this->assertTrue($db->ddlTableExists($vo->fTable));
        $fks = $db->ddlForeignKeyInfo($vo->fTable);
        $this->assertNotContains('Ffk_test_fk_idK', $fks);
        $db->ddlTableColumns($vo->fTable, null, null, $vo->constraints);
        $fks = $db->ddlForeignKeyInfo($vo->fTable);
        $this->assertArrayHasKey('Ffk_test_fk_idK', $fks);
    }

    /**
     * @covers BDb::many_as_array
     * @covers BDb::run
     * @covers ORMWrapper::for_table
     * @covers BORM::select
     * @covers ORMWrapper::limit
     * @covers BORM::find_many
     */
    public function testFindManyAsArray()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `test_table_2`(
        id int(10) not null auto_increment primary key,
        test varchar(100) null
        )";
        BDb::run($sql);
        $query = "INSERT INTO `test_table_2`(test) VALUES(?),(?)";
        BDb::run($query, array(2,3));

        $result = BORM::i()->for_table('test_table_2')->select('test')->limit(2)->find_many();

        $asArray = BDb::many_as_array($result);

        $this->assertTrue(is_array($asArray));
        $this->assertTrue(count($asArray) == 2);
    }
}

/**
 * Class BDbDouble
 * Convenience helper to test BDb
 */
class BDbDouble extends BDb
{
    public static function resetConnections()
    {
        static::$_currentConnectionName = null;
        static::$_config                = array('dbname' => null);
        static::$_namedConnections      = array();
        static::$_namedConnectionConfig = array();
    }
}

/**
 * Class Entity
 * Value object helper
 */
class Entity
{
    public $table = 'test_table';
    public $fTable = 'fk_test_table';

    public $tableFields = array(
        'COLUMNS'     => array(
            'test_id'      => 'int(10) not null',
            'test_fk_id'   => 'int(10) not null',
            'test_varchar' => 'varchar(100) null',
            'test_char'    => 'char(10) null',
            'test_text'    => 'text null',
        ),
        'PRIMARY'     => '(test_id)', // at the moment this HAS to be IN parenthesis or wrong SQL is generated
        'KEYS'        => array(
            'test_id'      => 'PRIMARY(test_id)',
            'test_fk_id'   => 'UNIQUE(test_fk_id)',
            'test_char'    => '(test_char)',
            'test_varchar' => '(test_varchar)',
        ),
        'CONSTRAINTS' => array(),
//        'OPTIONS' => array(
//            'engine' => 'InnoDB',
//            'charset' => 'latin1',
//            'collate' => 'latin1',// what will happen if they do not match?
//        ),
    );
    public $fkTableFields = array(
        'COLUMNS'     => array(
            'fk_test_id'    => 'int(10) not null',
            'fk_test_fk_id' => 'int(10) not null',
        ),
        'PRIMARY'     => '(fk_test_id)',
        'KEYS'        => array(
            'fk_test_id'    => 'PRIMARY(fk_test_id)',
            'fk_test_fk_id' => 'UNIQUE(fk_test_fk_id)',
        ),
//        'OPTIONS'     => array(
//            'engine'  => 'InnoDB',
//            'charset' => 'latin1',
//            'collate' => 'latin1_swedish_ci', // what will happen if they do not match?
//        ),
    );

    public $constraints = array(
        'Ffk_test_fk_idK' => 'FOREIGN KEY (fk_test_fk_id) REFERENCES test_table(test_fk_id) ON DELETE CASCADE ON UPDATE CASCADE'
    );
}

/*
 * CREATE TABLE test_table
(
test_id int(10) not null,
test_fk_id int(10) not null,
test_varchar varchar(100) null,
test_char char(10) null,
test_text text null,
PRIMARY KEY test_id
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci

ALTER TABLE test_table DROP PRIMARY KEY, ADD PRIMARY KEY `test_id` (test_id), ADD UNIQUE KEY `test_fk_id` (test_fk_id), ADD KEY `test_char` ("test_char"), ADD KEY `test_varchar` ("test_varchar")
 */