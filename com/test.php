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

class BTest extends BClass
{
    protected $_runners = array();

    /**
     * Shortcut to help with IDE autocompletion
     *
     * @param bool  $new
     * @param array $args
     * @return BTest
     */
    public static function i($new=false, array $args=array())
    {
        return BClassRegistry::i()->instance(__CLASS__, $args, !$new);
    }

    public function __destruct()
    {
        unset($this->_runners);
    }

    public function add($f)
    {
        $this->_runners[] = $runner = BTestRunner::i(true);
        return $runner;
    }

    public function run()
    {

    }
}

class BTestException extends BException
{

}

class BTestRunner extends BClass
{
    protected $_params;
    protected $_errors = array();
    protected $_suites = array();

    public function __construct($params)
    {
        $this->_params = $params;
    }

    public function __destruct()
    {
        unset($this->_params, $this->_suites);
    }

    public function describe($suite, $f)
    {
        $this->_suites[] = $suite = BTestSuite::i(true, array('runner'=>$this, 'description'=>$suite, 'callback'=>$f));
        return $suite;
    }

    public function beforeEach($f)
    {

    }

    public function afterEach($f)
    {

    }

    public function error($expectation)
    {
        $trace = debug_backtrace();
        $this->_errors[] = array(
            'expectation' => $expectation,
            'step' => $trace[2],
        );
        return $this;
    }
}

class BTestSuite extends BClass
{
    protected $_params;
    protected $_specs = array();

    public function __construct($params)
    {
        $this->_params = $params;
    }

    public function __destruct()
    {
        unset($this->_params, $this->_specs);
    }

    public function runner()
    {
        return $this->_params['runner'];
    }

    public function it($spec, $f)
    {
        BTestSpec::i(true, array('suite'=>$this, 'description'=>$spec, 'callback'=>$f));
        return $spec;
    }

    public function beforeEach($f)
    {

    }

    public function afterEach($f)
    {

    }
}

class BTestSpec extends BClass
{
    protected $_suite;
    protected $_expectations = array();
    protected $_spies = array();

    public function __construct($params)
    {
        $this->_params = $params;
    }

    public function __destruct()
    {
        unset($this->_params, $this->_expectations, $this->_spies);
    }

    public function suite()
    {
        return $this->_params['suite'];
    }

    public function expect($x)
    {
        $this->_expectations[] = $expectation = BTestExpectation::i(true, array('spec'=>$this, 'value'=>$x));
        return $expect;
    }

    public function spyOn($class, $method)
    {
        $this->_spies[] = $spy = BTestSpy::i(true, array('spec'=>$this, 'class'=>$class, 'method'=>$method));
        return $spy;
    }
}

class BTestExpectation extends BClass
{
    protected $_params;
    protected $_not = false;
    public $x;

    public function __construct($params)
    {
        $this->_params = $params;
        $this->x = $params['value'];
    }

    public function __destruct()
    {
        unset($this->_params, $this->x);
    }

    public function spec()
    {
        return $this->_params['spec'];
    }

    public function not()
    {
        $this->_not = !$this->_not;
        return $this;
    }

    public function to($result)
    {
        if ($this->_not) $result = !$result;
        if (!$result) {
            $this->spec()->suite()->runner()->error($this);
        }
        return $this;
    }

    public function toEqual($y)
    {
        return $this->to($this->x == $y);
    }

    public function toBe($y)
    {
        return $this->to(is_a($this->x, $y));
    }

    public function toMatch($pattern)
    {
        return $this->to(preg_match($pattern, $this->x));
    }
/*
    public function toBeDefined()
    {

    }

    public function toBeUndefined()
    {

    }
*/
    public function toBeNull()
    {
        return $this->to(is_null($this->x));
    }

    public function toBeTruthy()
    {
        return $this->to(!!$this->x);
    }

    public function toBeFalsy()
    {
        return $this->to(!$this->x);
    }

    public function toContain($y)
    {
        if (is_array($this->x)) {
            return $this->to(in_array($y, $this->x));
        } else {
            return $this->to(strpos($this->x, $y) !== false);
        }
    }

    public function toBeLessThan($y)
    {
        return $this->to($this->x < $y);
    }

    public function toBeGreaterThan($y)
    {
        return $this->to($this->x > $y);
    }

    public function toThrow($exceptionClass)
    {
        try {
            call_user_func($this->x);
        } catch (Exception $e) {
            return $this->to(is_a($e, $exceptionClass));
        }
        return $this->to(false);
    }

    public function toHaveBeenCalled()
    {
        return null;
    }

    public function toHaveBeenCalledWith($arguments)
    {
        return null;
    }

    public function __call($method, $arguments)
    {
        $this->_matchers[] = BTest::i()->matcher($method, array_merge($this->_params, (array)$arguments));
        return $this;
    }
}

class BTestSpy extends BClass
{
    protected $_params;
    protected $_callThrough = false;

    public function __construct($params)
    {
        $this->_params = $params;
    }

    public function __destruct()
    {
        unset($this->_params);
    }

    public function andCallThrough()
    {
        $this->_callThrough = true;
        return $this;
    }

    public function andReturn($arguments)
    {
        return $this;
    }

    public function andThrow($exception)
    {
        return $this;
    }

    public function andCallFake($function)
    {
        return $this;
    }
}

BTest::i()->run(function($runner) {
    $runner->beforeEach(function() {

    });

    $runner->describe('spy class constructor', function($suite) {
        $counter = 0;

        $suite->beforeEach(function() use(&$counter) {
            $counter = 0;
        });

        $suite->it('should be possible', function($spec) use(&$counter) {
            $counter += 2;
            $spec->expect($counter)->toEqual(2);

            $spec->spyOn('Class', 'method1')->andCallThrough();
            $spec->expect('Class', 'method1')->toHaveBeenCalledWith(array(1, 2, 3));

            $spec->spyOn('Class', 'method2');
            $spec->expect('Class', 'method2')->not()->toHaveBeenCalled();
        });
    });
});