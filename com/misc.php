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
* Utility class to parse and construct strings and data structures
*/
class BUtil extends BClass
{
    /**
    * IV for mcrypt operations
    *
    * @var string
    */
    protected static $_mcryptIV;

    /**
    * Encryption key from configuration (encrypt/key)
    *
    * @var string
    */
    protected static $_mcryptKey;

    /**
    * Default hash algorithm
    *
    * @var string default sha512 for strength and slowness
    */
    protected static $_hashAlgo = 'bcrypt';

    /**
    * Default number of hash iterations
    *
    * @var int
    */
    protected static $_hashIter = 3;

    /**
    * Default full hash string separator
    *
    * @var string
    */
    protected static $_hashSep = '$';

    /**
    * Default character pool for random and sequence strings
    *
    * Chars "c", "C" are ommited to avoid accidental obscene language
    * Chars "0", "1", "I" are removed to avoid leading 0 and ambiguity in print
    *
    * @var string
    */
    protected static $_defaultCharPool = '23456789abdefghijklmnopqrstuvwxyzABDEFGHJKLMNOPQRSTUVWXYZ';

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BUtil
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Convert any data to JSON string
    *
    * $data can be BData instance, or array of BModel objects, will be automatically converted to array
    *
    * @param mixed $data
    * @return string
    */
    public static function toJson($data)
    {
        if (is_array($data) && is_object(current($data)) && current($data) instanceof BModel) {
            $data = BDb::many_as_array($data);
        } elseif (is_object($data) && $data instanceof BData) {
            $data = $data->as_array(true);
        }
        return json_encode($data);
    }

    /**
    * Parse JSON into PHP data
    *
    * @param string $json
    * @param bool $asObject if false will attempt to convert to array,
    *                       otherwise standard combination of objects and arrays
    */
    public static function fromJson($json, $asObject=false)
    {
        $obj = json_decode($json);
        return $asObject ? $obj : static::objectToArray($obj);
    }

    /**
    * Indents a flat JSON string to make it more human-readable.
    *
    * @param string $json The original JSON string to process.
    *
    * @return string Indented version of the original JSON string.
    */
    public static function jsonIndent($json)
    {

        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '  ';
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

            // If this character is the end of an element,
            // output a new line and indent the next line.
            } else if(($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
    }

    /**
    * Convert data to JavaScript string
    *
    * Notable difference from toJson: allows raw function callbacks
    *
    * @param mixed $val
    * @return string
    */
    public static function toJavaScript($val)
    {
        if (is_null($val)) {
            return 'null';
        } elseif (is_bool($val)) {
            return $val ? 'true' : 'false';
        } elseif (is_string($val)) {
            if (preg_match('#^\s*function\s*\(#', $val)) {
                return $val;
            } else {
                return "'".addslashes($val)."'";
            }
        } elseif (is_int($val) || is_float($val)) {
            return $val;
        } elseif ($val instanceof BValue) {
            return $val->toPlain();
        } elseif (($isObj = is_object($val)) || is_array($val)) {
            $out = array();
            if (!empty($val) && ($isObj || array_keys($val) !== range(0, count($val)-1))) { // assoc?
                foreach ($val as $k=>$v) {
                    $out[] = "'".addslashes($k)."':".static::toJavaScript($v);
                }
                return '{'.join(',', $out).'}';
            } else {
                foreach ($val as $k=>$v) {
                    $out[] = static::toJavaScript($v);
                }
                return '['.join(',', $out).']';
            }
        }
        return '"UNSUPPORTED TYPE"';
    }

    public static function toRss($data)
    {
        $lang = !empty($data['language']) ? $data['language'] : 'en-us';
        $ttl = !empty($data['ttl']) ? (int)$data['ttl'] : 40;
        $descr = !empty($data['description']) ? $data['description'] : $data['title'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>'
.'<title><![CDATA['.$data['title'].']]></title><link><![CDATA['.$data['link'].']]></link>'
.'<description><![CDATA['.$descr.']]></description><language><![CDATA['.$lang.']]></language><ttl>'.$ttl.'</ttl>';
        foreach ($data['items'] as $item) {
            if (!is_numeric($item['pubDate'])) {
                $item['pubDate'] =  strtotime($item['pubDate']);
            }
            if (empty($item['guid'])) {
                $item['guid'] = $item['link'];
            }
            $xml .= '<item><title><![CDATA['.$item['title'].']]></title>'
.'<description><![CDATA['.$item['description'].']]></description>'
.'<pubDate>'.date('r', $item['pubDate']).'</pubDate>'
.'<guid><![CDATA['.$item['guid'].']]></guid><link><![CDATA['.$item['link'].']]></link></item>';
        }
        $xml .= '</channel></rss>';
        return $xml;
    }

    /**
    * Convert object to array recursively
    *
    * @param object $d
    * @return array
    */
    public static function objectToArray($d)
    {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }
        if (is_array($d)) {
            return array_map('BUtil::objectToArray', $d);
        }
        return $d;
    }

    /**
    * Convert array to object
    *
    * @param mixed $d
    * @return object
    */
    public static function arrayToObject($d)
    {
        if (is_array($d)) {
            return (object) array_map('BUtil::objectToArray', $d);
        }
        return $d;
    }

    /**
     * version of sprintf for cases where named arguments are desired (php syntax)
     *
     * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
     *
     * with sprintfn: sprintfn('second: %second$s ; first: %first$s', array(
     *  'first' => '1st',
     *  'second'=> '2nd'
     * ));
     *
     * @see http://www.php.net/manual/en/function.sprintf.php#94608
     * @param string $format sprintf format string, with any number of named arguments
     * @param array $args array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    public static function sprintfn($format, $args = array())
    {
        $args = (array)$args;

        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)([a-zA-Z_]\w*)(?=\$)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (! array_key_exists($arg_key, $arg_nums)) {
                user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
                return false;
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key], $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        if (!$args) {
            $args = array('');
        }
        return vsprintf($format, array_values($args));
    }

    /**
    * Inject vars into string template
    *
    * Ex: echo BUtil::injectVars('One :two :three', array('two'=>2, 'three'=>3))
    * Result: "One 2 3"
    *
    * @param string $str
    * @param array $vars
    * @return string
    */
    public static function injectVars($str, $vars)
    {
        $from = array(); $to = array();
        foreach ($vars as $k=>$v) {
            $from[] = ':'.$k;
            $to[] = $v;
        }
        return str_replace($from, $to, $str);
    }

    /**
     * Merges any number of arrays / parameters recursively, replacing
     * entries with string keys with values from latter arrays.
     * If the entry or the next value to be assigned is an array, then it
     * automagically treats both arguments as an array.
     * Numeric entries are appended, not replaced, but only if they are
     * unique
     *
     * calling: result = BUtil::arrayMerge(a1, a2, ... aN)
     *
     * @param array $array1
     * @param array $array2...
     * @return array
     **/
     public static function arrayMerge() {
         $arrays = func_get_args();
         $base = array_shift($arrays);
         if (!is_array($base))  {
             $base = empty($base) ? array() : array($base);
         }
         foreach ($arrays as $append) {
             if (!is_array($append)) {
                 $append = array($append);
             }
             foreach ($append as $key => $value) {
                 if (is_numeric($key)) {
                     if (!in_array($value, $base)) {
                        $base[] = $value;
                     }
                 } elseif (!array_key_exists($key, $base)) {
                     $base[$key] = $value;
                 } elseif (is_array($value) && is_array($base[$key])) {
                     $base[$key] = static::arrayMerge($base[$key], $append[$key]);
                 } else {
                     $base[$key] = $value;
                 }
             }
         }
         return $base;
     }

    /**
    * Compare 2 arrays recursively
    *
    * @param array $array1
    * @param array $array2
    */
    public static function arrayCompare(array $array1, array $array2)
    {
        $diff = false;
        // Left-to-right
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key,$array2)) {
                $diff[0][$key] = $value;
            } elseif (is_array($value)) {
                if (!is_array($array2[$key])) {
                    $diff[0][$key] = $value;
                    $diff[1][$key] = $array2[$key];
                } else {
                    $new = static::arrayCompare($value, $array2[$key]);
                    if ($new !== false) {
                        if (isset($new[0])) $diff[0][$key] = $new[0];
                        if (isset($new[1])) $diff[1][$key] = $new[1];
                    }
                }
            } elseif ($array2[$key] !== $value) {
                 $diff[0][$key] = $value;
                 $diff[1][$key] = $array2[$key];
            }
        }
        // Right-to-left
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key,$array1)) {
                $diff[1][$key] = $value;
            }
            // No direct comparsion because matching keys were compared in the
            // left-to-right loop earlier, recursively.
        }
        return $diff;
    }

    /**
    * Walk over array of objects and perform method or callback on each row
    *
    * @param array $arr
    * @param callback $cb
    * @param array $args
    * @param boolean $ignoreExceptions
    * @return array
    */
    static public function arrayWalk($arr, $cb, $args=array(), $ignoreExceptions=false)
    {
        $result = array();
        foreach ($arr as $i=>$r) {
            $callback = is_string($cb) && $cb[0]==='.' ? array($r, substr($cb, 1)) : $cb;
            if ($ignoreExceptions) {
                try {
                    $result[] = call_user_func_array($callback, $args);
                } catch (Exception $e) {
                    BDebug::warning('EXCEPTION class('.get_class($r).') arrayWalk('.$i.'): '.$e->getMessage());
                }
            } else {
                $result[] = call_user_func_array($callback, $args);
            }
        }
        return $result;
    }

    /**
    * Clean array of ints from empty and non-numeric values
    *
    * If parameter is a string, splits by comma
    *
    * @param array|string $arr
    * @return array
    */
    static public function arrayCleanInt($arr)
    {
        $res = array();
        if (is_string($arr)) {
            $arr = explode(',', $arr);
        }
        if (is_array($arr)) {
            foreach ($arr as $k=>$v) {
                if (is_numeric($v)) {
                    $res[$k] = intval($v);
                }
            }
        }
        return $res;
    }

    /**
    * Insert 1 or more items into array at specific position
    *
    * Note: code repetition is for better iteration performance
    *
    * @param array $array The original container array
    * @param array $items Items to be inserted
    * @param string $where
    *   - start
    *   - end
    *   - offset==$key
    *   - key.(before|after)==$key
    *   - obj.(before|after).$object_property==$key
    *   - arr.(before|after).$item_array_key==$key
    * @return array resulting array
    */
    static public function arrayInsert($array, $items, $where)
    {
        $result = array();
        $w1 = explode('==', $where, 2);
        $w2 = explode('.', $w1[0], 3);

        switch ($w2[0]) {
        case 'start':
            $result = array_merge($items, $array);
            break;

        case 'end':
            $result = array_merge($array, $items);
            break;

        case 'offset': // for associative only
            $key = $w1[1];
            $i = 0;
            foreach ($array as $k=>$v) {
                if ($key===$i++) {
                    foreach ($items as $k1=>$v1) {
                        $result[$k1] = $v1;
                    }
                }
                $result[$k] = $v;
            }
            break;

        case 'key': // for associative only
            $rel = $w2[1];
            $key = $w1[1];
            foreach ($array as $k=>$v) {
                if ($key===$k) {
                    if ($rel==='after') {
                        $result[$k] = $v;
                    }
                    foreach ($items as $k1=>$v1) {
                        $result[$k1] = $v1;
                    }
                    if ($rel==='before') {
                        $result[$k] = $v;
                    }
                } else {
                    $result[$k] = $v;
                }
            }
            break;

        case 'obj':
            $rel = $w2[1];
            $f = $w2[2];
            $key = $w1[1];
            foreach ($array as $k=>$v) {
                if ($key===$v->$f) {
                    if ($rel==='after') {
                        $result[$k] = $v;
                    }
                    foreach ($items as $k1=>$v1) {
                        $result[$k1] = $v1;
                    }
                    if ($rel==='before') {
                        $result[$k] = $v;
                    }
                } else {
                    $result[$k] = $v;
                }
            }
            break;

        case 'arr':
            $rel = $w2[1];
            $f = $w2[2];
            $key = $w1[1];
            foreach ($array as $k=>$v) {
                if ($key===$v[$f]) {
                    if ($rel==='after') {
                        $result[$k] = $v;
                    }
                    foreach ($items as $k1=>$v1) {
                        $result[$k1] = $v1;
                    }
                    if ($rel==='before') {
                        $result[$k] = $v;
                    }
                } else {
                    $result[$k] = $v;
                }
            }
            break;

        default: BDebug::error('Invalid where condition: '.$where);
        }

        return $result;
    }

    /**
    * Return only specific fields from source array
    *
    * @param array $source
    * @param array|string $fields
    * @param boolean $inverse if true, will return anything NOT in $fields
    * @param boolean $setNulls fill missing fields with nulls
    * @result array
    */
    static public function arrayMask(array $source, $fields, $inverse=false, $setNulls=true)
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
            array_walk($fields, 'trim');
        }
        $result = array();
        if (!$inverse) {
            foreach ($fields as $k) {
                if (isset($source[$k])) {
                    $result[$k] = $source[$k];
                } elseif ($setNulls) {
                    $result[$k] = null;
                }
            }
        } else {
            foreach ($source as $k=>$v) {
                if (!in_array($k, $fields)) $result[$k] = $v;
            }
        }
        return $result;
    }

    static public function arrayToOptions($source, $labelField, $keyField=null, $emptyLabel=null)
    {
        $options = array();
        if (!is_null($emptyLabel)) {
            $options = array("" => $emptyLabel);
        }
        if (empty($source)) {
            return array();
        }
        $isObject = is_object(current($source));
        foreach ($source as $k=>$item) {
            if ($isObject) {
                $key = is_null($keyField) ? $k : $item->$keyField;
                $label = $labelField[0]==='.' ? $item->{substr($labelField, 1)}() : $item->labelField;
                $options[$key] = $label;
            } else {
                $key = is_null($keyField) ? $k : $item[$keyField];
                $options[$key] = $item[$labelField];
            }
        }
        return $options;
    }

    static public function arrayMakeAssoc($source, $keyField)
    {
        $isObject = is_object(current($source));
        $assocArray = array();
        foreach ($source as $k => $item) {
            if ($isObject) {
                $assocArray[$item->$keyField] = $item;
            } else {
                $assocArray[$item[$keyField]] = $item;
            }
        }
        return $assocArray;
    }

    /**
    * Create IV for mcrypt operations
    *
    * @return string
    */
    static public function mcryptIV()
    {
        if (!static::$_mcryptIV) {
            static::$_mcryptIV = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_DEV_URANDOM);
        }
        return static::$_mcryptIV;
    }

    /**
    * Fetch default encryption key from config
    *
    * @return string
    */
    static public function mcryptKey($key=null, $configPath=null)
    {
        if (!is_null($key)) {
            static::$_mcryptKey = $key;
        } elseif (is_null(static::$_mcryptKey) && $configPath) {
            static::$_mcryptKey = BConfig::i()->get($configPath);
        }
        return static::$_mcryptKey;

    }

    /**
    * Encrypt using AES256
    *
    * Requires PHP extension mcrypt
    *
    * @param string $value
    * @param string $key
    * @param boolean $base64
    * @return string
    */
    static public function encrypt($value, $key=null, $base64=true)
    {
        if (is_null($key)) $key = static::mcryptKey();
        $enc = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $value, MCRYPT_MODE_ECB, static::mcryptIV());
        return $base64 ? trim(base64_encode($enc)) : $enc;
    }

    /**
    * Decrypt using AES256
    *
    * Requires PHP extension mcrypt
    *
    * @param string $value
    * @param string $key
    * @param boolean $base64
    * @return string
    */
    static public function decrypt($value, $key=null, $base64=true)
    {
        if (is_null($key)) $key = static::mcryptKey();
        $enc = $base64 ? base64_decode($value) : $value;
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $enc, MCRYPT_MODE_ECB, static::mcryptIV()));
    }

    /**
    * Generate random string
    *
    * @param int $strLen length of resulting string
    * @param string $chars allowed characters to be used
    */
    public static function randomString($strLen=8, $chars=null)
    {
        if (is_null($chars)) {
            $chars = static::$_defaultCharPool;
        }
        $charsLen = strlen($chars)-1;
        $str = '';
        for ($i=0; $i<$strLen; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];
        }
        return $str;
    }

    /**
    * Generate random string based on pattern
    *
    * Syntax: {ULD10}-{U5}
    * - U: upper case letters
    * - L: lower case letters
    * - D: digits
    *
    * @param string $pattern
    * @return string
    */
    public static function randomPattern($pattern)
    {
        static $chars = array('L'=>'bcdfghjkmnpqrstvwxyz', 'U'=>'BCDFGHJKLMNPQRSTVWXYZ', 'D'=>'123456789');

        while (preg_match('#\{([ULD]+)([0-9]+)\}#i', $pattern, $m)) {
            for ($i=0, $c=''; $i<strlen($m[1]); $i++) $c .= $chars[$m[1][$i]];
            $pattern = preg_replace('#'.preg_quote($m[0]).'#', BUtil::randomString($m[2], $c), $pattern, 1);
        }
        return $pattern;
    }

    public static function nextStringValue($string='', $chars=null)
    {
        if (is_null($chars)) {
            $chars = static::$_defaultCharPool; // avoid leading 0
        }
        $pos = strlen($string);
        $lastChar = substr($chars, -1);
        while (--$pos>=-1) {
            if ($pos==-1) {
                $string = $chars[0].$string;
                return $string;
            } elseif ($string[$pos]===$lastChar) {
                $string[$pos] = $chars[0];
                continue;
            } else {
                $string[$pos] = $chars[strpos($chars, $string[$pos])+1];
                return $string;
            }
        }
        // should never get here
        return $string;
    }

    /**
    * Set or retrieve current hash algorithm
    *
    * @param string $algo
    */
    public static function hashAlgo($algo=null)
    {
        if (is_null($algo)) {
            return static::$_hashAlgo;
        }
        static::$_hashAlgo = $algo;
    }

    public static function hashIter($iter=null)
    {
        if (is_null($iter)) {
            return static::$_hashIter;
        }
        static::$iter = $iter;
    }

    /**
    * Generate salted hash
    *
    * @deprecated by Bcrypt
    * @param string $string original text
    * @param mixed $salt
    * @param mixed $algo
    * @return string
    */
    public static function saltedHash($string, $salt, $algo=null)
    {
        $algo = !is_null($algo) ? $algo : static::$_hashAlgo;
        return hash($algo, $salt.$string);
    }

    /**
    * Generate fully composed salted hash
    *
    * Ex: $sha512$2$<salt1>$<salt2>$<double-hashed-string-here>
    *
    * @deprecated by Bcrypt
    * @param string $string
    * @param string $salt
    * @param string $algo
    * @param integer $iter
    */
    public static function fullSaltedHash($string, $salt=null, $algo=null, $iter=null)
    {
        $algo = !is_null($algo) ? $algo : static::$_hashAlgo;
        if ('bcrypt'===$algo) {
            return Bcrypt::i()->hash($string);
        }
        $iter = !is_null($iter) ? $iter : static::$_hashIter;
        $s = static::$_hashSep;
        $hash = $s.$algo.$s.$iter;
        for ($i=0; $i<$iter; $i++) {
            $salt1 = !is_null($salt) ? $salt : static::randomString();
            $hash .= $s.$salt1;
            $string = static::saltedHash($string, $salt1, $algo);
        }
        return $hash.$s.$string;
    }

    /**
    * Validate salted hash against original text
    *
    * @deprecated by BUtil::bcrypt()
    * @param string $string original text
    * @param string $storedHash fully composed salted hash
    * @return bool
    */
    public static function validateSaltedHash($string, $storedHash)
    {
        if (strpos($storedHash, '$2a$')===0 || strpos($storedHash, '$2y$')===0) {
            return Bcrypt::i()->verify($string, $storedHash);
        }
        if (!$storedHash) {
            return false;
        }
        $sep = $storedHash[0];
        $arr = explode($sep, $storedHash);
        array_shift($arr);
        $algo = array_shift($arr);
        $iter = array_shift($arr);
        $verifyHash = $string;
        for ($i=0; $i<$iter; $i++) {
            $salt = array_shift($arr);
            $verifyHash = static::saltedHash($verifyHash, $salt, $algo);
        }
        $knownHash = array_shift($arr);
        return $verifyHash===$knownHash;
    }

    public static function sha512base64($str)
    {
        return base64_encode(pack('H*', hash('sha512', $str)));
    }

    static protected $_lastRemoteHttpInfo;
    /**
    * Send simple POST request to external server and retrieve response
    *
    * @param string $method
    * @param string $url
    * @param array $data
    * @return string
    */
    public static function remoteHttp($method, $url, $data = array())
    {
        $timeout = 5;
        $userAgent = 'Mozilla/5.0';
        if ($method==='GET' && $data) {
            if(is_array($data)){
                $request = http_build_query($data, '', '&');
            } else {
                $request = $data;
            }

            $url .= (strpos($url, '?')===false ? '?' : '&') . $request;
        }

        // curl disabled because file upload doesn't work for some reason. TODO: figure out why
        if (false && function_exists('curl_init')) {
            $curlOpt = array(
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_URL => $url,
                CURLOPT_ENCODING => '',
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CAINFO => dirname(__DIR__).'/ssl/ca-bundle.crt',
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_HTTPHEADER, array('Expect:'), //Fixes the HTTP/1.1 417 Expectation Failed
                CURLOPT_HEADER => true,
            );
            if (false) { // TODO: figure out cookies handling
                $cookieDir = BConfig::i()->get('fs/storage_dir').'/cache';
                BUtil::ensureDir($cookieDir);
                $cookie = tempnam($cookieDir, 'CURLCOOKIE');
                $curlOpt += array(
                    CURLOPT_COOKIEJAR => $cookie,
                );
            }

            if ($method==='POST') {
                $curlOpt += array(
                    CURLOPT_POSTFIELDS => $data,
                    CURLOPT_POST => 1,
                );
            } elseif ($method==='PUT') {
                $curlOpt += array(
                    CURLOPT_POSTFIELDS => $data,
                    CURLOPT_PUT => 1,
                );
            }
            $ch = curl_init();
            curl_setopt_array($ch, $curlOpt);
            $rawResponse = curl_exec($ch);
            list($response, $headers) = explode("\r\n\r\n", $rawResponse, 2);
            static::$_lastRemoteHttpInfo = curl_getinfo($ch);
            $respHeaders = explode("\r\n", $headers);
            if(curl_errno($ch) != 0){
                static::$_lastRemoteHttpInfo['errno'] = curl_errno($ch);
                static::$_lastRemoteHttpInfo['error'] = curl_error($ch);
            }
            curl_close($ch);

        } else {
            $opts = array('http' => array(
                'method' => $method,
                'timeout' => $timeout,
                'header' => "User-Agent: {$userAgent}\r\n",
            ));
            if ($method==='POST' || $method==='PUT') {
                $multipart = false;
                foreach ($data as $k=>$v) {
                    if (is_string($v) && $v[0]==='@') {
                        $multipart = true;
                        break;
                    }
                }
                if (!$multipart) {
                    $contentType = 'application/x-www-form-urlencoded';
                    $opts['http']['content'] = http_build_query($data);
                } else {
                    $boundary = '--------------------------'.microtime(true);
                    $contentType = 'multipart/form-data; boundary='.$boundary;
                    $opts['http']['content'] = '';
                    //TODO: implement recursive forms
                    foreach ($data as $k =>$v) {
                        if (is_string($v) && $v[0]==='@') {
                            $filename = substr($v, 1);
                            $fileContents = file_get_contents($filename);
                            $opts['http']['content'] .= "--{$boundary}\r\n".
                                "Content-Disposition: form-data; name=\"{$k}\"; filename=\"".basename($filename)."\"\r\n".
                                "Content-Type: application/zip\r\n".
                                "\r\n".
                                "{$fileContents}\r\n";
                        } else {
                            $opts['http']['content'] .= "--{$boundary}\r\n".
                                "Content-Disposition: form-data; name=\"{$k}\"\r\n".
                                "\r\n".
                                "{$v}\r\n";
                        }
                    }
                    $opts['http']['content'] .= "--{$boundary}--\r\n";
                }
                $opts['http']['header'] .= "Content-Type: {$contentType}\r\n";
                    //."Content-Length: ".strlen($request)."\r\n";
                if (preg_match('#^(ssl|ftps|https):#', $url)) {
                    $opts['ssl'] = array(
                        'verify_peer' => true,
                        'cafile' => dirname(__DIR__).'/ssl/ca-bundle.crt',
                        'verify_depth' => 5,
                    );
                }
            }
            $response = file_get_contents($url, false, stream_context_create($opts));

            static::$_lastRemoteHttpInfo = array(); //TODO: emulate curl data?
            $respHeaders = $http_response_header;
        }
        foreach ($respHeaders as $i => $line) {
            if ($i) {
                $arr = explode(':', $line, 2);
            } else {
                $arr = array(0, $line);
            }
            static::$_lastRemoteHttpInfo['headers'][strtolower($arr[0])] = trim($arr[1]);
        }

        return $response;
    }

    public static function lastRemoteHttpInfo()
    {
        return static::$_lastRemoteHttpInfo;
    }

    public static function normalizePath($path)
    {
        $path = str_replace('\\', '/', $path);
        if (strpos($path, '/..')!==false) {
            $a = explode('/', $path);
            $b = array();
            foreach ($a as $p) {
                if ($p==='..') array_pop($b); else $b[] = $p;
            }
            $path = join('/', $b);
        }
        return $path;
    }

    public static function globRecursive($pattern, $flags=0)
    {
        $files = glob($pattern, $flags);
        if (!$files) $files = array();
        $dirs = glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT);
        if ($dirs) {
            foreach ($dirs as $dir) {
                $files = array_merge($files, self::globRecursive($dir.'/'.basename($pattern), $flags));
            }
        }
        return $files;
    }

    public static function isPathAbsolute($path)
    {
        return !empty($path) && ($path[0]==='/' || $path[0]==='\\') // starting with / or \
            || !empty($path[1]) && $path[1]===':'; // windows drive letter C:
    }

    public static function isUrlFull($url)
    {
        return preg_match('#^(https?:)?//#', $url);
    }

    public static function ensureDir($dir)
    {
        if (is_file($dir)) {
            BDebug::warning($dir.' is a file, directory required');
            return;
        }
        if (!is_dir($dir)) {
            @$res = mkdir($dir, 0777, true);
            if (!$res) {
                BDebug::warning("Can't create directory: ".$dir);
            }
        }
    }

    /**
    * Put together URL components generated by parse_url() function
    *
    * @see http://us2.php.net/manual/en/function.parse-url.php#106731
    * @param array $p result of parse_url()
    * @return string
    */
    public static function unparseUrl($p)
    {
        $scheme   = isset($p['scheme'])   ? $p['scheme'] . '://' : '';
        $user     = isset($p['user'])     ? $p['user']           : '';
        $pass     = isset($p['pass'])     ? ':' . $p['pass']     : '';
        $pass     = ($user || $pass)      ? $pass . '@'          : '';
        $host     = isset($p['host'])     ? $p['host']           : '';
        $port     = isset($p['port'])     ? ':' . $p['port']     : '';
        $path     = isset($p['path'])     ? $p['path']           : '';
        $query    = isset($p['query'])    ? '?' . $p['query']    : '';
        $fragment = isset($p['fragment']) ? '#' . $p['fragment'] : '';
        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
    }

    /**
    * Add or set URL query parameters
    *
    * @param string $url
    * @param array $params
    * @return string
    */
    public static function setUrlQuery($url, $params)
    {
        if (true === $url) {
            $url = BRequest::currentUrl();
        }
        $parsed = parse_url($url);
        $query = array();
        if (!empty($parsed['query'])) {
            foreach (explode('&', $parsed['query']) as $q) {
                $a = explode('=', $q);
                if ($a[0]==='') {
                    continue;
                }
                $a[0] = urldecode($a[0]);
                $query[$a[0]] = urldecode($a[1]);
            }
        }
        foreach($params as $k => $v){
            if($v === ""){
                if(isset($query[$k])){
                    unset($query[$k]);
                }
                unset($params[$k]);
            }
        }
        $query = array_merge($query, $params);
        $parsed['query'] = http_build_query($query);
        return static::unparseUrl($parsed);
    }

    public static function paginateSortUrl($url, $state, $field)
    {
        return static::setUrlQuery($url, array(
            's'=>$field,
            'sd'=>$state['s']!=$field || $state['sd']=='desc' ? 'asc' : 'desc',
        ));
    }

    public static function paginateSortAttr($url, $state, $field, $class='')
    {
        return 'href="'.static::paginateSortUrl($url, $state, $field)
            .'" class="'.$class.' '.($state['s']==$field ? $state['sd'] : '').'"';
    }

    /**
     * @param string $tag
     * @param array  $attrs
     * @param null   $content
     * @return string
     */
    public static function tagHtml($tag, $attrs = array(), $content = null)
    {
        $attrsHtmlArr = array();
        foreach ($attrs as $k => $v) {
            if ('' === $v || is_null($v) || false === $v) {
                continue;
            }
            if (true === $v) {
                $v = $k;
            } elseif (is_array($v)) {
                switch ($k) {
                    case 'class':
                        $v = join(' ', $v);
                        break;

                    case 'style':
                        $attrHtmlArr = array();
                        foreach ($v as $k1 => $v1) {
                            $attrHtmlArr[] = $k1.':'.$v1;
                        }
                        $v = join('; ', $attrHtmlArr);
                        break;

                    default:
                        $v = join('', $v);
                }
            }
            $attrsHtmlArr[] = $k.'="'.htmlspecialchars($v, ENT_QUOTES, 'UTF-8').'"';
        }
        return '<'.$tag.' '.join(' ', $attrsHtmlArr).'>'.$content.'</'.$tag.'>';
    }

    /**
     * @param array $options
     * @param string $default
     * @return string
     */
    public static function optionsHtml($options, $default = '')
    {
        if(!is_array($default)){
            $default = (string)$default;
        }
        $htmlArr = array();
        foreach ($options as $k => $v) {
            $k = (string)$k;
            if (is_array($v) && $k!=='' && $k[0] === '@') { // group
                $label = trim(substr($k, 1));
                $htmlArr[] = BUtil::tagHtml('optgroup', array('label' => $label), static::optionsHtml($v, $default));
                continue;
            }
            if (is_array($v)) {
                $attr = $v;
                $v = !empty($attr['text']) ? $attr['text'] : '';
                unset($attr['text']);
            } else {
                $attr = array();
            }
            $attr['value'] = $k;
            $attr['selected'] = is_array($default) && in_array($k, $default) || $default === $k;
            $htmlArr[] = BUtil::tagHtml('option', $attr, $v);
        }

        return join("\n", $htmlArr);
    }

    /**
    * Strip html tags and shorten to specified length, to the whole word
    *
    * @param string $text
    * @param integer $limit
    */
    public static function previewText($text, $limit)
    {
        $text = strip_tags($text);
        if (strlen($text) < $limit) {
            return $text;
        }
        preg_match('/^(.{1,'.$limit.'})\b/', $text, $matches);
        return $matches[1];
    }

    public static function isEmptyDate($date)
    {
        return preg_replace('#[0 :-]#', '', (string)$date)==='';
    }

    /**
    * Get gravatar image src by email
    *
    * @param string $email
    * @param array $params
    *   - size (default 80)
    *   - rating (G, PG, R, X)
    *   - default
    *   - border
    */
    public static function gravatar($email, $params=array())
    {
        if (empty($params['default'])) {
            $params['default'] = 'identicon';
        }
        return BRequest::i()->scheme().'://www.gravatar.com/avatar/'.md5(strtolower($email))
            .($params ? '?'.http_build_query($params) : '');
    }

    public static function extCallback($callback)
    {
        if (is_string($callback)) {
            if (strpos($callback, '.')!==false) {
                list($class, $method) = explode('.', $callback);
            } elseif (strpos($callback, '->')) {
                list($class, $method) = explode('->', $callback);
            }
            if (!empty($class)) {
                $callback = array($class::i(), $method);
            }
        }
        return $callback;
    }

    public static function call($callback, $args=array(), $array=false)
    {
        $callback = static::extCallback($callback);
        if ($array) {
            return call_user_func_array($callback, $args);
        } else {
            return call_user_func($callback, $args);
        }
    }

    public static function formatDateRecursive($source, $format='m/d/Y')
    {
        foreach ($source as $i=>$val) {
            if (is_string($val)) {
                // checking only beginning of string for speed, assuming it is a date
                if (preg_match('#^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]( |$)#', $val)) {
                    $source[$i] = date($format, strtotime($val));
                }
            } elseif (is_array($val)) {
                $source[$i] = static::formatDateRecursive($val, $format);
            }
        }
        return $source;
    }

    public static function timeAgo($ptime, $now=null, $long=false)
    {
        if (!is_numeric($ptime)) {
            $ptime = strtotime($ptime);
        }
        if (!$now) {
            $now = time();
        } elseif (!is_numeric($now)) {
            $now = strtotime($now);
        }
        $etime = $now - $ptime;
        if ($etime < 1) {
            return $long ? 'less than 1 second' : '0s';
        }
        $a = array(
            12 * 30 * 24 * 60 * 60  =>  array('year', 'y'),
            30 * 24 * 60 * 60       =>  array('month', 'mon'),
            24 * 60 * 60            =>  array('day', 'd'),
            60 * 60                 =>  array('hour', 'h'),
            60                      =>  array('minute', 'm'),
            1                       =>  array('second', 's'),
        );

        foreach ($a as $secs => $sa) {
            $d = $etime / $secs;
            if ($d >= 1) {
                $r = round($d);
                return $r . ($long ? ' ' . $sa[0] . ($r > 1 ? 's' : '') : $sa[1]);
            }
        }
    }

    /**
     * Simplify string to allowed characters only
     *
     * @param string $str input string
     * @param string $pattern RegEx pattern to specify not allowed characters
     * @param string $filler character to replace not allowed characters with
     * @return string
     */
    static public function simplifyString($str, $pattern='#[^a-z0-9-]+#', $filler='-')
    {
        return trim(preg_replace($pattern, $filler, strtolower($str)), $filler);
    }

    /**
    * Remove directory recursively
    *
    * DANGEROUS, I'm afraid to enable it
    *
    * @param string $dir
    */
    /*
    static public function rmdirRecursive_YesIHaveCheckedThreeTimes($dir, $first=true)
    {
        if ($first) {
            $dir = realpath($dir);
        }
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir) || is_link($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!static::rmdirRecursive($dir . "/" . $item, false)) {
                chmod($dir . "/" . $item, 0777);
                if (!static::rmdirRecursive($dir . "/" . $item, false)) return false;
            }
        }
        return rmdir($dir);
    }
    */

    static public function topoSort(array $array, array $args=array())
    {
        if (empty($array)) {
            return array();
        }

        // nodes listed in 'after' are parents
        // nodes listed in 'before' are children
        // prepare initial $nodes array
        $beforeVar = !empty($args['before']) ? $args['before'] : 'before';
        $afterVar = !empty($args['before']) ? $args['after'] : 'after';
        $isObject = is_object(current($array));
        $nodes = array();
        foreach ($array as $k=>$v) {
            $before = $isObject ? $v->$beforeVar : $v[$beforeVar];
            if (is_string($before)) {
                $before = array_walk(explode(',', $before), 'trim');
            }
            $after = $isObject ? $v->$afterVar : $v[$afterVar];
            if (is_string($after)) {
                $after = array_walk(explode(',', $after), 'trim');
            }
            $nodes[$k] = array('key' => $k, 'item' => $v, 'parents' => (array)$after, 'children' => (array)$before);
        }

        // get nodes without parents
        $rootNodes = array();
        foreach ($nodes as $k=>$node) {
            if (empty($node['parents'])) {
                $rootNodes[] = $node;
            }
        }
        // begin algorithm
        $sorted = array();
        while ($nodes) {
            // check for circular reference
            if (!$rootNodes) return false;
            // remove this node from root nodes and add it to the output
            $n = array_pop($rootNodes);
            $sorted[$n['key']] = $n['item'];
            // for each of its children: queue the new node, finally remove the original
            for ($i = count($n['children'])-1; $i>=0; $i--) {
                // get child node
                $childNode = $nodes[$n['children'][$i]];
                // remove child nodes from parent
                unset($n['children'][$i]);
                // remove parent from child node
                unset($childNode['parents'][array_search($n['name'], $childNode['parents'])]);
                // check if this child has other parents. if not, add it to the root nodes list
                if (!$childNode['parents']) {
                    array_push($rootNodes, $childNode);
                }
            }
            // remove processed node from list
            unset($nodes[$n['key']]);
        }
        return $sorted;
    }

    /**
     * Wrapper for ZipArchive::open+extractTo
     *
     * @param string $filename
     * @param string $targetDir
     * @return boolean Result
     */
    static public function zipExtract($filename, $targetDir)
    {
        if (!class_exists('ZipArchive')) {
            throw new BException("Class ZipArchive doesn't exist");
        }
        $zip = new ZipArchive;
        $res = $zip->open($filename);
        if (!$res) {
            throw new BException("Can't open zip archive for reading: " . $filename);
        }
        BUtil::ensureDir($targetDir);
        $res = $zip->extractTo($targetDir);
        $zip->close();
        if (!$res) {
            throw new BException("Can't extract zip archive: " . $filename . " to " . $targetDir);
        }
        return true;
    }

    static public function zipCreateFromDir($filename, $sourceDir)
    {
        if (!class_exists('ZipArchive')) {
            throw new BException("Class ZipArchive doesn't exist");
        }
        $files = BUtil::globRecursive($sourceDir.'/*');
        if (!$files) {
            throw new BException('Invalid or empty source dir');
        }
        $zip = new ZipArchive;
        $res = $zip->open($filename, ZipArchive::CREATE);
        if (!$res) {
            throw new BException("Can't open zip archive for writing: " . $filename);
        }
        foreach ($files as $file) {
            $packedFile = str_replace($sourceDir.'/', '', $file);
            if (is_dir($file)) {
                $zip->addEmptyDir($packedFile);
            } else {
                $zip->addFile($file, $packedFile);
            }
        }
        $zip->close();
        return true;
    }
}

class BHTML extends BClass
{

}

/**
 * @todo Verify license compatibility and integrate with https://github.com/PHPMailer/PHPMailer
 */
class BEmail extends BClass
{
    static protected $_handlers = array();
    static protected $_defaultHandler = 'default';

    public function __construct()
    {
        $this->addHandler('default', array($this, 'defaultHandler'));
    }

    public function addHandler($name, $params)
    {
        if (is_callable($params)) {
            $params = array(
                'description' => $name,
                'callback' => $params,
            );
        }
        static::$_handlers[$name] = $params;
    }

    public function getHandlers()
    {
        return static::$_handlers;
    }

    public function setDefaultHandler($name)
    {
        static::$_defaultHandler = $name;
    }

    public function send($data)
    {
        static $allowedHeadersRegex = '/^(to|from|cc|bcc|reply-to|return-path|content-type|list-unsubscribe|x-.*)$/';

        $data = array_change_key_case($data, CASE_LOWER);

        $body = trim($data['body']);
        unset($data['body']);

        $to      = '';
        $subject = '';
        $headers = array();
        $params  = array();
        $files   = array();

        foreach ($data as $k => $v) {
            if ($k == 'subject') {
                $subject = $v;

            } elseif ($k == 'to') {
                $to = $v;

            } elseif ($k == 'attach') {
                foreach ((array)$v as $file) {
                    $files[] = $file;
                }

            } elseif ($k[0] === '-') {
                $params[$k] = $k . ' ' . $v;

            } elseif (preg_match($allowedHeadersRegex, $k)) {
                if (!empty($v) && $v!=='"" <>') {
                    $headers[$k] = $k . ': ' . $v;
                }
            }
        }

        $origBody = $body;
        if ($files) {
            // $body and $headers will be updated
            $this->_addAttachment($files, $headers, $body);
        }

        $emailData = array(
            'to' => &$to,
            'subject' => &$subject,
            'orig_body' => &$origBody,
            'body' => &$body,
            'headers' => &$headers,
            'params' => &$params,
            'files' => &$files,
            'orig_data' => $data,
        );

        return $this->_dispatch($emailData);
    }

    protected function _dispatch($emailData)
    {
        try {
            $flags = BEvents::i()->fire('BEmail::send:before', array('email_data' => $emailData));
            if ($flags===false) {
                return false;
            } elseif (is_array($flags)) {
                foreach ($flags as $f) {
                    if ($f===false) {
                        return false;
                    }
                }
            }
        } catch (BException $e) {
            BDebug::warning($e->getMessage());
            return false;
        }

        $callback = static::$_handlers[static::$_defaultHandler]['callback'];
        if (is_callable($callback)) {
            $result = call_user_func($callback, $emailData);
        } else {
            BDebug::warning('Default email handler is not callable');
            $result = false;
        }
        $emailData['result'] = $result;

        BEvents::i()->fire('BEmail::send:after', array('email_data' => $emailData));

        return $result;
    }

    /**
     * Add email attachment
     *
     * @param $files
     * @param $mailheaders
     * @param $body
     */
    protected function _addAttachment($files, &$mailheaders, &$body)
    {
        $body = trim($body);
        //$headers = array();
        // boundary
        $semi_rand     = md5(microtime());
        $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";

        // headers for attachment
        $headers   = $mailheaders;
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed;";
        $headers[] = " boundary=\"{$mime_boundary}\"";

        //headers and message for text
        $message = "--{$mime_boundary}\n\n" . $body . "\n\n";

        // preparing attachments
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = chunk_split(base64_encode(file_get_contents($file)));
                $name = basename($file);
                $message .= "--{$mime_boundary}\n" .
                    "Content-Type: application/octet-stream; name=\"" . $name . "\"\n" .
                    "Content-Description: " . $name . "\n" .
                    "Content-Disposition: attachment;\n" . " filename=\"" . $name . "\"; size=" . filesize($files[$i]) . ";\n" .
                    "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
            }
        }
        $message .= "--{$mime_boundary}--";

        $body        = $message;
        $mailheaders = $headers;
        return true;
    }

    public function defaultHandler($data)
    {
        return mail($data['to'], $data['subject'], $data['body'],
            join("\r\n", $data['headers']), join(' ', $data['params']));
    }
}

/**
* Helper class to designate a variable a custom type
*/
class BValue
{
    public $content;
    public $type;

    public function __construct($content, $type='string')
    {
        $this->content = $content;
        $this->type = $type;
    }

    public function toPlain()
    {
        return $this->content;
    }

    public function __toString()
    {
        return (string)$this->toPlain();
    }
}

/**
* @deprecated
*/
class BType extends BValue {}

class BData extends BClass implements ArrayAccess
{
    protected $_data;

    public function __construct($data, $recursive = false)
    {
        if (!is_array($data)) {
            $data = array(); // not sure for here, should we try to convert data to array or do empty array???
        }
        if ($recursive) {
            foreach ($data as $k => $v) {
                if (is_array($v)) {
                    $data[$k] = new BData($v, true);
                }
            }
        }
        $this->_data = $data;
    }

    public function as_array($recursive=false)
    {
        $data = $this->_data;
        if ($recursive) {
            foreach ($data as $k => $v) {
                if (is_object($v) && $v instanceof BData) {
                    $data[$k] = $v->as_array();
                }
            }
        }
        return $data;
    }

    public function __get($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->_data[$name] = $value;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_data[] = $value;
        } else {
            $this->_data[$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->_data[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->_data[$offset]) ? $this->_data[$offset] : null;
    }

    public function get($name)
    {
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    public function set($name, $value)
    {
        $this->_data[$name] = $value;
        return $this;
    }
}

class BErrorException extends Exception
{
    public $context;
    public $stackPop;

    public function __construct($code, $message, $file, $line, $context=null, $stackPop=1)
    {
        parent::__construct($message, $code);
        $this->file = $file;
        $this->line = $line;
        $this->context = $context;
        $this->stackPop = $stackPop;
    }
}

/**
* Facility to log errors and events for development and debugging
*
* @todo move all debugging into separate plugin, and override core classes
*/
class BDebug extends BClass
{
    const EMERGENCY = 0,
        ALERT       = 1,
        CRITICAL    = 2,
        ERROR       = 3,
        WARNING     = 4,
        NOTICE      = 5,
        INFO        = 6,
        DEBUG       = 7;

    static protected $_levelLabels = array(
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT     => 'ALERT',
        self::CRITICAL  => 'CRITICAL',
        self::ERROR     => 'ERROR',
        self::WARNING   => 'WARNING',
        self::NOTICE    => 'NOTICE',
        self::INFO      => 'INFO',
        self::DEBUG     => 'DEBUG',
    );

    const MEMORY  = 0,
        FILE      = 1,
        SYSLOG    = 2,
        EMAIL     = 4,
        OUTPUT    = 8,
        EXCEPTION = 16,
        STOP      = 4096;

    const MODE_DEBUG      = 'DEBUG',
        MODE_DEVELOPMENT  = 'DEVELOPMENT',
        MODE_STAGING      = 'STAGING',
        MODE_PRODUCTION   = 'PRODUCTION',
        MODE_MIGRATION    = 'MIGRATION',
        MODE_INSTALLATION = 'INSTALLATION',
        MODE_RECOVERY     = 'RECOVERY',
        MODE_DISABLED     = 'DISABLED'
    ;

    /**
    * Trigger levels for different actions
    *
    * - memory: remember in immediate script memory
    * - file: write to debug log file
    * - email: send email notification to admin
    * - output: display error in output
    * - exception: stop script execution by throwing exception
    *
    * Default are production values
    *
    * @var array
    */
    static protected $_level;

    static protected $_levelPreset = array(
        self::MODE_PRODUCTION => array(
            self::MEMORY    => false,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::ERROR,
            self::OUTPUT    => self::CRITICAL,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_STAGING => array(
            self::MEMORY    => false,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::ERROR,
            self::OUTPUT    => self::CRITICAL,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_DEVELOPMENT => array(
            self::MEMORY    => self::INFO,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_DEBUG => array(
            self::MEMORY    => self::DEBUG,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_RECOVERY => array(
            self::MEMORY    => self::DEBUG,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_MIGRATION => array(
            self::MEMORY    => self::DEBUG,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_INSTALLATION => array(
            self::MEMORY    => self::DEBUG,
            self::SYSLOG    => false,
            self::FILE      => self::WARNING,
            self::EMAIL     => false,//self::CRITICAL,
            self::OUTPUT    => self::NOTICE,
            self::EXCEPTION => self::ERROR,
            self::STOP      => self::CRITICAL,
        ),
        self::MODE_DISABLED => array(
            self::MEMORY    => false,
            self::SYSLOG    => false,
            self::FILE      => false,
            self::EMAIL     => false,
            self::OUTPUT    => false,
            self::EXCEPTION => false,
            self::STOP      => false,
        ),
    );

    static protected $_modules = array();

    static protected $_mode = 'PRODUCTION';

    static protected $_startTime;
    static protected $_events = array();

    static protected $_logDir = null;
    static protected $_logFile = array(
        self::EMERGENCY => 'error.log',
        self::ALERT     => 'error.log',
        self::CRITICAL  => 'error.log',
        self::ERROR     => 'error.log',
        self::WARNING   => 'debug.log',
        self::NOTICE    => 'debug.log',
        self::INFO      => 'debug.log',
        self::DEBUG     => 'debug.log',
    );

    static protected $_adminEmail = null;

    static protected $_phpErrorMap = array(
        E_ERROR => self::ERROR,
        E_WARNING => self::WARNING,
        E_NOTICE => self::NOTICE,
        E_USER_ERROR => self::ERROR,
        E_USER_WARNING => self::WARNING,
        E_USER_NOTICE => self::NOTICE,
        E_STRICT => self::NOTICE,
        E_RECOVERABLE_ERROR => self::ERROR,
    );

    static protected $_verboseBacktrace = array();

    static protected $_collectedErrors = array();

    static protected $_errorHandlerLog = array();

    /**
    * Constructor, remember script start time for delta timestamps
    *
    * @return BDebug
    */
    public function __construct()
    {
        self::$_startTime = microtime(true);
        BEvents::i()->on('BResponse::output:after', 'BDebug::afterOutput');
    }

    /**
     * Shortcut to help with IDE autocompletion
     *
     * @param bool  $new
     * @param array $args
     * @return BDebug
     */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public static function registerErrorHandlers()
    {
        set_error_handler('BDebug::errorHandler');
        set_exception_handler('BDebug::exceptionHandler');
        register_shutdown_function('BDebug::shutdownHandler');
    }

    public static function startErrorLogger()
    {
        static::$_errorHandlerLog = array();
        set_error_handler('BDebug::errorHandlerLogger');
    }

    public static function stopErrorLogger()
    {
        set_error_handler('BDebug::errorHandler');
        return static::$_errorHandlerLog;
    }

    public static function errorHandlerLogger($code, $message, $file, $line, $context=null)
    {
        return static::$_errorHandlerLog[] = compact('code', 'message', 'file', 'line', 'context');
    }

    public static function errorHandler($code, $message, $file, $line, $context=null)
    {
        if (!(error_reporting() & $code)) {
            return;
        }
        static::trigger(self::$_phpErrorMap[$code], $message, 1);
        //throw new BErrorException(self::$_phpErrorMap[$code], $message, $file, $line, $context);
    }

    public static function exceptionHandler($e)
    {
        //static::trigger($e->getCode(), $e->getMessage(), $e->stackPop+1);
        static::trigger(self::ERROR, $e);
    }

    public static function shutdownHandler()
    {
        $e = error_get_last();
        if ($e && ($e['type']===E_ERROR || $e['type']===E_PARSE || $e['type']===E_COMPILE_ERROR || $e['type']===E_COMPILE_WARNING)) {
            static::trigger(self::CRITICAL, $e['file'].':'.$e['line'].': '.$e['message'], 1);
        }
    }

    public static function level($type, $level=null)
    {
        if (!isset(static::$_level[$type])) {
            throw new BException('Invalid debug level type');
        }
        if (is_null($level)) {
            if (is_null(static::$_level)) {
                static::$_level = static::$_levelPreset[self::$_mode];
            }
            return static::$_level[$type];
        }
        static::$_level[$type] = $level;
    }

    public static function logDir($dir)
    {
        BUtil::ensureDir($dir);
        static::$_logDir = $dir;
    }

    public static function log($msg, $file='debug.log')
    {
        error_log($msg."\n", 3, static::$_logDir.'/'.$file);
    }

    public static function logException($e)
    {
        static::log(print_r($e, 1), 'exceptions.log');
    }

    public static function adminEmail($email)
    {
        self::$_adminEmail = $email;
    }

    public static function mode($mode=null, $setLevels=true)
    {
        if (is_null($mode)) {
            return static::$_mode;
        }
        self::$_mode = $mode;
        if ($setLevels && !empty(static::$_levelPreset[$mode])) {
            static::$_level = static::$_levelPreset[$mode];
        }
    }

    public static function backtraceOn($msg)
    {
        foreach ((array)$msg as $m) {
            static::$_verboseBacktrace[$m] = true;
        }
    }

    public static function trigger($level, $msg, $stackPop=0)
    {
        if (is_scalar($msg)) {
            $e = array('msg'=>$msg);
        } elseif (is_object($msg) && $msg instanceof Exception) {
            $e = array('msg'=>$msg->getMessage());
        } elseif (is_array($msg)) {
            $e = $msg;
        } else {
            throw new Exception('Invalid message type: '.print_r($msg, 1));
        }

        //$stackPop++;
        $bt = debug_backtrace(true);
        $e['level'] = self::$_levelLabels[$level];
        if (isset($bt[$stackPop]['file'])) $e['file'] = $bt[$stackPop]['file'];
        if (isset($bt[$stackPop]['line'])) $e['line'] = $bt[$stackPop]['line'];
        //$o = $bt[$stackPop]['object'];
        //$e['object'] = is_object($o) ? get_class($o) : $o;

        $e['ts'] = BDb::now();
        $e['t'] = microtime(true)-self::$_startTime;
        $e['d'] = null;
        $e['c'] = null;
        $e['mem'] = memory_get_usage();

        if (!empty(static::$_verboseBacktrace[$e['msg']])) {
            foreach ($bt as $t) {
                $e['msg'] .= "\n".$t['file'].':'.$t['line'];
            }
        }

        $message = "{$e['level']}: {$e['msg']}".(isset($e['file'])?" ({$e['file']}:{$e['line']})":'');

        if (($moduleName = BModuleRegistry::i()->currentModuleName())) {
            $e['module'] = $moduleName;
        }

        if (is_null(static::$_level) && !empty(static::$_levelPreset[self::$_mode])) {
            static::$_level = static::$_levelPreset[self::$_mode];
        }

        $l = self::$_level[self::MEMORY];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            self::$_events[] = $e;
            $id = sizeof(self::$_events)-1;
        }

        $l = self::$_level[self::SYSLOG];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            error_log($message, 0, self::$_logDir);
        }

        if (!is_null(self::$_logDir)) { // require explicit enable of file log
            $l = self::$_level[self::FILE];
            if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
                /*
                if (is_null(self::$_logDir)) {
                    self::$_logDir = sys_get_temp_dir();
                }
                */
                $file = self::$_logDir.'/'.self::$_logFile[$level];
                if (is_writable(self::$_logDir) || is_writable($file)) {
                    error_log("{$e['ts']} {$message}\n", 3, $file);
                } else {
                    //TODO: anything needs to be done here?
                }
            }
        }

        if (!is_null(self::$_adminEmail)) { // require explicit enable of email
            $l = self::$_level[self::EMAIL];
            if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
                error_log(print_r($e, 1), 1, self::$_adminEmail);
            }
        }

        $l = self::$_level[self::OUTPUT];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            echo '<xmp style="text-align:left; border:solid 1px red; font-family:monospace;">';
            //ob_start();
            echo $message."\n";
            debug_print_backtrace();
            //echo ob_get_clean();
            echo '</xmp>';
        }
/*
        $l = self::$_level[self::EXCEPTION];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            if (is_object($msg) && $msg instanceof Exception) {
                throw $msg;
            } else {
                throw new Exception($msg);
            }
        }
*/
        $l = self::$_level[self::STOP];
        if (false!==$l && (is_array($l) && in_array($level, $l) || $l>=$level)) {
            static::dumpLog();
            die;
        }

        return isset($id) ? $id : null;
    }

    public static function alert($msg, $stackPop=0)
    {
        self::i()->collectError($msg);
        return self::trigger(self::ALERT, $msg, $stackPop+1);
    }

    public static function critical($msg, $stackPop=0)
    {
        self::i()->collectError($msg);
        return self::trigger(self::CRITICAL, $msg, $stackPop+1);
    }

    public static function error($msg, $stackPop=0)
    {
        self::i()->collectError($msg);
        return self::trigger(self::ERROR, $msg, $stackPop+1);
    }

    public static function warning($msg, $stackPop=0)
    {
        self::i()->collectError($msg);
        return self::trigger(self::WARNING, $msg, $stackPop+1);
    }

    public static function notice($msg, $stackPop=0)
    {
        self::i()->collectError($msg);
        return self::trigger(self::NOTICE, $msg, $stackPop+1);
    }

    public static function info($msg, $stackPop=0)
    {
        self::i()->collectError($msg);
        return self::trigger(self::INFO, $msg, $stackPop+1);
    }

    public function collectError($msg, $type=self::ERROR)
    {
        self::$_collectedErrors[$type][] = $msg;
    }

    public function getCollectedErrors($type=self::ERROR)
    {
        if (!empty(self::$_collectedErrors[$type])) {
            return self::$_collectedErrors[$type];
        }
    }

    public static function debug($msg, $stackPop=0)
    {
        if ('DEBUG'!==self::$_mode) return; // to speed things up
        return self::trigger(self::DEBUG, $msg, $stackPop+1);
    }

    public static function profile($id)
    {
        if ($id && !empty(self::$_events[$id])) {
            self::$_events[$id]['d'] = microtime(true)-self::$_startTime-self::$_events[$id]['t'];
            self::$_events[$id]['c']++;
        }
    }

    public static function is($modes)
    {
        if (is_string($modes)) $modes = explode(',', $modes);
        return in_array(self::$_mode, $modes);
    }

    public static function dumpLog($return=false)
    {
        if ((self::$_mode!==self::MODE_DEBUG && self::$_mode!==self::MODE_DEVELOPMENT)
            || BResponse::i()->contentType()!=='text/html'
            || BRequest::i()->xhr()
        ) {
            return;
        }
        ob_start();
?><style>
#buckyball-debug-trigger { position:fixed; top:0; right:0; font:normal 10px Verdana; cursor:pointer; z-index:999999; background:#ffc; }
#buckyball-debug-console { position:fixed; overflow:auto; top:10px; left:10px; bottom:10px; right:10px; border:solid 2px #f00; padding:4px; text-align:left; opacity:1; background:#FFC; font:normal 10px Verdana; z-index:20000; }
#buckyball-debug-console table { border-collapse: collapse; }
#buckyball-debug-console th, #buckyball-debug-console td { font:normal 10px Verdana; border: solid 1px #ccc; padding:2px 5px;}
#buckyball-debug-console th { font-weight:bold; }
</style>
<div id="buckyball-debug-trigger" onclick="var el=document.getElementById('buckyball-debug-console');el.style.display=el.style.display?'':'none'">[DBG]</div>
<div id="buckyball-debug-console" style="display:none"><?php
        echo "DELTA: ".BDebug::i()->delta().', PEAK: '.memory_get_peak_usage(true).', EXIT: '.memory_get_usage(true);
        echo "<pre>";
        print_r(BORM::get_query_log());
        //BEvents::i()->debug();
        echo "</pre>";
        //print_r(self::$_events);
?><table cellspacing="0"><tr><th>Message</th><th>Rel.Time</th><th>Profile</th><th>Memory</th><th>Level</th><th>Relevant Location</th><th>Module</th></tr><?php
        foreach (self::$_events as $e) {
            if (empty($e['file'])) { $e['file'] = ''; $e['line'] = ''; }
            $profile = $e['d'] ? number_format($e['d'], 6).($e['c']>1 ? ' ('.$e['c'].')' : '') : '';
            echo "<tr><td><xmp style='margin:0'>".$e['msg']."</xmp></td><td>".number_format($e['t'], 6)."</td><td>".$profile."</td><td>".number_format($e['mem'], 0)."</td><td>{$e['level']}</td><td>{$e['file']}:{$e['line']}</td><td>".(!empty($e['module'])?$e['module']:'')."</td></tr>";
        }
?></table></div><?php
        $html = ob_get_clean();
        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }

    /**
    * Delta time from start
    *
    * @return float
    */
    public static function delta()
    {
        return microtime(true)-self::$_startTime;
    }

    public static function dump($var)
    {
        if (is_array($var) && current($var) instanceof Model) {
            foreach ($var as $k=>$v) {
                echo '<hr>'.$k.':';
                static::dump($v);
            }
        } elseif ($var instanceof Model) {
            echo '<pre>'; print_r($var->as_array()); echo '</pre>';
        } else {
            echo '<pre>'; print_r($var); echo '</pre>';
        }
    }

    public static function afterOutput($args)
    {
        static::dumpLog();
        //$args['content'] = str_replace('</body>', static::dumpLog(true).'</body>', $args['content']);
    }
}

/**
* Facility to handle l10n and i18n
*/
class BLocale extends BClass
{
    static protected $_domainPrefix = 'fulleron/';
    static protected $_domainStack = array();

    static protected $_defaultLanguage = 'en_US';
    static protected $_currentLanguage;

    static protected $_transliterateMap = array(
        '&amp;' => 'and',   '@' => 'at',    '©' => 'c', '®' => 'r', 'À' => 'a',
        'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Å' => 'a', 'Æ' => 'ae','Ç' => 'c',
        'È' => 'e', 'É' => 'e', 'Ë' => 'e', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i',
        'Ï' => 'i', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
        'Ø' => 'o', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Ý' => 'y',
        'ß' => 'ss','à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
        'æ' => 'ae','ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
        'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'p', 'ÿ' => 'y', 'Ā' => 'a',
        'ā' => 'a', 'Ă' => 'a', 'ă' => 'a', 'Ą' => 'a', 'ą' => 'a', 'Ć' => 'c',
        'ć' => 'c', 'Ĉ' => 'c', 'ĉ' => 'c', 'Ċ' => 'c', 'ċ' => 'c', 'Č' => 'c',
        'č' => 'c', 'Ď' => 'd', 'ď' => 'd', 'Đ' => 'd', 'đ' => 'd', 'Ē' => 'e',
        'ē' => 'e', 'Ĕ' => 'e', 'ĕ' => 'e', 'Ė' => 'e', 'ė' => 'e', 'Ę' => 'e',
        'ę' => 'e', 'Ě' => 'e', 'ě' => 'e', 'Ĝ' => 'g', 'ĝ' => 'g', 'Ğ' => 'g',
        'ğ' => 'g', 'Ġ' => 'g', 'ġ' => 'g', 'Ģ' => 'g', 'ģ' => 'g', 'Ĥ' => 'h',
        'ĥ' => 'h', 'Ħ' => 'h', 'ħ' => 'h', 'Ĩ' => 'i', 'ĩ' => 'i', 'Ī' => 'i',
        'ī' => 'i', 'Ĭ' => 'i', 'ĭ' => 'i', 'Į' => 'i', 'į' => 'i', 'İ' => 'i',
        'ı' => 'i', 'Ĳ' => 'ij','ĳ' => 'ij','Ĵ' => 'j', 'ĵ' => 'j', 'Ķ' => 'k',
        'ķ' => 'k', 'ĸ' => 'k', 'Ĺ' => 'l', 'ĺ' => 'l', 'Ļ' => 'l', 'ļ' => 'l',
        'Ľ' => 'l', 'ľ' => 'l', 'Ŀ' => 'l', 'ŀ' => 'l', 'Ł' => 'l', 'ł' => 'l',
        'Ń' => 'n', 'ń' => 'n', 'Ņ' => 'n', 'ņ' => 'n', 'Ň' => 'n', 'ň' => 'n',
        'ŉ' => 'n', 'Ŋ' => 'n', 'ŋ' => 'n', 'Ō' => 'o', 'ō' => 'o', 'Ŏ' => 'o',
        'ŏ' => 'o', 'Ő' => 'o', 'ő' => 'o', 'Œ' => 'oe','œ' => 'oe','Ŕ' => 'r',
        'ŕ' => 'r', 'Ŗ' => 'r', 'ŗ' => 'r', 'Ř' => 'r', 'ř' => 'r', 'Ś' => 's',
        'ś' => 's', 'Ŝ' => 's', 'ŝ' => 's', 'Ş' => 's', 'ş' => 's', 'Š' => 's',
        'š' => 's', 'Ţ' => 't', 'ţ' => 't', 'Ť' => 't', 'ť' => 't', 'Ŧ' => 't',
        'ŧ' => 't', 'Ũ' => 'u', 'ũ' => 'u', 'Ū' => 'u', 'ū' => 'u', 'Ŭ' => 'u',
        'ŭ' => 'u', 'Ů' => 'u', 'ů' => 'u', 'Ű' => 'u', 'ű' => 'u', 'Ų' => 'u',
        'ų' => 'u', 'Ŵ' => 'w', 'ŵ' => 'w', 'Ŷ' => 'y', 'ŷ' => 'y', 'Ÿ' => 'y',
        'Ź' => 'z', 'ź' => 'z', 'Ż' => 'z', 'ż' => 'z', 'Ž' => 'z', 'ž' => 'z',
        'ſ' => 'z', 'Ə' => 'e', 'ƒ' => 'f', 'Ơ' => 'o', 'ơ' => 'o', 'Ư' => 'u',
        'ư' => 'u', 'Ǎ' => 'a', 'ǎ' => 'a', 'Ǐ' => 'i', 'ǐ' => 'i', 'Ǒ' => 'o',
        'ǒ' => 'o', 'Ǔ' => 'u', 'ǔ' => 'u', 'Ǖ' => 'u', 'ǖ' => 'u', 'Ǘ' => 'u',
        'ǘ' => 'u', 'Ǚ' => 'u', 'ǚ' => 'u', 'Ǜ' => 'u', 'ǜ' => 'u', 'Ǻ' => 'a',
        'ǻ' => 'a', 'Ǽ' => 'ae','ǽ' => 'ae','Ǿ' => 'o', 'ǿ' => 'o', 'ə' => 'e',
        'Ё' => 'jo','Є' => 'e', 'І' => 'i', 'Ї' => 'i', 'А' => 'a', 'Б' => 'b',
        'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ж' => 'zh','З' => 'z',
        'И' => 'i', 'Й' => 'j', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
        'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
        'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch','Ш' => 'sh','Щ' => 'sch',
        'Ъ' => '-', 'Ы' => 'y', 'Ь' => '-', 'Э' => 'je','Ю' => 'ju','Я' => 'ja',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ж' => 'zh','з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l',
        'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
        'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh','щ' => 'sch','ъ' => '-','ы' => 'y', 'ь' => '-', 'э' => 'je',
        'ю' => 'ju','я' => 'ja','ё' => 'jo','є' => 'e', 'і' => 'i', 'ї' => 'i',
        'Ґ' => 'g', 'ґ' => 'g', 'א' => 'a', 'ב' => 'b', 'ג' => 'g', 'ד' => 'd',
        'ה' => 'h', 'ו' => 'v', 'ז' => 'z', 'ח' => 'h', 'ט' => 't', 'י' => 'i',
        'ך' => 'k', 'כ' => 'k', 'ל' => 'l', 'ם' => 'm', 'מ' => 'm', 'ן' => 'n',
        'נ' => 'n', 'ס' => 's', 'ע' => 'e', 'ף' => 'p', 'פ' => 'p', 'ץ' => 'C',
        'צ' => 'c', 'ק' => 'q', 'ר' => 'r', 'ש' => 'w', 'ת' => 't', '™' => 'tm',
    );

    /**
    * Default timezone
    *
    * @var string
    */
    protected $_defaultTz = 'America/Los_Angeles';

    /**
    * Default locale
    *
    * @var string
    */
    protected $_defaultLocale = 'en_US';

    /**
    * Cache for DateTimeZone objects
    *
    * @var DateTimeZone
    */
    protected $_tzCache = array();

    /**
    * Translations tree
    *
    * static::$_tr = array(
    *   'STRING1' => 'DEFAULT TRANSLATION',
    *   'STRING2' => array(
    *      '_' => 'DEFAULT TRANSLATION',
    *      'Module1' => 'MODULE1 TRANSLATION',
    *      'Module2' => 'MODULE2 TRANSLATION',
    *      ...
    *   ),
    * );
    *
    * @var array
    */
    protected static $_tr;

    /**
     * Shortcut to help with IDE autocompletion
     *
     * @param bool  $new
     * @param array $args
     * @return BLocale
     */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    /**
    * Constructor, set default timezone and locale
    *
    */
    public function __construct()
    {
        date_default_timezone_set($this->_defaultTz);
        setlocale(LC_ALL, $this->_defaultLocale);
        $this->_tzCache['GMT'] = new DateTimeZone('GMT');
    }

    public static function transliterate($str, $filler='-')
    {
        return strtolower(trim(preg_replace('#[^0-9a-z]+#i', $filler,
            strtr($str, static::$_transliterateMap)), $filler));
    }

    public static function setCurrentLanguage($lang)
    {
        self::$_currentLanguage = $lang;
    }

    public static function getCurrentLanguage()
    {
        if (empty(static::$_currentLanguage)) {
            static::$_currentLanguage = static::$_defaultLanguage;
        }
        return static::$_currentLanguage;
    }

    /**
    * Import translations to the tree
    *
    * @todo make more flexible with file location
    * @todo YAML
    * @param mixed $data array or file name string
    */
    public static function importTranslations($data, $params=array())
    {
        $module = !empty($params['_module']) ? $params['_module'] : BModuleRegistry::i()->currentModuleName();
        if (is_string($data)) {
            if (!BUtil::isPathAbsolute($data)) {
                $data = BApp::m($module)->root_dir.'/i18n/'.$data;
            }

            if (is_readable($data)) {
                $extension = !empty($params['extension']) ? $params['extension'] : 'csv';
                switch ($extension) {
                    case 'csv':
                        $fp = fopen($data, 'r');
                        while (($r = fgetcsv($fp, 2084))) {
                            static::addTranslation($r, $module);
                        }
                        fclose($fp);
                        break;

                    case 'json':
                        $content = file_get_contents($data);
                        $translations = BUtil::fromJson($content);
                        foreach ($translations as $word => $tr) {
                            static::addTranslation(array($word,$tr), $module);
                        }
                        break;

                    case 'php':
                        $translations = include $data;
                        foreach ($translations as $word => $tr) {
                            static::addTranslation(array($word,$tr), $module);
                        }
                        break;

                    case 'po':
                        //TODO: implement https://github.com/clinisbut/PHP-po-parser
                        $contentLines = file($data);
                        $translations = array();
                        $mode = null;
                        foreach ($contentLines as $line) {
                            $line = trim($line);
                            if ($line[0]==='"') {
                                $cmd = '+'.$mode;
                                $str = $line;
                            } else {
                                list($cmd, $str) = explode(' ', $line, 2);
                            }
                            $str = preg_replace('/(^\s*"|"\s*$)/', '', $str);
                            switch ($cmd) {
                                case 'msgid': $msgid = $str; $mode = $cmd; $translations[$msgid] = ''; break;
                                case '+msgid': $msgid .= $str; break;
                                case 'msgstr': $mode = $cmd; $translations[$msgid] = $str; break;
                                case '+msgstr': $translations[$msgid] .= $str; break;
                            }
                        }
                        break;
                }
            } else {
                BDebug::warning('Could not load translation file: '.$data);
                return;
            }
        } elseif (is_array($data)) {
            foreach ($data as $r) {
                static::addTranslation($r, $module);
            }
        }
    }

    /**
     * Collect all translation keys & values start from $rootDir and save into $targetFile
     * @param string $rootDir - start directory to look for translation calls BLocale::_
     * @param string $targetFile - output file which contain translation values
     * @return boolean - TRUE on success
     * @example BLocale::collectTranslations('/www/unirgy/fulleron/FCom/Disqus', '/www/unirgy/fulleron/FCom/Disqus/tr.csv');
     */
    static public function collectTranslations($rootDir, $targetFile)
    {
        //find files recursively
        $files = self::getFilesFromDir($rootDir);
        if (empty($files)) {
            return true;
        }

        //find all BLocale::_ calls and extract first parameter - translation key
        $keys = array();
        foreach($files as $file) {
            $source = file_get_contents($file);
            $tokens = token_get_all($source);
            $func = 0;
            $class = 0;
            $sep = 0;
            foreach($tokens as $token) {
                if (empty($token[1])){
                    continue;
                }
                if ($token[1] =='BLocale') {
                    $class = 1;
                    continue;
                }
                if ($class && $token[1] == '::') {
                    $class = 0;
                    $sep = 1;
                    continue;
                }
                if ($sep && $token[1] == '_') {
                    $sep = 0;
                    $func = 1;
                    continue;
                }
                if($func) {
                    $token[1] = trim($token[1], "'");
                    $keys[$token[1]] = '';
                    $func = 0;
                    continue;
                }
            }
        }

        //import translation from $targetFile

        self::$_tr = '';
        self::addTranslationsFile($targetFile);
        $translations = self::getTranslations();

        //find undefined translations
        foreach ($keys as $key => $v) {
            if(isset($translations[$key])) {
                unset($keys[$key]);
            }
        }
        //add undefined translation to $targetFile
        $newtranslations = array();
        if ($translations) {
            foreach($translations as $trkey => $tr){
                list(,$newtranslations[$trkey]) = each($tr);
            }
        }
        $newtranslations = array_merge($newtranslations, $keys);

        $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'php':
                static::saveToPHP($targetFile, $newtranslations);
                break;
            case 'csv':
                static::saveToCSV($targetFile, $newtranslations);
                break;
            case 'json':
                static::saveToJSON($targetFile, $newtranslations);
                break;
            case 'po':
                static::saveToJSON($targetFile, $newtranslations);
            default:
                throw new Exception("Undefined format of translation targetFile. Possible formats are: json/csv/php");
        }

    }

    static protected function saveToPHP($targetFile, $array)
    {
        $code = '';
        foreach($array as $k => $v) {
            if (!empty($code)) {
                $code .= ','."\n";
            }
            $code .= "'{$k}' => '".addslashes($v)."'";
        }
        $code = "<?php return array({$code});";
        file_put_contents($targetFile, $code);
    }

    static protected function saveToJSON($targetFile, $array)
    {
        $json = json_encode($array);
        file_put_contents($targetFile, $json);
    }

    static protected function saveToCSV($targetFile, $array)
    {
        $handle = fopen($targetFile, "w");
        foreach ($array as $k => $v) {
            $k = trim($k, '"');
            fputcsv($handle, array($k, $v));
        }
        fclose($handle);
    }

    static protected function saveToPO($targetFile, $array)
    {
        $handle = fopen($targetFile, "w");
        foreach ($array as $k => $v) {
            $v = str_replace("\n", '\n', $v);
            fwrite($handle, "msgid \"{$k}\"\nmsgstr \"{$v}\"\n\n");
        }
        fclose($handle);
    }

    static public function getFilesFromDir($dir)
    {
        $files = array();
        if (false !== ($handle = opendir($dir))) {
            while (false !== ($file = readdir($handle))) {
                if ($file != "." && $file != "..") {
                    if(is_dir($dir.'/'.$file)) {
                        $dir2 = $dir.'/'.$file;
                        $files = array_merge($files, self::getFilesFromDir($dir2));
                    }
                    else {
                        $files[] = $dir.'/'.$file;
                    }
                }
            }
            closedir($handle);
        }

        return $files;
    }

    static public function addTranslationsFile($file)
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (empty($ext)) {
            return;
        }
        $params['extension'] = $ext;
        self::importTranslations($file, $params);
    }

    protected static function addTranslation($r, $module=null)
    {
        if (empty($r[1])) {
            BDebug::debug('No translation specified for '.$r[0]);
            return;
        }
        // short and quick way
        static::$_tr[ $r[0] ][ !empty($module) ? $module : '_' ] = $r[1];

        /*
        // a bit of memory saving way
        list($from, $to) = $r;
        if (!empty($module)) { // module supplied
            if (!empty(static::$_tr[$from]) && is_string(static::$_tr[$from])) { // only default translation present
                static::$_tr[$from] = array('_'=>static::$_tr[$from]); // convert to array
            }
            static::$_tr[$from][$module] = $to; // save module specific translation
        } else { // no module, default translation
            if (!empty(static::$_tr[$from]) && is_array(static::$_tr[$from])) { // modular translations present
                static::$_tr[$from]['_'] = $to; // play nice
            } else {
                static::$_tr[$from] = $to; // simple
            }
        }
        */
    }

    public static function cacheSave()
    {

    }

    public static function cacheLoad()
    {

    }

    public static function _($string, $params=array(), $module=null)
    {
        if (empty(static::$_tr[$string])) { // if no translation at all
            $tr = $string; // return original string
        } else { // if some translation present
            $arr = static::$_tr[$string];
            if (!empty($module) && !empty($arr[$module])) { // if module requested and translation for it present
                $tr = $arr[$module]; // use it
            } elseif (!empty($arr['_'])) { // otherwise, if there's default translation
                $tr = $arr['_']; // use it
            } else { // otherwise
                reset($arr); // find the first available translation
                $tr = current($arr); // and use it
            }
        }

        return BUtil::sprintfn($tr, $params);
    }

    /*
    public function language($lang=null)
    {
        if (is_null($lang)) {
            return $this->_curLang;
        }
        putenv('LANGUAGE='.$lang);
        putenv('LANG='.$lang);
        setlocale(LC_ALL, $lang.'.utf8', $lang.'.UTF8', $lang.'.utf-8', $lang.'.UTF-8');
        return $this;
    }

    public function module($domain, $file=null)
    {
        if (is_null($file)) {
            if (!is_null($domain)) {
                $domain = static::$_domainPrefix.$domain;
                $oldDomain = textdomain(null);
                if ($oldDomain) {
                    array_push(static::$_domainStack, $domain!==$oldDomain ? $domain : false);
                }
            } else {
                $domain = array_pop(static::$_domainStack);
            }
            if ($domain) {
                textdomain($domain);
            }
        } else {
            $domain = static::$_domainPrefix.$domain;
            bindtextdomain($domain, $file);
            bind_textdomain_codeset($domain, "UTF-8");
        }
        return $this;
    }
    */

    /**
    * Translate a string and inject optionally named arguments
    *
    * @param string $string
    * @param array $args
    * @return string|false
    */
    /*
    public function translate($string, $args=array(), $domain=null)
    {
        if (!is_null($domain)) {
            $string = dgettext($domain, $string);
        } else {
            $string = gettext($string);
        }
        return BUtil::sprintfn($string, $args);
    }
    */

    /**
    * Get server timezone
    *
    * @return string
    */
    public function serverTz()
    {
        return date('e'); // Examples: UTC, GMT, Atlantic/Azores
    }

    /**
    * Get timezone offset in seconds
    *
    * @param stirng|null $tz If null, return server timezone offset
    * @return int
    */
    public function tzOffset($tz=null)
    {
        if (is_null($tz)) { // Server timezone
            return date('O') * 36; //  x/100*60*60; // Seconds from GMT
        }
        if (empty($this->_tzCache[$tz])) {
            $this->_tzCache[$tz] = new DateTimeZone($tz);
        }
        return $this->_tzCache[$tz]->getOffset($this->_tzCache['GMT']);
    }

    /**
    * Convert local datetime to DB (GMT)
    *
    * @param string $value
    * @return string
    */
    public function datetimeLocalToDb($value)
    {
        if (is_array($value)) {
            return array_map(array($this, __METHOD__), $value);
        }
        if (!$value) return $value;
        return gmstrftime('%F %T', strtotime($value));
    }

    /**
    * Parse user formatted dates into db style within object or array
    *
    * @param array|object $request fields to be parsed
    * @param null|string|array $fields if null, all fields will be parsed, if string, will be split by comma
    * @return array|object clone of $request with parsed dates
    */
    public function parseRequestDates($request, $fields=null)
    {
        if (is_string($fields)) $fields = explode(',', $fields);
        $isObject = is_object($request);
        if ($isObject) $result = clone $request;
        foreach ($request as $k=>$v) {
            if (is_null($fields) || in_array($k, $fields)) {
                $r = $this->datetimeLocalToDb($v);
            } else {
                $r = $v;
            }
            if ($isObject) $result->$k = $r; else $result[$k] = $r;
        }
        return $result;
    }

    /**
    * Convert DB datetime (GMT) to local
    *
    * @param string $value
    * @param bool $full Full format or short
    * @return string
    */
    public function datetimeDbToLocal($value, $full=false)
    {
        return strftime($full ? '%c' : '%x', strtotime($value));
    }

    static public function getTranslations()
    {
        return self::$_tr;
    }

    static protected $_currencySymbolMap = array(
        'USD' => '$',
        'EUR' => '',
        'GBP' => '',
    );
    static protected $_currencyCode = 'USD';
    static protected $_currencySymbol = '$';

    static public function setCurrency($code, $symbol = null)
    {
        static::$_currencyCode = $code;
        if (is_null($symbol)) {
            if (!empty(static::$_currencySymbolMap[$code])) {
                $symbol = static::$_currencySymbolMap[$code];
            } else {
                $symbol = $code.' ';
            }
        }
        static::$_currencySymbol = $symbol;
    }

    static public function currency($value, $decimals = 2)
    {
        return sprintf('%s%s', static::$_currencySymbol, number_format($value, $decimals));
    }
}


class BFtpClient extends BClass
{
    protected $_ftpDirMode = 0775;
    protected $_ftpFileMode = 0664;
    protected $_ftpHost = '';
    protected $_ftpPort = 21;
    protected $_ftpUsername = '';
    protected $_ftpPassword = '';

    public function __construct($config)
    {
        if (!empty($config['hostname'])) {
            $this->_ftpHost = $config['hostname'];
        }
        if (!empty($config['port'])) {
            $this->_ftpPort = $config['port'];
        }
        if (!empty($config['username'])) {
            $this->_ftpUsername = $config['username'];
        }
        if (!empty($config['password'])) {
            $this->_ftpPassword = $config['password'];
        }
    }

    public function upload($from, $to)
    {
        if (!extension_loaded('ftp')) {
            new BException('FTP PHP extension is not installed');
        }

        if (!($conn = ftp_connect($this->_ftpHost, $this->_ftpPort))) {
            throw new BException('Could not connect to FTP host');
        }

        if (!@ftp_login($conn, $this->_ftpUsername, $this->_ftpPassword)) {
            ftp_close($conn);
            throw new BException('Could not login to FTP host');
        }

        if (!ftp_chdir($conn, $to)) {
            ftp_close($conn);
            throw new BException('Could not navigate to '. $to);
        }

        $errors = $this->uploadDir($conn, $from.'/');
        ftp_close($conn);

        return $errors;
    }

    public function uploadDir($conn, $source, $ftpPath='')
    {
        $errors = array();
        $dir = opendir($source);
        while ($file = readdir($dir)) {
            if ($file=='.' || $file=="..") {
                continue;
            }

            if (!is_dir($source.$file)) {
                if (@ftp_put($conn, $file, $source.$file, FTP_BINARY)) {
                    // all is good
                    #ftp_chmod($conn, $this->_ftpFileMode, $file);
                } else {
                    $errors[] = ftp_pwd($conn).'/'.$file;
                }
                continue;
            }
            if (@ftp_chdir($conn, $file)) {
                // all is good
            } elseif (@ftp_mkdir($conn, $file)) {
                ftp_chmod($conn, $this->_ftpDirMode, $file);
                ftp_chdir($conn, $file);
            } else {
                $errors[] = ftp_pwd($conn).'/'.$file.'/';
                continue;
            }
            $errors += $this->uploadDir($conn, $source.$file.'/', $ftpPath.$file.'/');
            ftp_chdir($conn, '..');
        }
        return $errors;
    }
}

/**
* Throttle invalid login attempts and potentially notify user and admin
*
* Usage:
* - BEFORE AUTH: BLoginThrottle::i()->init('FCom_Customer_Model_Customer', $username);
* - ON FAILURE:  BLoginThrottle::i()->failure();
* - ON SUCCESS:  BloginThrottle::i()->success();
*/
class BLoginThrottle extends BClass
{
    protected $_all;
    protected $_area;
    protected $_username;
    protected $_rec;
    protected $_config;
    protected $_blockedIPs = array();
    protected $_cachePrefix = 'BLoginThrottle/';

    /**
    * Shortcut to help with IDE autocompletion
    *
    * @return BLoginThrottle
    */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function __construct()
    {
        $c = BConfig::i()->get('modules/BLoginThrottle');

        if (empty($c['sleep_sec'])) $c['sleep_sec'] = 2; // lock record for 2 secs after failed login
        if (empty($c['brute_attempts_max'])) $c['brute_attempts_max'] = 3; // after 3 fast attempts do something
        if (empty($c['reset_time'])) $c['reset_time'] = 10; // after 10 secs reset record

        $this->_config = $c;
    }

    public function config($config)
    {
        $this->_config = BUtil::arrayMerge($this->_config, $config);
    }

    public function init($area, $username)
    {
        $now = time();
        $c = $this->_config;

        $this->_area = $area;
        $this->_username = $username;
        $this->_rec = $this->_load();

        if ($this->_rec) {
            if ($this->_rec['status'] === 'FAILED') {
                if (empty($this->_rec['brute_attempts_cnt'])) {
                    $this->_rec['brute_attempts_cnt'] = 1;
                } else {
                    $this->_rec['brute_attempts_cnt']++;
                }
                $this->_save();
                $this->_fire('init:brute');
                if ($this->_rec['brute_attempts_cnt'] == $c['brute_attempts_max']) {
                    $this->_fire('init:brute_max');
                }
                return false; // currently locked
            }
        }
        return true; // init OK
    }

    public function success()
    {
        $this->_fire('success');
        $this->_reset();
        return true;
    }

    public function failure()
    {
        $username = $this->_username;
        $now = time();
        $c = $this->_config;

        $this->_fire('fail:before');

        if (empty($this->_rec['attempt_cnt'])) {
            $this->_rec['attempt_cnt'] = 1;
        } else {
            $this->_rec['attempt_cnt']++;
        }
        $this->_rec['last_attempt'] = $now;
        $this->_rec['status'] = 'FAILED';
        $this->_save();
        $this->_fire('fail:wait');

        $this->_gc();
        sleep($c['sleep_sec']);

        $this->_rec['status'] = '';
        $this->_save();
        $this->_fire('fail:after');

        return true; // normal response
    }

    protected function _fire($event)
    {
        BEvents::i()->fire('BLoginThrottle::'.$event, array(
            'area'     => $this->_area,
            'username' => $this->_username,
            'rec'      => $this->_rec,
            'config'   => $this->_config,
        ));
    }

    protected function _load()
    {
        $key = $this->_area.'/'.$this->_username;
        return BCache::i()->load($this->_cachePrefix.$key);
    }

    protected function _save()
    {
        $key = $this->_area.'/'.$this->_username;
        return BCache::i()->save($this->_cachePrefix.$key, $this->_rec, $this->_config['reset_time']);
    }

    protected function _reset()
    {
        $key = $this->_area.'/'.$this->_username;
        return BCache::i()->delete($key);
    }

    protected function _gc()
    {

        return true;
    }
}

/**
* Falls back to pecl extensions: yaml, syck
* Uses libraries: spyc, symphony\yaml (not included)
*/
class BYAML extends BCLass
{
    static protected $_peclYaml = null;
    static protected $_peclSyck = null;

    static public function bootstrap()
    {

    }

    static public function load($filename, $cache=true)
    {
        $filename1 = realpath($filename);
        if (!$filename1) {
            BDebug::debug('BCache load: file does not exist: '.$filename);
            return false;
        }
        $filename = $filename1;

        $filemtime = filemtime($filename);

        if ($cache) {
            $cacheData = BCache::i()->load('BYAML--'.$filename);
            if (!empty($cacheData) && !empty($cacheData['v']) && $cacheData['v'] === $filemtime) {
                return $cacheData['d'];
            }
        }

        $yamlData = file_get_contents($filename);
        $yamlData = str_replace("\t", '    ', $yamlData); //TODO: make configurable tab size
        $arrayData = static::parse($yamlData);

        if ($cache) {
            BCache::i()->save('BYAML--'.$filename, array('v'=>$filemtime, 'd'=>$arrayData), false);
        }

        return $arrayData;
    }

    static public function init()
    {
        if (is_null(static::$_peclYaml)) {
            static::$_peclYaml = function_exists('yaml_parse');

            if (!static::$_peclYaml) {
                static::$_peclSyck = function_exists('syck_load');
            }

            if (!static::$_peclYaml && !static::$_peclSyck) {
                require_once(__DIR__.'/lib/spyc.php');
                /*
                require_once(__DIR__.'/Yaml/Exception/ExceptionInterface.php');
                require_once(__DIR__.'/Yaml/Exception/RuntimeException.php');
                require_once(__DIR__.'/Yaml/Exception/DumpException.php');
                require_once(__DIR__.'/Yaml/Exception/ParseException.php');
                require_once(__DIR__.'/Yaml/Yaml.php');
                require_once(__DIR__.'/Yaml/Parser.php');
                require_once(__DIR__.'/Yaml/Dumper.php');
                require_once(__DIR__.'/Yaml/Escaper.php');
                require_once(__DIR__.'/Yaml/Inline.php');
                require_once(__DIR__.'/Yaml/Unescaper.php');
                */
            }
        }
        return true;
    }

    static public function parse($yamlData)
    {
        static::init();

        if (static::$_peclYaml) {
            return yaml_parse($yamlData);
        } elseif (static::$_peclSyck) {
            return syck_load($yamlData);
        }

        if (class_exists('Spyc', false)) {
            return Spyc::YAMLLoadString($yamlData);
        } else {
            return Symfony\Component\Yaml\Yaml::parse($yamlData);
        }
    }

    static public function dump($arrayData)
    {
        static::init();

        if (static::$_peclYaml) {
            return yaml_emit($arrayData);
        } elseif (static::$_peclSyck) {
            return syck_dump($arrayData);
        }

        if (class_exists('Spyc', false)) {
            return Spyc::YAMLDump($arrayData);
        } else {
            return Symfony\Component\Yaml\Yaml::dump($arrayData);
        }
    }
}

class BValidate extends BClass
{
    protected $_reRegex = '#^([/\#~&,%])(.*)(\1)[imsxADSUXJu]*$#';
    protected $_defaultRules = array(
        'required' => array(
            'rule'    => 'BValidate::ruleRequired',
            'message' => 'Missing field: :field',
        ),
        'url'       => array(
            'rule'    => '#(([\w]+:)?//)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(\#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?#',
            'message' => 'Invalid URL',
        ),
        'email'     => array(
            'rule'    => '/^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/',
            'message' => 'Invalid Email',
        ),
        'numeric'   => array(
            'rule'    => '/^([+-]?)([0-9 ]+)(\.?,?)([0-9]*)$/',
            'message' => 'Invalid number: :field',
        ),
        'integer'   => array(
            'rule'    => '/^[+-][0-9]+$/',
            'message' => 'Invalid integer: :field',
        ),
        'alphanum'  => array(
            'rule'    => '/^[a-zA-Z0-9 ]+$/',
            'message' => 'Invalid alphanumeric: :field',
        ),
        'alpha'  => array(
            'rule'    => '/^[a-zA-Z ]+$/',
            'message' => 'Invalid alphabet field: :field',
        ),
        'password_confirm' => array(
            'rule'    => 'BValidate::rulePasswordConfirm',
            'message' => 'Password confirmation does not match',
            'args'    => array('original' => 'password'),
        ),
    );

    protected $_defaultMessage = "Validation failed for: :field";
    protected $_expandedRules = array();

    protected $_validateErrors = array();

    public function addValidator($name, $rule)
    {
        $this->_defaultRules[$name] = $rule;
        return $this;
    }

    protected function _expandRules($rules)
    {
        $this->_expandedRules = array();
        foreach ($rules as $rule) {
            if (!empty($rule[0]) && !empty($rule[1])) {
                $r = $rule;
                $rule = array('field' => $r[0], 'rule' => $r[1]);
                if (isset($r[2])) $rule['message'] = $r[2];
                if (isset($r[3])) $rule['args'] = $r[3];
                if (isset($rule['args']) && is_string($rule['args'])) {
                    $rule['args'] = array($rule['args'] => true);
                }
            }
            if (is_string($rule['rule']) && $rule['rule'][0] === '@') {
                $ruleName = substr($rule['rule'], 1);
                if (empty($this->_defaultRules[$ruleName])) {
                    throw new BException('Invalid rule name: ' . $ruleName);
                }
                $defRule = $this->_defaultRules[$ruleName];
                $rule = BUtil::arrayMerge($defRule, $rule);
                $rule['rule'] = $defRule['rule'];
            }
            if(empty($rule['message'])) $rule['message'] = $this->_defaultMessage;
            $this->_expandedRules[] = $rule;
        }
    }

    protected function _validateRules($data)
    {
        $this->_validateErrors = array();
        foreach ($this->_expandedRules as $r) {
            $args = !empty($r['args']) ? $r['args'] : array();
            $r['args']['field'] = $r['field']; // for callback and message vars
            if (is_string($r['rule']) && preg_match($this->_reRegex, $r['rule'], $m)) {
                $result = empty($data[$r['field']]) || preg_match($m[0], $data[$r['field']]);
            } elseif($r['rule'] instanceof Closure){
                $result = $r['rule']($data, $r['args']);
            } elseif (is_callable($r['rule'])) {
                $result = BUtil::call($r['rule'], array($data, $r['args']), true);
            } else {
                throw new BException('Invalid rule: '.print_r($r['rule'], 1));
            }

            if (!$result) {
                $this->_validateErrors[$r['field']][] = BUtil::injectVars($r['message'], $r['args']);
                if (!empty($r['args']['break'])) {
                    break;
                }
            }
        }
    }

    /**
     * Validate passed data
     *
     * $data is an array of key value pairs.
     * Keys will be matched against rules.
     * <code>
     * // data
     * array (
     *  'firstname' => 'John',
     *  'lastname' => 'Doe',
     *  'email' => 'test@example.com',
     *  'url' => 'http://example.com/test?foo=bar#baz',
     *  'password' => '12345678',
     *  'password_confirm' => '12345678',
     * );
     *
     * // rules in format: ['field', 'rule', ['message'], [ 'break' | 'arg1' => 'val1' ] ]
     * $rules = array(
     *   array('email', '@required'),
     *   array('email', '@email'),
     *   array('url', '@url'),
     *   array('firstname', '@required', 'Missing First Name'),
     *   array('firstname', '/^[A-Za-z]+$/', 'Invalid First Name', 'break'),
     *   array('password', '@required', 'Missing Password'),
     *   array('password_confirm', '@password_confirm'),
     * );
     * </code>
     *
     * Rule can be either string that resolves to callback, regular expression or closure.
     * Allowed pattern delimiters for regular expression are: /\#~&,%
     * Allowed regular expression modifiers are: i m s x A D S U X J u
     * e and E modifiers are NOT allowed. Any exptression using them will not work.
     *
     * Callbacks can be either: Class::method for static method call or Class.method | Class->method for instance call
     *
     * @param array $data
     * @param array $rules
     * @param null  $formName
     * @return bool
     */
    public function validateInput($data, $rules, $formName = null)
    {
        $this->_expandRules($rules);

        $this->_validateRules($data);

        if ($this->_validateErrors && $formName) {
            BSession::i()->set('validator-data:' . $formName, $data);
            foreach ($this->_validateErrors as $field => $errors) {
                foreach ($errors as $error) {
                    $msg = compact('error', 'field');
                    BSession::i()->addMessage($msg, 'error', 'validator-errors:' . $formName);
                }
            }
        }
        return $this->_validateErrors ? false : true;
    }

    public function validateErrors()
    {
        return $this->_validateErrors;
    }

    static public function ruleRequired($data, $args)
    {
        return !empty($data[$args['field']]);
    }

    static public function rulePasswordConfirm($data, $args)
    {
        return empty($data[$args['original']])
            || !empty($data[$args['field']]) && $data[$args['field']] === $data[$args['original']];
    }
}

/**
 * Class BValidateViewHelper
 *
 *
 */
class BValidateViewHelper extends BClass
{
    protected $_errors = array();
    protected $_data = array();

    public function __construct($args)
    {
        if(!isset($args['form'])){
            return;
        }
        if (isset($args['data'])) {
            if (is_object($args['data'])) {
                $args['data'] = $args['data']->as_array();
            }
            $this->_data = $args['data'];
        }

        $sessionHlp = BSession::i();
        $errors     = $sessionHlp->messages('validator-errors:' . $args['form']);
        $formData   = $sessionHlp->get('validator-data:' . $args['form']);
        $this->_data = BUtil::arrayMerge($this->_data, $formData);
        $sessionHlp->set('validator-data:' . $args['form'], null);

        foreach ($errors as $error) {
            $field                 = $error['msg']['field'];
            $error['value']        = !empty($formData[$field]) ? $formData[$field] : null;
            $this->_errors[$field] = $error;
        }
    }

    public function fieldClass($field)
    {
        if (empty($this->_errors[$field]['type'])) {
            return '';
        }
        return $this->_errors[$field]['type'];
    }

    public function fieldValue($field)
    {
        return !empty($this->_data[$field]) ? $this->_data[$field] : null;
    }

    public function messageClass($field)
    {
        if (empty($this->_errors[$field]['type'])) {
            return '';
        }
        return $this->_errors[$field]['type'];
    }

    public function messageText($field)
    {
        if (empty($this->_errors[$field]['msg']['error'])) {
            return '';
        }
        return BLocale::_($this->_errors[$field]['msg']['error']);
    }

    /**
     * @param string $field form field name
     * @param string $fieldId form field ID
     * @return string
     */
    public function errorHtml($field, $fieldId)
    {
        $html = '';

        if(!empty($this->_errors[$field]['type'])){
            $html .= BUtil::tagHtml('label', array('for' => $fieldId, 'class' => $this->messageClass($field)),$this->messageText($field));
        }

        return $html;
    }
}

/**
 * If FISMA/FIPS/NIST compliance required, use PBKDF2
 *
 * @see http://stackoverflow.com/questions/4795385/how-do-you-use-bcrypt-for-hashing-passwords-in-php
 */
class Bcrypt extends BClass
{
    public function __construct()
    {
        if (CRYPT_BLOWFISH != 1) {
            throw new Exception("bcrypt not supported in this installation. See http://php.net/crypt");
        }
    }

    public function hash($input)
    {
        $hash = crypt($input, $this->getSalt());
        return strlen($hash) > 13 ? $hash : false;
    }

    public function verify($input, $existingHash)
    {
        // md5 for protection against timing side channel attack (needed)
        return md5(crypt($input, $existingHash)) === md5($existingHash);
    }

    private function getSalt()
    {
        // The security weakness between 5.3.7 affects password with 8-bit characters only
        // @see: http://php.net/security/crypt_blowfish.php
        $salt = '$' . (version_compare(phpversion(), '5.3.7', '>=') ? '2y' : '2a') . '$12$';
        $salt .= $this->encodeBytes($this->getRandomBytes(16));
        return $salt;
    }

    private $randomState;
    private function getRandomBytes($count)
    {
        $bytes = '';

        if (function_exists('openssl_random_pseudo_bytes') &&
            (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN')) { // OpenSSL slow on Win
            $bytes = openssl_random_pseudo_bytes($count);
        }

        if ($bytes === '' && is_readable('/dev/urandom') &&
            ($hRand = @fopen('/dev/urandom', 'rb')) !== FALSE) {
            $bytes = fread($hRand, $count);
            fclose($hRand);
        }

        if (strlen($bytes) < $count) {
            $bytes = '';

            if ($this->randomState === null) {
                $this->randomState = microtime();
                if (function_exists('getmypid')) {
                    $this->randomState .= getmypid();
                }
            }

            for ($i = 0; $i < $count; $i += 16) {
                $this->randomState = md5(microtime() . $this->randomState);

                $bytes .= md5($this->randomState, true);
            }

            $bytes = substr($bytes, 0, $count);
        }

        return $bytes;
    }

    private function encodeBytes($input)
    {
        // The following is code from the PHP Password Hashing Framework
        $itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        $output = '';
        $i = 0;
        do {
            $c1 = ord($input[$i++]);
            $output .= $itoa64[$c1 >> 2];
            $c1 = ($c1 & 0x03) << 4;
            if ($i >= 16) {
                $output .= $itoa64[$c1];
                break;
            }

            $c2 = ord($input[$i++]);
            $c1 |= $c2 >> 4;
            $output .= $itoa64[$c1];
            $c1 = ($c2 & 0x0f) << 2;

            $c2 = ord($input[$i++]);
            $c1 |= $c2 >> 6;
            $output .= $itoa64[$c1];
            $output .= $itoa64[$c2 & 0x3f];
        } while (1);

        return $output;
    }
}

class BRSA extends BClass
{
    protected $_configPath = 'modules/BRSA';
    protected $_config = array();
    protected $_publicKey;
    protected $_privateKey;
    protected $_cache = array();

    public function __construct()
    {
        if (!function_exists('openssl_pkey_new')) {
            // TODO: integrate Crypt_RSA ?
            throw new BException('RSA encryption requires openssl module installed');
        }
        $defConf = array(
            "digest_alg" => "sha512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        $conf = BConfig::i()->get($this->_configPath);
        $this->_config = array_merge($defConf, $conf);
    }

    public function generateKey()
    {
        $config = BUtil::arrayMask($this->_config, 'digest_alg,x509_extensions,req_extensions,'
            . 'private_key_bits,private_key_type,encrypt_key,encrypt_key_cipher');
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $this->_privateKey); // private key

        $pubKey = openssl_pkey_get_details($res); // public key
        $this->_publicKey = $pubKey["key"];

        BConfig::i()->set($this->_configPath.'/public_key', $this->_publicKey, false, true);

        file_put_contents($this->_getPrivateKeyFileName(), $this->_privateKey);

        return $this;
    }

    protected  function _getPublicKey()
    {
        if (!$this->_publicKey) {
            $this->_publicKey = BConfig::i()->get($this->_configPath.'/public_key');
            if (!$this->_publicKey) {
                throw new BException('No public key defined');
            }
        }
        return $this->_publicKey;
    }

    protected function _getPrivateKeyFileName()
    {
        $configDir = BConfig::i()->get('fs/config_dir');
        if (!$configDir) {
            $configDir = '.';
        }
        return $configDir . '/private-' . md5($this->_getPublicKey()) . '.key';
    }

    protected function _getPrivateKey()
    {
        if (!$this->_privateKey) {
            $filepath = $this->_getPrivateKeyFileName();
            if (!is_readable($filepath)) {
                throw new BException('No private key file found');
            }
            $this->_privateKey = file_get_contents($filepath);
        }
        return $this->_privateKey;
    }

    public function setPublicKey()
    {
        $this->_publicKey = $key;
        return $this;
    }

    public function setPrivateKey()
    {
        $this->_privateKey = $key;
        return $this;
    }

    public function encrypt($plain)
    {
        openssl_public_encrypt($plain, $encrypted, $this->_getPublicKey());
        return $encrypted;
    }

    /**
     * Decrypt data
     *
     * Use buckyball/ssl/offsite-decrypt.php script for much improved security
     *
     * @param string $encrypted
     * @return string
     */
    public function decrypt($encrypted)
    {
        $hash = sha1($encrypted);
        if (!empty($this->_cache[$hash])) {
            return $this->_cache[$hash];
        }
        // even though decrypt_url can potentially be overridden by extension, only encrypted data is sent over
        if (!empty($this->_config['decrypt_url'])) {
            $data = array('encrypted' => base64_encode($encrypted));
            $result = BUtil::remoteHttp('GET', $this->_config['decrypt_url'], $data);
            $decrypted = base64_decode($result);
            if (!empty($result['decrypted'])) {
                $decrypted = $result['decrypted'];
            } else {
                //TODO: handle exceptions
            }
        } else {
            openssl_private_decrypt($encrypted, $decrypted, $this->_getPrivateKey());
        }
        $this->_cache[$hash] = $decrypted;
        return $decrypted;
    }
}

if( !function_exists( 'xmlentities' ) ) {
    /**
     * @see http://www.php.net/manual/en/function.htmlentities.php#106535
     */
    function xmlentities( $string ) {
        $not_in_list = "A-Z0-9a-z\s_-";
        return preg_replace_callback( "/[^{$not_in_list}]/" , 'get_xml_entity_at_index_0' , $string );
    }
    function get_xml_entity_at_index_0( $CHAR ) {
        if( !is_string( $CHAR[0] ) || ( strlen( $CHAR[0] ) > 1 ) ) {
            die( "function: 'get_xml_entity_at_index_0' requires data type: 'char' (single character). '{$CHAR[0]}' does not match this type." );
        }
        switch( $CHAR[0] ) {
            case "'":    case '"':    case '&':    case '<':    case '>':
                return htmlspecialchars( $CHAR[0], ENT_QUOTES );    break;
            default:
                return numeric_entity_4_char($CHAR[0]);                break;
        }
    }
    function numeric_entity_4_char( $char ) {
        return "&#".str_pad(ord($char), 3, '0', STR_PAD_LEFT).";";
    }
}

if (!function_exists('password_hash')) {
    /**
     * If FISMA/FIPS/NIST compliance required, use PBKDF2
     *
     * @see http://stackoverflow.com/questions/4795385/how-do-you-use-bcrypt-for-hashing-passwords-in-php
     */
    function password_hash($password)
    {
        return Bcrypt::i()->hash($password);
    }

    function password_verify($password, $hash)
    {
        return Bcrypt::i()->verify($password, $hash);
    }
}

if (!function_exists('hash_hmac')) {
    /**
     * HMAC hash, works if hash extension is not installed
     *
     * Supports SHA1 and MD5 algos
     *
     * @see http://www.php.net/manual/en/function.hash-hmac.php#93440
     *
     * @param string  $data       Data to be hashed.
     * @param string  $key        Hash key.
     * @param boolean $raw_output Return raw or hex
     *
     * @access public
     * @static
     *
     * @return string Hash
     */
    function hash_hmac($algo, $data, $key, $raw_output = false)
    {
        $algo = strtolower($algo);
        $pack = 'H'.strlen($algo('test'));
        $size = 64;
        $opad = str_repeat(chr(0x5C), $size);
        $ipad = str_repeat(chr(0x36), $size);

        if (strlen($key) > $size) {
            $key = str_pad(pack($pack, $algo($key)), $size, chr(0x00));
        } else {
            $key = str_pad($key, $size, chr(0x00));
        }

        for ($i = 0; $i < strlen($key) - 1; $i++) {
            $opad[$i] = $opad[$i] ^ $key[$i];
            $ipad[$i] = $ipad[$i] ^ $key[$i];
        }

        $output = $algo($opad.pack($pack, $algo($ipad.$data)));

        return ($raw_output) ? pack($pack, $output) : $output;
    }
}

if (!function_exists('hash_pbkdf2')) {
    /**
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by defuse.ca
     * With improvements by variations-of-shadow.com
     *
     * @see http://www.php.net/manual/en/function.hash-hmac.php#109260
     *
     * @param string $algorithm - The hash algorithm to use. Recommended: SHA256
     * @param string $password - The password.
     * @param string $salt - A salt that is unique to the password.
     * @param integer $count - Iteration count. Higher is better, but slower. Recommended: At least 1024.
     * @param integer $key_length - The length of the derived key in bytes.
     * @param boolean $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * @return A $key_length-byte key derived from the password and salt.
     */
    function hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        if (function_exists('openssl_pbkdf2')) {
            return openssl_pbkdf2($password, $salt, $key_length, $count, $algorithm);
        }
        $algorithm = strtolower($algorithm);
        if(!in_array($algorithm, hash_algos(), true))
            die('PBKDF2 ERROR: Invalid hash algorithm.');
        if($count <= 0 || $key_length <= 0)
            die('PBKDF2 ERROR: Invalid parameters.');

        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = "";
        for($i = 1; $i <= $block_count; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . pack("N", $i);
            // first iteration
            $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
            // perform the other $count - 1 iterations
            for ($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
            }
            $output .= $xorsum;
        }

        if($raw_output)
            return substr($output, 0, $key_length);
        else
            return bin2hex(substr($output, 0, $key_length));
    }
}

if (!function_exists('oath_hotp')) {
    /**
     * Yet another OATH HOTP function. Has a 64 bit counter.
     *
     * @see http://www.php.net/manual/en/function.hash-hmac.php#108978
     *
     * @param string $secret Shared secret
     * @param string $crt Counter
     * @param integer $len OTP length
     * @return string
     */
    function oath_hotp($secret, $counter, $len = 8)
    {
        $binctr = pack ('NNC*', $counter>>32, $counter & 0xFFFFFFFF);
        $hash = hash_hmac ("sha1", $binctr, $secret);
        // This is where hashing stops and truncation begins
        $ofs = 2*hexdec (substr ($hash, 39, 1));
        $int = hexdec (substr ($hash, $ofs, 8)) & 0x7FFFFFFF;
        $pin = substr ($int, -$len);
        $pin = str_pad ($pin, $len, "0", STR_PAD_LEFT);
        return $pin;
    }
}
