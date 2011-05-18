<?php

class Bucky_Base extends BClass
{
    static public function init()
    {
        BFrontController::i()
            ->route('GET /', array('Bucky_Base_Controller', 'home'));

        BLayout::i()->allViews('protected/view')
            ->view('head', array('view_class'=>'BViewHead'))
            ->view('body', array('view_class'=>'BViewList'))
        ;

        //BClassRegistry::i()->overrideClass('TestA', 'TestB');

        //BClassRegistry::i()->overrideMethod('TestA', 'test', array(self::i(), 'testOverrideMethod'));

        //BClassRegistry::i()->augmentMethod('TestA', 'test', array(self::i(), 'testAugmentMethod'));
    }

    public function testOverrideMethod($obj, $arg)
    {
        return 'Method Override: '.$arg;
    }

    public function testAugmentMethod($result, $obj, $arg)
    {
        return 'Method Augment: '.$result;
    }
}

class TestA extends BClass
{
    public function test($arg)
    {
        return 'Original: '.$arg;
    }
}

class TestB extends TestA
{
    public function test($arg)
    {
        return 'Class Override: '.$arg;
    }
}

class Bucky_Base_Controller extends BActionController
{
    public function action_home()
    {
        $result = TestA::i()->test('foo');
        BLayout::i()->view('body')->append('home')->appendText($result);
        BResponse::i()->output();
    }
}