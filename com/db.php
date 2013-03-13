<?php
/**
* Copyright 2011 Unirgy LLC
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*
* @package BuckyBall
* @link http://github.com/unirgy/buckyball
* @author Boris Gurvich <boris@unirgy.com>
* @copyright (c) 2010-2012 Boris Gurvich
* @license http://www.apache.org/licenses/LICENSE-2.0.html
*/

/**
* Wrapper for idiorm/paris
*
* @see http://j4mie.github.com/idiormandparis/
*/
class BDb
{
    /**
    * Collection of cached named DB connections
    *
    * @var array
    */
    protected static $_namedConnections = array();

    /**
    * Necessary configuration for each DB connection name
    *
    * @var array
    */
    protected static $_namedConnectionConfig = array();

    /**
    * Default DB connection name
    *
    * @var string
    */
    protected static $_defaultConnectionName = 'DEFAULT';

    /**
    * DB name which is currently referenced in BORM::$_db
    *
    * @var string
    */
    protected static $_currentConnectionName;

    /**
    * Current DB configuration
    *
    * @var array
    */
    protected static $_config = array('table_prefix'=>'');

    /**
    * List of tables per connection
    *
    * @var array
    */
    protected static $_tables = array();

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BDb
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Connect to DB using default or a named connection from global configuration
    *
    * Connections are cached for reuse when switching.
    *
    * Structure in configuration:
    *
    * {
    *   db: {
    *     dsn: 'mysql:host=127.0.0.1;dbname=buckyball',  - optional: replaces engine, host, dbname
    *     engine: 'mysql',                               - optional if dsn exists, default: mysql
    *     host: '127.0.0.1',                             - optional if dsn exists, default: 127.0.0.1
    *     dbname: 'buckyball',                           - optional if dsn exists, required otherwise
    *     username: 'dbuser',                            - default: root
    *     password: 'password',                          - default: (empty)
    *     logging: false,                                - default: false
    *     named: {
    *       read: {<db-connection-structure>},           - same structure as default connection
    *       write: {
    *         use: 'read'                                - optional, reuse another connection
    *       }
    *     }
    *  }
    *
    * @param string $name
    */
    public static function connect($name=null)
    {
        if (!$name && static::$_currentConnectionName) { // continue connection to current db, if no value
            return BORM::get_db();
        }
        if (is_null($name)) { // if first time connection, connect to default db
            $name = static::$_defaultConnectionName;
        }
        if ($name===static::$_currentConnectionName) { // if currently connected to requested db, return
            return BORM::get_db();
        }
        if (!empty(static::$_namedConnections[$name])) { // if connection already exists, switch to it
            BDebug::debug('DB.SWITCH '.$name);
            static::$_currentConnectionName = $name;
            static::$_config = static::$_namedConnectionConfig[$name];
            BORM::set_db(static::$_namedConnections[$name], static::$_config);
            return BORM::get_db();
        }
        $config = BConfig::i()->get($name===static::$_defaultConnectionName ? 'db' : 'db/named/'.$name);
        if (!$config) {
            throw new BException(BLocale::_('Invalid or missing DB configuration: %s', $name));
        }
        if (!empty($config['use'])) { //TODO: Prevent circular reference
            static::connect($config['use']);
            return;
        }
        if (!empty($config['dsn'])) {
            $dsn = $config['dsn'];
            if (empty($config['dbname']) && preg_match('#dbname=(.*?)(;|$)#', $dsn, $m)) {
                $config['dbname'] = $m[1];
            }
        } else {
            if (empty($config['dbname'])) {
                throw new BException(BLocale::_("dbname configuration value is required for '%s'", $name));
            }
            $engine = !empty($config['engine']) ? $config['engine'] : 'mysql';
            $host = !empty($config['host']) ? $config['host'] : '127.0.0.1';
            switch ($engine) {
                case "mysql":
                    $dsn = "mysql:host={$host};dbname={$config['dbname']};charset=UTF8";
                    break;

                default:
                    throw new BException(BLocale::_('Invalid DB engine: %s', $engine));
            }
        }
        $profile = BDebug::debug('DB.CONNECT '.$name);
        static::$_currentConnectionName = $name;

        BORM::configure($dsn);
        BORM::configure('username', !empty($config['username']) ? $config['username'] : 'root');
        BORM::configure('password', !empty($config['password']) ? $config['password'] : '');
        BORM::configure('logging', !empty($config['logging']));
        BORM::set_db(null);
        BORM::setup_db();
        static::$_namedConnections[$name] = BORM::get_db();
        static::$_config = static::$_namedConnectionConfig[$name] = array(
            'dbname' => !empty($config['dbname']) ? $config['dbname'] : null,
            'table_prefix' => !empty($config['table_prefix']) ? $config['table_prefix'] : '',
        );

        $db = BORM::get_db();
        BDebug::profile($profile);
        return $db;
    }

    /**
    * DB friendly current date/time
    *
    * @return string
    */
    public static function now()
    {
        return gmstrftime('%Y-%m-%d %H:%M:%S');
    }

    /**
    * Shortcut to run multiple queries from migrate scripts
    *
    * It doesn't make sense to run multiple queries in the same call and use $params
    *
    * @param string $sql
    * @param array $params
    * @param array $options
    *   - echo - echo all queries as they run
    */
    public static function run($sql, $params=null, $options=array())
    {
        BDb::connect();
        $queries = preg_split("/;+(?=([^'|^\\\']*['|\\\'][^'|^\\\']*['|\\\'])*[^'|^\\\']*[^'|^\\\']$)/", $sql);
        $results = array();
        foreach ($queries as $i=>$query){
           if (strlen(trim($query)) > 0) {
                try {
                    BDebug::debug('DB.RUN: '.$query);
                    if (!empty($options['echo'])) {
                        echo '<hr><pre>'.$query.'<pre>';
                    }
                    if (is_null($params)) {
                        $results[] = BORM::get_db()->exec($query);
                    } else {
                        $results[] = BORM::get_db()->prepare($query)->execute($params);
                    }
                } catch (Exception $e) {
                    echo "<hr>{$e->getMessage()}: <pre>{$query}</pre>";
                    if (empty($options['try'])) {
                        throw $e;
                    }
                }
           }
        }
        return $results;
    }

    /**
    * Start transaction
    *
    * @param string $connectionName
    */
    public static function transaction($connectionName=null)
    {
        if (!is_null($connectionName)) {
            BDb::connect($connectionName);
        }
        BORM::get_db()->beginTransaction();
    }

    /**
    * Commit transaction
    *
    * @param string $connectionName
    */
    public static function commit($connectionName=null)
    {
        if (!is_null($connectionName)) {
            BDb::connect($connectionName);
        }
        BORM::get_db()->commit();
    }

    /**
    * Rollback transaction
    *
    * @param string $connectionName
    */
    public static function rollback($connectionName=null)
    {
        if (!is_null($connectionName)) {
            BDb::connect($connectionName);
        }
        BORM::get_db()->rollback();
    }

    /**
    * Get db specific table name with pre-configured prefix for current connection
    *
    * Can be used as both BDb::t() and $this->t() within migration script
    * Convenient within strings and heredocs as {$this->t(...)}
    *
    * @param string $tableName
    */
    public static function t($tableName)
    {
        $a = explode('.', $tableName);
        $p = static::$_config['table_prefix'];
        return !empty($a[1]) ? $a[0].'.'.$p.$a[1] : $p.$a[0];
    }

    /**
    * Convert array collection of objects from find_many result to arrays
    *
    * @param array $rows result of ORM::find_many()
    * @param string $method default 'as_array'
    * @param array|string $fields if specified, return only these fields
    * @param boolean $maskInverse if true, do not return specified fields
    * @return array
    */
    public static function many_as_array($rows, $method='as_array', $fields=null, $maskInverse=false)
    {
        $res = array();
        foreach ((array)$rows as $i=>$r) {
            if (!$r instanceof BModel) {
                echo "Rows are not models: <pre>"; print_r($r);
                debug_print_backtrace();
                exit;
            }
            $row = $r->$method();
            if (!is_null($fields)) $row = BUtil::maskFields($row, $fields, $maskInverse);
            $res[$i] = $row;
        }
        return $res;
    }

    /**
    * Construct where statement (for delete or update)
    *
    * Examples:
    * $w = BDb::where("f1 is null");
    *
    * // (f1='V1') AND (f2='V2')
    * $w = BDb::where(array('f1'=>'V1', 'f2'=>'V2'));
    *
    * // (f1=5) AND (f2 LIKE '%text%'):
    * $w = BDb::where(array('f1'=>5, array('f2 LIKE ?', '%text%')));
    *
    * // (f1!=5) OR f2 BETWEEN 10 AND 20:
    * $w = BDb::where(array('OR'=>array(array('f1!=?', 5), array('f2 BETWEEN ? AND ?', 10, 20))));
    *
    * // (f1 IN (1,2,3)) AND NOT ((f2 IS NULL) OR (f2=10))
    * $w = BDb::where(array('f1'=>array(1,2,3)), 'NOT'=>array('OR'=>array("f2 IS NULL", 'f2'=>10)));
    *
    * // ((A OR B) AND (C OR D))
    * $w = BDb::where(array('AND', array('OR', 'A', 'B'), array('OR', 'C', 'D')));
    *
    * @param array $conds
    * @param boolean $or
    * @return array (query, params)
    */
    public static function where($conds, $or=false)
    {
        if (is_string($conds)) {
            return array($conds, array());
        }
        $where = array();
        $params = array();
        if (is_array($conds)) {
            foreach ($conds as $f=>$v) {
                if (is_int($f)) {
                    if (is_string($v)) { // freeform
                        $where[] = '('.$v.')';
                        continue;
                    }
                    if (is_array($v)) { // [freeform|arguments]
                        $sql = array_shift($v);
                        if ('AND'===$sql || 'OR'===$sql || 'NOT'===$sql) {
                            $f = $sql;
                        } else {
                            if (isset($v[0]) && is_array($v[0])) { // `field` IN (?)
                                $v = $v[0];
                                $sql = str_replace('(?)', "(".str_pad('', sizeof($v)*2-1, '?,')."))", $sql);
                            }
                            $where[] = '('.$sql.')';
                            $params = array_merge($params, $v);
                            continue;
                        }
                    } else {
                        throw new BException('Invalid token: '.print_r($v,1));
                    }
                }
                if ('AND'===$f) {
                    list($w, $p) = static::where($v);
                    $where[] = '('.$w.')';
                    $params = array_merge($params, $p);
                } elseif ('OR'===$f) {
                    list($w, $p) = static::where($v, true);
                    $where[] = '('.$w.')';
                    $params = array_merge($params, $p);
                } elseif ('NOT'===$f) {
                    list($w, $p) = static::where($v);
                    $where[] = 'NOT ('.$w.')';
                    $params = array_merge($params, $p);
                } elseif (is_array($v)) {
                    $where[] = "({$f} IN (".str_pad('', sizeof($v)*2-1, '?,')."))";
                    $params = array_merge($params, $v);
                } elseif (is_null($v)) {
                    $where[] = "({$f} IS NULL)";
                } else {
                    $where[] = "({$f}=?)";
                    $params[] = $v;
                }
            }
#print_r($where); print_r($params);
            return array(join($or ? " OR " : " AND ", $where), $params);
        }
        throw new BException("Invalid where parameter");
    }

    /**
    * Get database name for current connection
    *
    */
    public static function dbName()
    {
        if (!static::$_config) {
            throw new BException('No connection selected');
        }
        return !empty(static::$_config['dbname']) ? static::$_config['dbname'] : null;
    }

    public static function ddlStart()
    {
        BDb::run(<<<EOT
/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
EOT
        );
    }

    public static function ddlFinish()
    {
        BDb::run(<<<EOT
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
EOT
        );
    }

    /**
    * Clear DDL cache
    *
    */
    public static function ddlClearCache($fullTableName=null)
    {
        if ($fullTableName) {
            if (!static::dbName()) {
                static::connect(static::$_defaultConnectionName);
            }
            $a = explode('.', $fullTableName);
            $dbName = empty($a[1]) ? static::dbName() : $a[0];
            $tableName = empty($a[1]) ? $fullTableName : $a[1];
            static::$_tables[$dbName][$tableName] = null;
        } else {
            static::$_tables = array();
        }
    }

    /**
    * Check whether table exists
    *
    * @param string $fullTableName
    * @return BDb
    */
    public static function ddlTableExists($fullTableName)
    {
        if (!static::dbName()) {
            static::connect(static::$_defaultConnectionName);
        }
        $a = explode('.', $fullTableName);
        $dbName = empty($a[1]) ? static::dbName() : $a[0];
        $tableName = empty($a[1]) ? $fullTableName : $a[1];
        if (!isset(static::$_tables[$dbName])) {
            $tables = BORM::i()->raw_query("SHOW TABLES FROM `{$dbName}`", array())->find_many();
            $field = "Tables_in_{$dbName}";
            foreach ($tables as $t) {
                static::$_tables[$dbName][$t->$field] = array();
            }
        }
        return isset(static::$_tables[$dbName][$tableName]);
    }

    /**
    * Get table field info
    *
    * @param string $fullTableName
    * @param string $fieldName if null return all fields
    * @return mixed
    */
    public static function ddlFieldInfo($fullTableName, $fieldName=null)
    {
        if (!static::ddlTableExists($fullTableName)) {
            throw new BException(BLocale::_('Invalid table name: %s', $fullTableName));
        }
        $a = explode('.', $fullTableName);
        $dbName = empty($a[1]) ? static::dbName() : $a[0];
        $tableName = empty($a[1]) ? $fullTableName : $a[1];
        if (!isset(static::$_tables[$dbName][$tableName]['fields'])) {
            static::$_tables[$dbName][$tableName]['fields'] = BORM::i()
                ->raw_query("SHOW FIELDS FROM `{$dbName}`.`{$tableName}`", array())->find_many_assoc('Field');

        }
        $res = static::$_tables[$dbName][$tableName]['fields'];
        return is_null($fieldName) ? $res : (isset($res[$fieldName]) ? $res[$fieldName] : null);
    }

    /**
    * Retrieve table index(es) info, if exist
    *
    * @param string $fullTableName
    * @param string $indexName
    * @return array|null
    */
    public static function ddlIndexInfo($fullTableName, $indexName=null)
    {
        if (!static::ddlTableExists($fullTableName)) {
            throw new BException(BLocale::_('Invalid table name: %s', $fullTableName));
        }
        $a = explode('.', $fullTableName);
        $dbName = empty($a[1]) ? static::dbName() : $a[0];
        $tableName = empty($a[1]) ? $fullTableName : $a[1];
        if (!isset(static::$_tables[$dbName][$tableName]['indexes'])) {
            static::$_tables[$dbName][$tableName]['indexes'] = BORM::i()
                ->raw_query("SHOW KEYS FROM `{$dbName}`.`{$tableName}`", array())->find_many_assoc('Key_name');
        }
        $res = static::$_tables[$dbName][$tableName]['indexes'];
        return is_null($indexName) ? $res : (isset($res[$indexName]) ? $res[$indexName] : null);
    }

    /**
    * Retrieve table foreign key(s) info, if exist
    *
    * Mysql/InnoDB specific
    *
    * @param string $fullTableName
    * @param string $fkName
    * @result array|null
    */
    public static function ddlForeignKeyInfo($fullTableName, $fkName=null)
    {
        if (!static::ddlTableExists($fullTableName)) {
            throw new BException(BLocale::_('Invalid table name: %s', $fullTableName));
        }
        $a = explode('.', $fullTableName);
        $dbName = empty($a[1]) ? static::dbName() : $a[0];
        $tableName = empty($a[1]) ? $fullTableName : $a[1];
        if (!isset(static::$_tables[$dbName][$tableName]['fks'])) {
            static::$_tables[$dbName][$tableName]['fks'] = BORM::i()
                ->raw_query("SELECT * FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA='{$dbName}' AND TABLE_NAME='{$tableName}'
                        AND CONSTRAINT_TYPE='FOREIGN KEY'", array())->find_many_assoc('CONSTRAINT_NAME');
        }
        $res = static::$_tables[$dbName][$tableName]['fks'];
        return is_null($fkName) ? $res : (isset($res[$fkName]) ? $res[$fkName] : null);
    }
    
    /**
    * Create or update table
    * 
    * @deprecates ddlTable and ddlTableColumns
    * @param string $fullTableName
    * @param array $def
    */
    public static function ddlTableDef($fullTableName, $def)
    {
        $fields = !empty($def['COLUMNS']) ? $def['COLUMNS'] : null;
        $primary = !empty($def['PRIMARY']) ? $def['PRIMARY'] : null;
        $indexes = !empty($def['KEYS']) ? $def['KEYS'] : null;
        $fks = !empty($def['CONSTRAINTS']) ? $def['CONSTRAINTS'] : null;
        $options = !empty($def['OPTIONS']) ? $def['OPTIONS'] : null;
        
        if (!static::ddlTableExists($fullTableName)) {
            if (!$fields) {
                throw new BException('Missing fields definition for new table');
            }
            // temporary code duplication with ddlTable, until the other one is removed
            $fieldsArr = array();
            foreach ($fields as $f=>$def) {
                $fieldsArr[] = $f.' '.$def;
            }
            $fields = null; // reset before update step
            if ($primary) {
                $fieldsArr[] = "PRIMARY KEY ".$primary;
                $primary = null; // reset before update step
            }
            $engine = !empty($options['engine']) ? $options['engine'] : 'InnoDB';
            $charset = !empty($options['charset']) ? $options['charset'] : 'utf8';
            $collate = !empty($options['collate']) ? $options['collate'] : 'utf8_general_ci';
            BORM::i()->raw_query("CREATE TABLE {$fullTableName} (".join(', ', $fieldsArr).")
                ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate}", array())->execute();
            static::ddlClearCache();
        }
        return static::ddlTableColumns($fullTableName, $fields, $indexes, $fks, $options);
    }

    /**
    * Create or update table
    *
    * @param string $fullTableName
    * @param array $fields
    * @param array $options
    *   - engine (default InnoDB)
    *   - charset (default utf8)
    *   - collate (default utf8_general_ci)
    */
    public static function ddlTable($fullTableName, $fields, $options=null)
    {
        if (static::ddlTableExists($fullTableName)) {
            static::ddlTableColumns($fullTableName, $fields, null, null, $options); // altering options is not implemented
        } else {
            $fieldsArr = array();
            foreach ($fields as $f=>$def) {
                $fieldsArr[] = $f.' '.$def;
            }
            if (!empty($options['primary'])) {
                $fieldsArr[] = "PRIMARY KEY ".$options['primary'];
            }
            $engine = !empty($options['engine']) ? $options['engine'] : 'InnoDB';
            $charset = !empty($options['charset']) ? $options['charset'] : 'utf8';
            $collate = !empty($options['collate']) ? $options['collate'] : 'utf8_general_ci';
            BORM::i()->raw_query("CREATE TABLE {$fullTableName} (".join(', ', $fieldsArr).")
                ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collate}", array())->execute();
            static::ddlClearCache();
        }
        return true;
    }

    /**
    * Add or change table columns
    *
    * BDb::ddlTableColumns('my_table', array(
    *   'field_to_create' => 'varchar(255) not null',
    *   'field_to_update' => 'decimal(12,4) null',
    *   'field_to_drop'   => 'DROP',
    * ));
    *
    * @param string $fullTableName
    * @param array $fields
    * @param array $indexes
    * @param array $fks
    * @return array
    */
    public static function ddlTableColumns($fullTableName, $fields, $indexes=null, $fks=null)
    {
        $tableFields = static::ddlFieldInfo($fullTableName, null);
        $alterArr = array();
        if ($fields) {
            foreach ($fields as $f=>$def) {
                if ($def==='DROP') {
                    if (!empty($tableFields[$f])) {
                        $alterArr[] = "DROP `{$f}`";
                    }
                } elseif (empty($tableFields[$f])) {
                    $alterArr[] = "ADD `{$f}` {$def}";
                } else {
                    $alterArr[] = "CHANGE `{$f}` `{$f}` {$def}";
                }
            }
        }
        if ($indexes) {
            $tableIndexes = static::ddlIndexInfo($fullTableName);
            foreach ($indexes as $idx=>$def) {
                if ($def==='DROP') {
                    if (!empty($tableIndexes[$idx])) {
                        $alterArr[] = "DROP KEY `{$idx}`";
                    }
                } else {
                    if (!empty($tableIndexes[$idx])) {
                        $alterArr[] = "DROP KEY `{$idx}`";
                    }
                    if (strpos($def, 'PRIMARY')===0) {
                        $alterArr[] = "DROP PRIMARY KEY";
                        $def = substr($def, 7);
                        $alterArr[] = "ADD PRIMARY KEY `{$idx}` {$def}";
                    } elseif (strpos($def, 'UNIQUE')===0) {
                        $def = substr($def, 6);
                        $alterArr[] = "ADD UNIQUE KEY `{$idx}` {$def}";
                    } else {
                        $alterArr[] = "ADD KEY `{$idx}` {$def}";
                    }
                }
            }
        }
        if ($fks) {
            $tableFKs = static::ddlForeignKeyInfo($fullTableName);
            // @see http://dev.mysql.com/doc/refman/5.5/en/innodb-foreign-key-constraints.html
            // You cannot add a foreign key and drop a foreign key in separate clauses of a single ALTER TABLE statement.
            // Separate statements are required.
            $dropArr = array();
            foreach ($fks as $idx=>$def) {
                if ($def==='DROP') {
                    if (!empty($tableFKs[$idx])) {
                        $dropArr[] = "DROP FOREIGN KEY `{$idx}`";
                    }
                } else {
                    if (!empty($tableFKs[$idx])) {
                        $dropArr[] = "DROP FOREIGN KEY `{$idx}`";
                    }
                    $alterArr[] = "ADD CONSTRAINT `{$idx}` {$def}";
                }
            }
            if (!empty($dropArr)) {
                BORM::i()->raw_query("ALTER TABLE {$fullTableName} ".join(", ", $dropArr), array())->execute();
                static::ddlClearCache();
            }
        }
        $result = null;
        if ($alterArr) {
            $result = BORM::i()->raw_query("ALTER TABLE {$fullTableName} ".join(", ", $alterArr), array())->execute();
            static::ddlClearCache();
        }
        return $result;
    }

    /**
    * Clean array or object fields based on table columns and return an array
    *
    * @param array|object $data
    * @return array
    */
    public static function cleanForTable($table, $data)
    {
        $isObject = is_object($data);
        $result = array();
        foreach ($data as $k=>$v) {
            if (BDb::ddlFieldInfo($table, $k)) {
                $result[$k] = $isObject ? $data->get($k) : $data[$k];
            }
        }
        return $result;
    }
}

/**
* Enhanced PDO class to allow for transaction nesting for mysql and postgresql
*
* @see http://us.php.net/manual/en/pdo.connections.php#94100
* @see http://www.kennynet.co.uk/2008/12/02/php-pdo-nested-transactions/
*/
class BPDO extends PDO
{
    // Database drivers that support SAVEPOINTs.
    protected static $_savepointTransactions = array("pgsql", "mysql");

    // The current transaction level.
    protected $_transLevel = 0;
/*
    public static function exception_handler($exception)
    {
        // Output the exception details
        die('Uncaught exception: '. $exception->getMessage());
    }

    public function __construct($dsn, $username='', $password='', $driver_options=array())
    {
        // Temporarily change the PHP exception handler while we . . .
        set_exception_handler(array(__CLASS__, 'exception_handler'));

        // . . . create a PDO object
        parent::__construct($dsn, $username, $password, $driver_options);

        // Change the exception handler back to whatever it was before
        restore_exception_handler();
    }
*/
    protected function _nestable() {
        return in_array($this->getAttribute(PDO::ATTR_DRIVER_NAME),
                        static::$_savepointTransactions);
    }

    public function beginTransaction() {
        if (!$this->_nestable() || $this->_transLevel == 0) {
            parent::beginTransaction();
        } else {
            $this->exec("SAVEPOINT LEVEL{$this->_transLevel}");
        }

        $this->_transLevel++;
    }

    public function commit() {
        $this->_transLevel--;

        if (!$this->_nestable() || $this->_transLevel == 0) {
            parent::commit();
        } else {
            $this->exec("RELEASE SAVEPOINT LEVEL{$this->_transLevel}");
        }
    }

    public function rollBack() {
        $this->_transLevel--;

        if (!$this->_nestable() || $this->_transLevel == 0) {
            parent::rollBack();
        } else {
            $this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->_transLevel}");
        }
    }
}

/**
* Enhanced ORMWrapper to support multiple database connections and many other goodies
*/
class BORM extends ORMWrapper
{
    /**
    * Singleton instance
    *
    * @var BORM
    */
    protected static $_instance;

    /**
    * ID for profiling of the last run query
    *
    * @var int
    */
    protected static $_last_profile;

    /**
    * Default class name for direct ORM calls
    *
    * @var string
    */
    protected $_class_name = 'BModel';

    /**
    * Read DB connection for selects (replication slave)
    *
    * @var string|null
    */
    protected $_readConnectionName;

    /**
    * Write DB connection for updates (master)
    *
    * @var string|null
    */
    protected $_writeConnectionName;

    /**
    * Read DB name
    *
    * @var string
    */
    protected $_readDbName;

    /**
    * Write DB name
    *
    * @var string
    */
    protected $_writeDbName;

    /**
    * Old values in the object before ->set()
    *
    * @var array
    */
    protected $_old_values = array();

    /**
    * Shortcut factory for generic instance
    *
    * @return BConfig
    */
    public static function i($new=false)
    {
        if ($new) {
            return new static('');
        }
        if (!static::$_instance) {
            static::$_instance = new static('');
        }
        return static::$_instance;
    }

    protected function _quote_identifier($identifier) {
        if ($identifier[0]=='(') {
            return $identifier;
        }
        return parent::_quote_identifier($identifier);
    }

    public static function get_config($key)
    {
        return !empty(static::$_config[$key]) ? static::$_config[$key] : null;
    }

    /**
    * Public alias for _setup_db
    */
    public static function setup_db()
    {
        static::_setup_db();
    }

    /**
     * Set up the database connection used by the class.
     * Use BPDO for nested transactions
     */
    protected static function _setup_db()
    {
        if (!is_object(static::$_db)) {
            $connection_string = static::$_config['connection_string'];
            $username = static::$_config['username'];
            $password = static::$_config['password'];
            $driver_options = static::$_config['driver_options'];
            if (empty($driver_options[PDO::MYSQL_ATTR_INIT_COMMAND])) { //ADDED
                $driver_options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8";
            }
            try { //ADDED: hide connection details from the error if not in DEBUG mode
                $db = new BPDO($connection_string, $username, $password, $driver_options); //UPDATED
            } catch (PDOException $e) {
                if (BDebug::is('DEBUG')) {
                    throw $e;
                } else {
                    throw new PDOException('Could not connect to database');
                }
            }
            $db->setAttribute(PDO::ATTR_ERRMODE, static::$_config['error_mode']);
            static::set_db($db);
        }
    }

    /**
     * Set the PDO object used by Idiorm to communicate with the database.
     * This is public in case the ORM should use a ready-instantiated
     * PDO object as its database connection.
     */
    public static function set_db($db, $config=null)
    {
        if (!is_null($config)) {
            static::$_config = array_merge(static::$_config, $config);
        }
        static::$_db = $db;
        if (!is_null($db)) {
            static::_setup_identifier_quote_character();
        }
    }

    /**
    * Set read/write DB connection names from model
    *
    * @param string $read
    * @param string $write
    * @return BORMWrapper
    */
    public function set_rw_db_names($read, $write)
    {
        $this->_readDbName = $read;
        $this->_writeDbName = $write;
        return $this;
    }

    protected static function _log_query($query, $parameters)
    {
        $result = parent::_log_query($query, $parameters);
        static::$_last_profile = BDebug::debug('DB.RUN: '.(static::$_last_query ? static::$_last_query : 'LOGGING NOT ENABLED'));
        return $result;
    }

    /**
    * Execute the SELECT query that has been built up by chaining methods
    * on this class. Return an array of rows as associative arrays.
    *
    * Connection will be switched to read, if set
    *
    * @return array
    */
    protected function _run()
    {
        BDb::connect($this->_readConnectionName);
        #$timer = microtime(true); // file log
        $result = parent::_run();
        #BDebug::log((microtime(true)-$timer).' '.static::$_last_query); // file log
        BDebug::profile(static::$_last_profile);
        static::$_last_profile = null;
        return $result;
    }

    /**
    * Set or return table alias for the main table
    *
    * @param string|null $alias
    * @return BORM|string
    */
    public function table_alias($alias=null)
    {
        if (is_null($alias)) {
            return $this->_table_alias;
        }
        $this->_table_alias = $alias;
        return $this;
    }

    /**
    * Add a column to the list of columns returned by the SELECT
    * query. This defaults to '*'. The second optional argument is
    * the alias to return the column as.
    *
    * @param string|array $column if array, select multiple columns
    * @param string $alias optional alias, if $column is array, used as table name
    * @return BORM
    */
    public function select($column, $alias=null)
    {
        if (is_array($column)) {
            foreach ($column as $k=>$v) {
                $col = (!is_null($alias) ? $alias.'.' : '').$v;
                if (is_int($k)) {
                    $this->select($col);
                } else {
                    $this->select($col, $k);
                }
            }
            return $this;
        }
        return parent::select($column, $alias);
    }

    protected $_use_index = array();

    public function use_index($index, $type='USE', $table='_')
    {
        $this->_use_index[$table] = compact('index', 'type');
        return $this;
    }

    protected function _build_select_start() {
        $fragment = parent::_build_select_start();
        if (!empty($this->_use_index['_'])) {
            $idx = $this->_use_index['_'];
            $fragment .= ' '.$idx['type'].' INDEX ('.$idx['index'].') ';
        }
        return $fragment;
    }

    protected function _add_result_column($expr, $alias=null) {
        if (!is_null($alias)) {
            $expr .= " AS " . $this->_quote_identifier($alias);
        }
        // ADDED TO AVOID DUPLICATE FIELDS
        if (in_array($expr, $this->_result_columns)) {
            return $this;
        }

        if ($this->_using_default_result_columns) {
            $this->_result_columns = array($expr);
            $this->_using_default_result_columns = false;
        } else {
            $this->_result_columns[] = $expr;
        }
        return $this;
    }

    /**
    * Return select sql statement built from the ORM object
    *
    * @return string
    */
    public function as_sql()
    {
        return $this->_build_select();
    }

    /**
    * Execute the query and return PDO statement object
    *
    * Usage:
    *   $sth = $orm->execute();
    *   while ($row = $sth->fetch(PDO::FETCH_ASSOC)) { ... }
    *
    * @return PDOStatement
    */
    public function execute()
    {
        BDb::connect($this->_readConnectionName);
        $query = $this->_build_select();
        static::_log_query($query, $this->_values);
        $statement = static::$_db->prepare($query);
try {
        $statement->execute($this->_values);
} catch (Exception $e) {
echo $query;
print_r($e);
exit;
}
        return $statement;
    }

    public function row_to_model($row)
    {
        return $this->_create_model_instance($this->_create_instance_from_row($row));
    }

    /**
    * Iterate over select result with callback on each row
    *
    * @param mixed $callback
    * @param string $type
    * @return BORM
    */
    public function iterate($callback, $type='callback')
    {
        $statement = $this->execute();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $model = $this->row_to_model($row);
            switch ($type) {
                case 'callback': call_user_func($callback, $model); break;
                case 'method': $model->$callback(); break;
            }
        }
        return $this;
    }

    /**
    * Extended where condition
    *
    * @param string|array $column_name if array - use where_complex() syntax
    * @param mixed $value
    */
    public function where($column_name, $value=null)
    {
        if (is_array($column_name)) {
            return $this->where_complex($column_name, !!$value);
        }
        return parent::where($column_name, $value);
    }

    /**
    * Add a complex where condition
    *
    * @see BDb::where
    * @param array $conds
    * @param boolean $or
    * @return BORM
    */
    public function where_complex($conds, $or=false)
    {
        list($where, $params) = BDb::where($conds, $or);
        if (!$where) {
            return $this;
        }
        return $this->where_raw($where, $params);
    }

    /**
    * Find one row
    *
    * @return array
    */
    public function find_one($id=null)
    {
        $class = $this->_class_name;
        if ($class::origClass()) {
            $class = $class::origClass();
        }
        BPubSub::i()->fire($class.'::find_one.orm', array('orm'=>$this, 'class'=>$class, 'id'=>$id));
        $result = parent::find_one($id);
        BPubSub::i()->fire($class.'::find_one.after', array('result'=>$result, 'class'=>$class, 'id'=>$id));
        return $result;
    }

    /**
    * Find many rows (SELECT)
    *
    * @return array
    */
    public function find_many()
    {
        $class = $this->_class_name;
        if ($class::origClass()) {
            $class = $class::origClass();
        }
        BPubSub::i()->fire($class.'::find_many.orm', array('orm'=>$this, 'class'=>$class));
        $result = parent::find_many();
        BPubSub::i()->fire($class.'::find_many.after', array('result'=>$result, 'class'=>$class));
        return $result;
    }

    /**
    * Find many records and return as associated array
    *
    * @param string|array $key if array, will create multi-dimensional array (currently 2D)
    * @param string|null $labelColumn
    * @param array $options (key_lower, key_trim)
    * @return array
    */
    public function find_many_assoc($key=null, $labelColumn=null, $options=array())
    {
        $objects = $this->find_many();
        $array = array();
        if (empty($key)) {
            $key = $this->_get_id_column_name();
        }
        foreach ($objects as $r) {
            $value = is_null($labelColumn) ? $r : (is_array($labelColumn) ? BUtil::maskFields($r, $labelColumn) : $r->get($labelColumn));
            if (!is_array($key)) { // save on performance for 1D keys
                $v = $r->get($key);
                if (!empty($options['key_lower'])) $v = strtolower($v);
                if (!empty($options['key_trim'])) $v = trim($v);
                $array[$v] = $value;
            } else {
                $v1 = $r->get($key[0]);
                if (!empty($options['key_lower'])) $v1 = strtolower($v1);
                if (!empty($options['key_trim'])) $v1 = trim($v1);
                $v2 = $r->get($key[1]);
                if (!empty($options['key_lower'])) $v2 = strtolower($v2);
                if (!empty($options['key_trim'])) $v1 = trim($v2);
                $array[$v1][$v2] = $value;
            }
        }
        return $array;
    }

    /**
     * Check whether the given field (or object itself) has been changed since this
     * object was saved.
     */
    public function is_dirty($key=null) {
        return is_null($key) ? !empty($this->_dirty_fields) : isset($this->_dirty_fields[$key]);
    }

    /**
     * Set a property to a particular value on this object.
     * Flags that property as 'dirty' so it will be saved to the
     * database when save() is called.
     */
    public function set($key, $value) {
        if (!is_scalar($key)) {
            throw new BException('Key not scalar');
        }
        if (!array_key_exists($key, $this->_data)
            || is_null($this->_data[$key]) && !is_null($value)
            || !is_null($this->_data[$key]) && is_null($value)
            || is_scalar($this->_data[$key]) && is_scalar($value)
                && ((string)$this->_data[$key] !== (string)$value)
        ) {
#echo "DIRTY: "; var_dump($this->_data[$key], $value); echo "\n";
            if (!array_key_exists($key, $this->_old_values)) {
                $this->_old_values[$key] = array_key_exists($key, $this->_data) ? $this->_data[$key] : BNULL;
            }
            $this->_dirty_fields[$key] = $value;
        }
        $this->_data[$key] = $value;
    }

    /**
    * Class to table map cache
    *
    * @var array
    */
    protected static $_classTableMap = array();

    /**
     * Add a simple JOIN source to the query
     */
    public function _add_join_source($join_operator, $table, $constraint, $table_alias=null) {
        if (!isset(self::$_classTableMap[$table])) {
            if (class_exists($table) && is_subclass_of($table, 'BModel')) {
                $class = BClassRegistry::i()->className($table);
                self::$_classTableMap[$table] = $class::table();
            } else {
                self::$_classTableMap[$table] = false;
            }
        }
        if (self::$_classTableMap[$table]) {
            $table = self::$_classTableMap[$table];
        }
        return parent::_add_join_source($join_operator, $table, $constraint, $table_alias);
    }

    /**
     * Save any fields which have been modified on this object
     * to the database.
     *
     * Connection will be switched to write, if set
     *
     * @return boolean
     */
    public function save()
    {
        BDb::connect($this->_writeConnectionName);
        $this->_dirty_fields = BDb::cleanForTable($this->_table_name, $this->_dirty_fields);
        if (true) {
            #if (array_diff_assoc($this->_old_values, $this->_dirty_fields)) {
                $result = parent::save();
            #}
        } else {
            echo $this->_class_name.'['.$this->id.']: ';
            print_r($this->_data);
            echo 'FROM: '; print_r($this->_old_values);
            echo 'TO: '; print_r($this->_dirty_fields); echo "\n\n";
            $result = true;
        }
        $this->_old_values = array();
        return $result;
    }

    /**
    * Return dirty fields for debugging
    *
    * @return array
    */
    public function dirty_fields()
    {
        return $this->_dirty_fields;
    }

    public function old_values($property='')
    {
        if ($property && isset($this->_old_values[$property])) {
            return $this->_old_values[$property];
        }
        return $this->_old_values;
    }

    /**
     * Delete this record from the database
     *
     * Connection will be switched to write, if set
     *
     * @return boolean
     */
    public function delete()
    {
        BDb::connect($this->_writeConnectionName);
        return parent::delete();
    }

     /**
     * Add an ORDER BY expression DESC clause
     */
     public function order_by_expr($expression) {
        $this->_order_by[] = "{$expression}";
        return $this;
     }

    /**
     * Perform a raw query. The query should contain placeholders,
     * in either named or question mark style, and the parameters
     * should be an array of values which will be bound to the
     * placeholders in the query. If this method is called, all
     * other query building methods will be ignored.
     *
     * Connection will be set to write, if query is not SELECT or SHOW
     *
     * @return BORMWrapper
     */
    public function raw_query($query, $parameters=array())
    {
        if (preg_match('#^\s*(SELECT|SHOW)#i', $query)) {
            BDb::connect($this->_readConnectionName);
        } else {
            BDb::connect($this->_writeConnectionName);
        }
        return parent::raw_query($query, $parameters);
    }

    /**
    * Get table name with prefix, if configured
    *
    * @param string $class_name
    * @return string
    */
    protected static function _get_table_name($class_name) {
        return BDb::t(parent::_get_table_name($class_name));
    }

    /**
    * Set page constraints on collection for use in grids
    *
    * Request and result vars:
    * - p: page number
    * - ps: page size
    * - s: sort order by (if default is array - only these values are allowed) (alt: sort|dir)
    * - sd: sort direction (asc/desc)
    * - sc: sort combined (s|sd)
    * - rs: requested row start (optional in request, not dependent on page size)
    * - rc: requested row count (optional in request, not dependent on page size)
    * - c: total row count (return only)
    * - mp: max page (return only)
    *
    * Options (all optional):
    * - format: 0..2
    * - as_array: true or method name
    *
    * @param array $r pagination request, if null - take from request query string
    * @param array $d default values and options
    * @return array
    */
    public function paginate($r=null, $d=array())
    {
        if (is_null($r)) {
            $r = BRequest::i()->request(); // GET request
        }
        $d = (array)$d; // make sure it's array
        if (!empty($r['sc']) && empty($r['s']) && empty($r['sd'])) { // sort and dir combined
            list($r['s'], $r['sd']) = explode('|', $r['sc']);
        }
        if (!empty($r['s']) && !empty($d['s']) && is_array($d['s'])) { // limit by these values only
            if (!in_array($r['s'], $d['s'])) $r['s'] = null;
            $d['s'] = null;
        }
        if (!empty($r['sd']) && $r['sd']!='asc' && $r['sd']!='desc') { // only asc and desc are allowed
            $r['sd'] = null;
        }
        $s = array( // state
            'p'  => !empty($r['p'])  && is_numeric($r['p']) ? $r['p']  : (isset($d['p'])  ? $d['p']  : 1), // page
            'ps' => !empty($r['ps']) && is_numeric($r['ps']) ? $r['ps'] : (isset($d['ps']) ? $d['ps'] : 100), // page size
            's'  => !empty($r['s'])  ? $r['s']  : (isset($d['s'])  ? $d['s']  : ''), // sort by
            'sd' => !empty($r['sd']) ? $r['sd'] : (isset($d['sd']) ? $d['sd'] : 'asc'), // sort dir
            'rs' => !empty($r['rs']) ? $r['rs'] : null,
            'rc' => !empty($r['rc']) ? $r['rc'] : null,
            'q'  => !empty($r['q'])  ? $r['q'] : null,
            'c'  => !empty($d['c'])  ? $d['c'] : null, //total found
        );
#print_r($r); print_r($d); print_r($s); exit;
        $s['sc'] = $s['s'].'|'.$s['sd']; // sort combined for state

        #$s['c'] = 600000;
        if (empty($s['c'])){
            $cntOrm = clone $this; // clone ORM to count
            $s['c'] = $cntOrm->count(); // total row count
            unset($cntOrm); // free mem
        }

        $s['mp'] = ceil($s['c']/$s['ps']); // max page
        if (($s['p']-1)*$s['ps']>$s['c']) $s['p'] = $s['mp']; // limit to max page
        if ($s['s']) $this->{'order_by_'.$s['sd']}($s['s']); // sort rows if requested
        $s['rs'] = max(0, isset($s['rs']) ? $s['rs'] : ($s['p']-1)*$s['ps']); // start from requested row or page
        if(empty($d['donotlimit'])){
            $this->offset($s['rs'])->limit(!empty($s['rc']) ? $s['rc'] : $s['ps']); // limit rows to page
        }
        $rows = $this->find_many(); // result data
        $s['rc'] = $rows ? sizeof($rows) : 0; // returned row count
        if (!empty($d['as_array'])) {
            $rows = BDb::many_as_array($rows, is_string($d['as_array']) ? $d['as_array'] : 'as_array');
        }
        if (!empty($d['format'])) {
            switch ($d['format']) {
                case 1: return $rows;
                case 2: $s['rows'] = $rows; return $s;
            }
        }
        return array('state'=>$s, 'rows'=>$rows);
    }

    public function jqGridData($r=null, $d=array())
    {
        if (is_null($r)) {
            $r = BRequest::i()->request();
        }
        if (!empty($r['rows'])) { // without adapting jqgrid config
            $data = $this->paginate(array(
                'p'  => !empty($r['page']) ? $r['page'] : null,
                'ps' => !empty($r['rows']) ? $r['rows'] : null,
                's'  => !empty($r['sidx']) ? $r['sidx'] : null,
                'sd' => !empty($r['sord']) ? $r['sord'] : null,
            ), $d);
        } else { // jqgrid config adapted
            $data = $this->paginate($r, $d);
        }
        $res = $data['state'];
        $res['rows'] = $data['rows'];
        if (empty($d['as_array'])) {
            $res['rows'] = BDb::many_as_array($res['rows']);
        }
        return $res;
    }

    public function __destruct()
    {
        unset($this->_data);
    }
}

/**
* ORM model base class
*/
class BModel extends Model
{
    /**
    * Original class to be used as event prefix to remain constant in overridden classes
    *
    * Usage:
    *
    * class Some_Class extends BClass
    * {
    *    static protected $_origClass = __CLASS__;
    * }
    *
    * @var string
    */
    static protected $_origClass;

    /**
    * Named connection reference
    *
    * @var string
    */
    protected static $_connectionName = 'DEFAULT';
    /**
    * DB name for reads. Set in class declaration
    *
    * @var string|null
    */
    protected static $_readConnectionName = null;

    /**
    * DB name for writes. Set in class declaration
    *
    * @var string|null
    */
    protected static $_writeConnectionName = null;

    /**
    * Final table name cache with prefix
    *
    * @var array
    */
    protected static $_tableNames = array();

    /**
    * Whether to enable automatic model caching on load
    * if array, auto cache only if loading by one of these fields
    *
    * @var boolean|array
    */
    protected static $_cacheAuto = false;

    /**
    * Fields used in cache, that require values to be case insensitive or trimmed
    *
    * - key_lower
    * - key_trim (TODO)
    *
    * @var array
    */
    protected static $_cacheFlags = array();

    /**
    * Cache of model instances (for models that makes sense to keep cache)
    *
    * @var array
    */
    protected static $_cache = array();

    /**
    * Cache of instance level data values (related models)
    *
    * @var array
    */
    protected static $_instanceCache = array();

    /**
    * TRUE after save if a new record
    *
    * @var boolean
    */
    protected $_newRecord;

    /**
    * Retrieve original class name
    *
    * @return string
    */
    public static function origClass()
    {
        return static::$_origClass;
    }

    /**
    * PDO object of read DB connection
    *
    * @return BPDO
    */
    public static function readDb()
    {
        return BDb::connect(static::$_readConnectionName ? static::$_readConnectionName : static::$_connectionName);
    }

    /**
    * PDO object of write DB connection
    *
    * @return BPDO
    */
    public static function writeDb()
    {
        return BDb::connect(static::$_writeConnectionName ? static::$_writeConnectionName : static::$_connectionName);
    }

    /**
    * Model instance factory
    *
    * Use XXX::i()->orm($alias) instead
    *
    * @param string|null $class_name optional
    * @return BORM
    */
    public static function factory($class_name=null)
    {
        if (is_null($class_name)) { // ADDED
            $class_name = get_called_class();
        }
        $class_name = BClassRegistry::i()->className($class_name); // ADDED

        static::readDb();
        $table_name = static::_get_table_name($class_name);
        $orm = BORM::for_table($table_name); // CHANGED
        $orm->set_class_name($class_name);
        $orm->use_id_column(static::_get_id_column_name($class_name));
        $orm->set_rw_db_names( // ADDED
            static::$_readConnectionName ? static::$_readConnectionName : static::$_connectionName,
            static::$_writeConnectionName ? static::$_writeConnectionName : static::$_connectionName
        );
        $orm->table_alias('_main');
        return $orm;
    }

    /**
    * Alias for self::factory() with shortcut for table alias
    *
    * @param string $alias table alias
    * @return BORM
    */
    public static function orm($alias=null)
    {
        $orm = static::factory();
        if ($alias) {
            $orm->table_alias($alias);
        }
        static::_findOrm($orm);
        return $orm;
    }

    /**
    * Placeholder for class specific ORM augmentation
    *
    * @param BORM $orm
    */
    protected static function _findOrm($orm)
    {

    }

    /**
    * Fallback singleton/instance factory
    *
    * @param bool $new if true returns a new instance, otherwise singleton
    * @param array $args
    * @return BClass
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(get_called_class(), $args, !$new);
    }

    /**
    * Enhanced set method, allowing to set multiple values, and returning $this for chaining
    *
    * @param string|array $key
    * @param mixed $value
    * @param mixed $flag if true, add to existing value; if null, update only if currently not set
    * @return BModel
    */
    public function set($key, $value=null, $flag=false)
    {
        if (is_array($key)) {
            foreach ($key as $k=>$v) {
                parent::set($k, $v);
            }
        } else {
            if (true===$flag) {
                $oldValue = $this->get($key);
                if (is_array($oldValue)) {
                    $oldValue[] = $value;
                    $value = $oldValue;
                } else {
                    $value += $oldValue;
                }
            }
            if (is_scalar($key) && (!is_null($flag) || is_null($this->get($key)))) {
                parent::set($key, $value);
            }
        }
        return $this;
    }

    /**
    * Add a value to field
    *
    * @param string $key
    * @param mixed $value
    * @return BModel
    */
    public function add($key, $increment=1)
    {
        return $this->set($key, $increment, true);
    }

    /**
    * Create a new instance of the model
    *
    * @param null|array $data
    */
    public static function create($data=null)
    {
        $record = static::factory()->create($data);
        $record->afterCreate();
        return $record;
    }

    /**
    * Placeholder for after creae callback
    *
    * Called not after new object save, but after creation of the object in memory
    */
    public function afterCreate()
    {
        return $this;
    }

    /**
    * Get event class prefix for current object
    *
    * @return string
    */
    protected function _origClass()
    {
        return static::$_origClass ? static::$_origClass : get_class($this);
    }

    /**
    * Place holder for custom load ORM logic
    *
    * @param BORM $orm
    */
    protected static function _loadORM($orm)
    {

    }

    /**
    * Load a model object based on ID, another field or multiple fields
    *
    * @param int|string|array $id
    * @param string $field
    * @param boolean $cache
    * @return BModel
    */
    public static function load($id, $field=null, $cache=false)
    {
        $class = static::$_origClass ? static::$_origClass : get_called_class();
        if (is_null($field)) {
            $field = static::_get_id_column_name($class);
        }

        if (is_array($id)) {
            ksort($id);
            $field = join(',', array_keys($id));
            $keyValue = join(',', array_values($id));
        } else {
            $keyValue = $id;
        }
        if (!empty(static::$_cacheFlags[$field]['key_lower'])) {
            $keyValue = strtolower($keyValue);
        }
        if (!empty(static::$_cache[$class][$field][$keyValue])) {
            return static::$_cache[$class][$field][$keyValue];
        }

        $orm = static::factory();
        static::_loadORM($orm);
        BPubSub::i()->fire($class.'::load.orm', array('orm'=>$orm, 'class'=>$class, 'called_class'=>get_called_class()));
        if (is_array($id)) {
            $orm->where_complex($id);
        } else {
            if (strpos($field, '.')===false && ($alias = $orm->table_alias())) {
                $field = $alias.'.'.$field;
            }
            $orm->where($field, $id);
        }
        /** @var BModel $record */
        $record = $orm->find_one();
        if ($record) {
            $record->afterLoad();
            if ($cache
                || static::$_cacheAuto===true
                || is_array(static::$_cacheAuto) && in_array($field, static::$_cacheAuto)
            ) {
                $record->cacheStore();
            }
        }
        return $record;
    }

    /**
    * Placeholder for after load callback
    *
    * @return BModel
    */
    public function afterLoad()
    {
        BPubSub::i()->fire($this->_origClass().'::afterLoad', array('model'=>$this));
        return $this;
    }

    /**
    * Apply afterLoad() to all models in collection
    *
    * @param array $arr Model collection
    * @return BModel
    */
    public function afterLoadAll($arr)
    {
        foreach ($arr as $r) {
            $r->afterLoad();
        }
        return $this;
    }

    /**
    * Clear model cache
    *
    * @return BModel
    */
    public function cacheClear()
    {
        static::$_cache[$this->_origClass()] = array();
        return $this;
    }

    /**
    * Preload models into cache
    *
    * @param mixed $where complex where @see BORM::where_complex()
    * @param mixed $field cache key
    * @param mixed $sort
    * @return BModel
    */
    public function cachePreload($where=null, $field=null, $sort=null)
    {
        $orm = static::factory();
        $class = $this->_origClass();
        if (is_null($field)) {
            $field = static::_get_id_column_name($class);
        }
        $cache =& static::$_cache[$class];
        if ($where) $orm->where_complex($where);
        if ($sort) $orm->order_by_asc($sort);
        $options = !empty(static::$_cacheFlags[$field]) ? static::$_cacheFlags[$field] : array();
        $cache[$field] = $orm->find_many_assoc($field, null, $options);
        return $this;
    }

    /**
    * Preload models using keys from external collection
    *
    * @param array $collection
    * @param string $fk foreign key field
    * @param string $lk local key field
    * @return BModel
    */
    public function cachePreloadFrom($collection, $fk='id', $lk='id')
    {
        if (!$collection) return $this;
        $class = $this->_origClass();
        $keyValues = array();
        $keyLower = !empty(static::$_cacheFlags[$lk]['key_lower']);
        foreach ($collection as $r) {
            $key = null;
            if (is_object($r)) {
                $keyValue = $r->get($fk);
            } elseif (is_array($r)) {
                $keyValue = isset($r[$fk]) ? $r[$fk] : null;
            } elseif (is_scalar($r)) {
                $keyValue = $r;
            }
            if (empty($keyValue)) continue;
            if ($keyLower) $keyValue = strtolower($keyValue);
            if (!empty(static::$_cache[$class][$lk][$keyValue])) continue;
            $keyValues[$keyValue] = 1;
        }
        $field = (strpos($lk, '.')===false ? '_main.' : '').$lk; //TODO: table alias flexibility
        if ($keyValues) $this->cachePreload(array($field=>array_keys($keyValues)), $lk);
        return $this;
    }

    /**
    * Copy cache into another field
    *
    * @param string $toKey
    * @param string $fromKey
    * @return BModel
    */
    public function cacheCopy($toKey, $fromKey='id')
    {
        $cache =& static::$_cache[$this->_origClass()];
        $lower = !empty(static::$_cacheFlags[$toKey]['key_lower']);
        foreach ($cache[$fromKey] as $r) {
            $keyValue = $r->get($toKey);
            if ($lower) $keyValue = strtolower($keyValue);
            $cache[$toKey][$keyValue] = $r;
        }
        return $this;
    }

    /**
    * Save all dirty models in cache
    *
    * @return BModel
    */
    public function cacheSaveDirty($field='id')
    {
        $class = $this->_origClass();
        if (!empty(static::$_cache[$class][$field])) {
            foreach (static::$_cache[$class][$field] as $c) {
                if ($c->is_dirty()) {
                    $c->save();
                }
            }
        }
        return $this;
    }

    /**
    * Fetch all cached models by field
    *
    * @param string $field
    * @param string $key
    * @return array|BModel
    */
    public function cacheFetch($field='id', $keyValue=null)
    {
        $class = $this->_origClass();
        if (empty(static::$_cache[$class])) return null;
        $cache = static::$_cache[$class];
        if (empty($cache[$field])) return null;
        if (is_null($keyValue)) return $cache[$field];
        if (!empty(static::$_cacheFlags[$field]['key_lower'])) $keyValue = strtolower($keyValue);
        return !empty($cache[$field][$keyValue]) ? $cache[$field][$keyValue] : null;
    }

    /**
    * Store model in cache by field
    *
    * @todo rename to cacheSave()
    *
    * @param string|array $field one or more fields to store the cache for
    * @param array $collection external model collection to store into cache
    * @return BModel
    */
    public function cacheStore($field='id', $collection=null)
    {
        $cache =& static::$_cache[$this->_origClass()];
        if ($collection) {
            foreach ($collection as $r) {
                $r->cacheStore($field);
            }
            return $this;
        }
        if (is_array($field)) {
            foreach ($field as $k) {
                $this->cacheStore($k);
            }
            return $this;
        }
        if (strpos($field, ',')) {
            $keyValueArr = array();
            foreach (explode(',', $field) as $k) {
                $keyValueArr[] = $this->get($k);
            }
            $keyValue = join(',', $keyValueArr);
        } else {
            $keyValue = $this->get($field);
        }
        if (!empty(static::$_cacheFlags[$field]['key_lower'])) $keyValue = strtolower($keyValue);
        $cache[$field][$keyValue] = $this;
        return $this;
    }

    /**
    * Placeholder for before save callback
    *
    * @return boolean whether to continue with save
    */
    public function beforeSave()
    {
        return $this;
    }

    /**
    * Return dirty fields for debugging
    *
    * @return array
    */
    public function dirty_fields()
    {
        return $this->orm->dirty_fields();
    }

    /**
     * Check whether the given field has changed since the object was created or saved
     */
    public function is_dirty($property=null) {
        return $this->orm->is_dirty($property);
    }

    /**
     * Return old value(s) of modified field
     * @param type $property
     * @return type
     */
    public function old_values($property='')
    {
        return $this->orm->old_values($property);
    }

    /**
    * Save method returns the model object for chaining
    *
    *
    * @param boolean $beforeAfter whether to run beforeSave and afterSave
    * @return BModel
    */
    public function save($beforeAfter=true)
    {
        if ($beforeAfter) {
            if (!$this->beforeSave()) {
                return this;
            }
            try {
                $this->beforeSave();
                BPubSub::i()->fire($this->origClass().'::beforeSave', array('model'=>$this));
                BPubSub::i()->fire('BModel::beforeSave', array('model'=>$this));
            } catch (BModelException $e) {
                return $this;
            }
        }

        $this->_newRecord = !$this->get(static::_get_id_column_name(get_called_class()));

        parent::save();

        if ($beforeAfter) {
            $this->afterSave();
            BPubSub::i()->fire($this->_origClass().'::afterSave', array('model'=>$this));
            BPubSub::i()->fire('BModel::afterSave', array('model'=>$this));
        }

        if (static::$_cacheAuto) {
            $this->cacheStore();
        }
        return $this;
    }

    /**
    * Placeholder for after save callback
    *
    */
    public function afterSave()
    {
        return $this;
    }

    /**
    * Was the record just saved to DB?
    *
    * @return boolean
    */
    public function isNewRecord()
    {
        return $this->_newRecord;
    }

    /**
    * Placeholder for before delete callback
    *
    * @return boolean whether to continue with delete
    */
    public function beforeDelete()
    {
        return true;
    }

    public function delete()
    {
        try {
            if (!$this->beforeDelete()) {
                return $this;
            }
            BPubSub::i()->fire($this->_origClass().'::beforeDelete', array('model'=>$this));
        } catch(BModelException $e) {
            return $this;
        }

        if (($cache =& static::$_cache[$this->_origClass()])) {
            foreach ($cache as $k=>$c) {
                $keyValue = $this->get($k);
                if (!empty(static::$_cacheFlags[$k]['key_lower'])) $keyValue = strtolower($keyValue);
                unset($cache[$k][$keyValue]);
            }
        }

        parent::delete();

        $this->afterDelete();
        BPubSub::i()->fire($this->_origClass().'::afterDelete', array('model'=>$this));

        return $this;
    }

    public function afterDelete()
    {
        return;
    }

    /**
    * Run raw SQL with optional parameters
    *
    * @param string $sql
    * @param array $params
    * @return PDOStatement
    */
    public static function run_sql($sql, $params=array())
    {
        return static::writeDb()->prepare($sql)->execute((array)$params);
    }

    /**
    * Get table name for the model
    *
    * @return string
    */
    public static function table()
    {
        $class = BClassRegistry::i()->className(get_called_class());
        if (empty(static::$_tableNames[$class])) {
            static::$_tableNames[$class] = static::_get_table_name($class);
        }
        return static::$_tableNames[$class];
    }

    public static function overrideTable($table)
    {
        static::$_table = $table;
        $class = get_called_class();
        BDebug::debug('OVERRIDE TABLE: '.$class.' -> '.$table);
        static::$_tableNames[$class] = null;
        $class = BClassRegistry::i()->className($class);
        static::$_tableNames[$class] = null;
    }

    /**
    * Update one or many records of the class
    *
    * @param array $data
    * @param string|array $where where conditions (@see BDb::where)
    * @param array $params if $where string, use these params
    * @return boolean
    */
    public static function update_many(array $data, $where, $p=array())
    {
        $update = array();
        $params = array();
        foreach ($data as $k=>$v) {
            $update[] = "`{$k}`=?";
            $params[] = $v;
        }
        if (is_array($where)) {
            list($where, $p) = BDb::where($where);
        }
        $sql = "UPDATE ".static::table()." SET ".join(', ', $update) ." WHERE {$where}";
        BDebug::debug('SQL: '.$sql);
        return static::run_sql($sql, array_merge($params, $p));
    }

    /**
    * Delete one or many records of the class
    *
    * @param string|array $where where conditions (@see BDb::where)
    * @param array $params if $where string, use these params
    * @return boolean
    */
    public static function delete_many($where, $params=array())
    {
        if (is_array($where)) {
            list($where, $params) = BDb::where($where);
        }
        $sql = "DELETE FROM ".static::table()." WHERE {$where}";
        BDebug::debug('SQL: '.$sql);
        return static::run_sql($sql, $params);
    }

    /**
    * Model data as array, recursively
    *
    * @param array $objHashes cache of object hashes to check for infinite recursion
    * @return array
    */
    public function as_array(array $objHashes=array())
    {
        $objHash = spl_object_hash($this);
        if (!empty($objHashes[$objHash])) {
            return "*** RECURSION: ".get_class($this);
        }
        $objHashes[$objHash] = 1;

        $data = parent::as_array();
        foreach ($data as $k=>$v) {
            if ($v instanceof Model) {
                $data[$k] = $v->as_array();
            } elseif (is_array($v) && current($v) instanceof Model) {
                foreach ($v as $k1=>$v1) {
                    $data[$k][$k1] = $v1->as_array($objHashes);
                }
            }
        }
        return $data;
    }

    /**
    * Store instance data cache, such as related models
    *
    * @deprecated
    * @param string $key
    * @param mixed $value
    * @return mixed
    */
    public function instanceCache($key, $value=null)
    {
        $thisHash = spl_object_hash($this);
        if (null===$value) {
            return isset(static::$_instanceCache[$thisHash][$key]) ? static::$_instanceCache[$thisHash][$key] : null;
        }
        static::$_instanceCache[$thisHash][$key] = $value;
        return $this;
    }

    public function saveInstanceCache($key, $value)
    {
        $thisHash = spl_object_hash($this);
        static::$_instanceCache[$thisHash][$key] = $value;
        return $this;
    }

    public function loadInstanceCache($key)
    {
        $thisHash = spl_object_hash($this);
        return isset(static::$_instanceCache[$thisHash][$key]) ? static::$_instanceCache[$thisHash][$key] : null;
    }

    /**
    * Retrieve persistent related model object
    *
    * @param string $modelClass
    * @param mixed $idValue related object id value or complex where expression
    * @param boolean $autoCreate if record doesn't exist yet, create a new object
    * @result BModel
    */
    public function relatedModel($modelClass, $idValue, $autoCreate=false, $cacheKey=null)
    {
        $cacheKey = $cacheKey ? $cacheKey : $modelClass;
        $model = $this->loadInstanceCache($cacheKey);
        if (is_null($model)) {
            if (is_array($idValue)) {
                $model = $modelClass::i()->factory()->where_complex($idValue)->find_one();
                if ($model) $model->afterLoad();
            } else {
                $model = $modelClass::i()->load($idValue);
            }

            if ($autoCreate && !$model) {
                if (is_array($idValue)) {
                    $model = $modelClass::i()->create($idValue);
                } else {
                    $model = $modelClass::i()->create(array($foreignIdField=>$idValue));
                }
            }
            $this->saveInstanceCache($cacheKey, $model);
        }
        return $model;
    }

    /**
    * Retrieve persistent related model objects collection
    *
    * @param string $modelClass
    * @param mixed $idValue complex where expression
    * @result array
    */
    public function relatedCollection($modelClass, $where)
    {

    }

    /**
    * Return a member of child collection identified by a field
    *
    * @param string $var
    * @param string|int $id
    * @param string $idField
    * @return mixed
    */
    public function childById($var, $id, $idField='id')
    {
        $collection = $this->get($var);
        if (!$collection){
            $collection = $this->{$var};
            if (!$collection) return null;
        }
        foreach ($collection as $k=>$v) {
            if ($v->get($idField)==$id) return $v;
        }
        return null;
    }

    public function __destruct()
    {
        if ($this->orm) {
            $class = $this->_origClass();
            if (!empty(static::$_cache[$class])) {
                foreach (static::$_cache[$class] as $key=>$cache) {
                    $keyValue = $this->get($key);
                    if (!empty($cache[$keyValue])) {
                        unset(static::$_cache[$class][$keyValue]);
                    }
                }
            }

            unset(static::$_instanceCache[spl_object_hash($this)]);
        }
    }

    public function fieldOptions($field, $key=null)
    {
        if (!isset(static::$_fieldOptions[$field])) {
            BDebug::warning('Invalid field options type: '.$field);
            return null;
        }
        $options = static::$_fieldOptions[$field];
        if (!is_null($key)) {
            if (!isset($options[$key])) {
                BDebug::debug('Invalid field options key: '.$field.'.'.$key);
                return null;
            }
            return $options[$key];
        }
        return $options;
    }

    public function __call($name, $args)
    {
        return BClassRegistry::i()->callMethod($this, $name, $args, static::$_origClass);
    }

    public static function __callStatic($name, $args)
    {
        return BClassRegistry::i()->callStaticMethod(get_called_class(), $name, $args, static::$_origClass);
    }
}

class BModelException extends BException
{

}
