<?php

class BLayout_Test extends PHPUnit_Framework_TestCase
{
    /**
     * @var BLayout
     */
    protected $_layout;

    protected function setUp()
    {
        $this->_layout = BLayout::i(true);
    }

    public function testViewRootDirSetGet()
    {
        BLayout::i()->setViewRootDir('/tmp');

        $this->assertEquals('/tmp', BLayout::i()->getViewRootDir());
    }

    public function testLayoutInstance()
    {
        $this->assertInstanceOf('BLayout', BLayout::i());
        $this->assertInstanceOf('BLayout', $this->_layout);
    }

    public function testAddAllViews()
    {
        $layout = $this->_layout;
        $layout->addAllViews(realpath(dirname(__FILE__)) . '/Frontend/views');
        foreach (array('test1', 'test2', 'test3',) as $vn) {
            $this->assertNotNull($layout->getView($vn));
        }

    }

    public function testAddAllViewsWithInvalidPath()
    {
        $layout = $this->_layout;
        $layout->addAllViews('/Frontend/views');
        foreach (array('test1', 'test2', 'test3',) as $vn) {
            $this->assertNull($layout->getView($vn));
        }
    }

    public function testDefaultViewClass()
    {
        $class = 'SampleView';

        $layout = $this->_layout;
        $layout->defaultViewClass($class);
        $layout->addView('my', array());
        $this->assertEquals($class, $layout->getView('my')->getParam('view_class'));
    }

    public function testAddGetView()
    {
        $layout = $this->_layout;
        $this->assertNull($layout->getView('my'));
        $layout->addView('my', array());
        $this->assertNotNull($layout->getView('my'));
    }

    public function testViewReturnsLayoutWhenInvokedWithParams()
    {
        $layout = $this->_layout;
        $this->assertInstanceOf('BLayout', $layout->view('my', array('template' => 'test')));
    }

    public function testViewReturnsNullWhenInvokedWithEmptyParams()
    {
        $layout = $this->_layout;
        $this->assertNull($layout->view('my', array()));
    }

    public function testMultipleAddView()
    {
        $layout = $this->_layout;
        $views = array(
            array('one', array()),
            array('two', array()),
            array('three', array()),
        );
        $layout->addView($views);
        foreach ($views as $v) {
            $this->assertNotNull($layout->getView($v[0]));
        }

    }

    public function testViewReturnsViewWhenCalledWithViewNameOnly()
    {
        $this->_layout->addView('my', array());
        $this->assertInstanceOf('BView', $this->_layout->view('my'));
    }

    public function testFindViewByTestingItsNameWithRegularExpression()
    {
        $layout = $this->_layout;

        $views = array(
            array('catalog/category/one', array()),
            array('catalog/product/one', array()),
            array('catalog/compare/one', array()),
            array('catalog/price/one', array()),
        );

        $layout->addView($views);

        $view = $layout->findViewsRegex('/\bpr.+\b/');
        $this->assertTrue(count($view) == 2);
        $this->assertEquals('catalog/product/one', $view['catalog/product/one']->getParam('view_name'));
        $this->assertEquals('catalog/price/one', $view['catalog/price/one']->getParam('view_name'));
    }

    public function testChangingRootView()
    {
        $layout = $this->_layout;
        $layout->addView('newRoot', array());
        $layout->setRootView('newRoot');
        $this->assertEquals('newRoot', $layout->getRootView()->getParam('view_name'));
    }

    public function testCanCloneViewAndItHasSameClass()
    {
        $layout = $this->_layout;

        $layout->addView('my', array('key' => 'value'));
        $view1 = $layout->getView('my');
        $view2 = $layout->cloneView('my');
        $view3 = $layout->cloneView('my', 'yours');

        $this->assertInstanceOf(get_class($view1), $view2);
        $this->assertInstanceOf(get_class($view1), $view3);
        $this->assertEquals('my-copy', $view2->getParam('view_name'));
        $this->assertEquals('yours', $view3->getParam('view_name'));
    }

    public function testCanAddCallbackToHookAndItIsCalled()
    {
        $layout = $this->_layout;
        $test = $this;
        $layout->hook('main', function() use ($test){
            $test->assertTrue(true);
        });

        $layout->dispatch('hook.main');
    }

    public function testHookView()
    {
        $layout = $this->_layout;
        $view = array('my', 'raw_text' => 'Called');
        $layout->hookView('main', $view);
        $result = $layout->dispatch('hook.main');

        $this->assertContains($view[1]['raw_text'], $result);
    }
}


class SampleView extends BView {}