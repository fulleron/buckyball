<?php

class BTwig extends BClass
{
    protected static $_defaultFileExt = '.twig.html';

    protected static $_fcomVars;

    protected static $_fileLoader;
    protected static $_fileTwig;

    protected static $_stringLoader;
    protected static $_stringTwig;

    public static function bootstrap()
    {
        BLayout::i()->addExtRenderer(static::$_defaultFileExt, 'BTwig::renderer');

        BPubSub::i()->on('BLayout::addAllViews', 'BTwig::onLayoutAddAllViews');
    }

    public static function onLayoutAddAllViews($args)
    {
        static::addPath($args['root_dir'], $args['module']->name);
    }

    public static function addPath($path, $namespace)
    {
        if (!static::$_fileLoader) {

            require_once __DIR__.'/lib/Twig/Autoloader.php';
            Twig_Autoloader::register();

            $config = BConfig::i();

            $cacheDir = $config->get('fs/storage_dir').'/twig';
            BUtil::ensureDir($cacheDir);

            $options = array(
                'cache' => $cacheDir,
                'debug' => 1,#$config->get('modules/BTwig/debug'),
                'auto_reload' => 1,#$config->get('modules/BTwig/auto_reload'),
            );

            static::$_fileLoader = new Twig_Loader_Filesystem($path); //TODO: possible not to add path?
            static::$_fileTwig = new Twig_Environment(static::$_fileLoader, $options);

            static::$_stringLoader = new Twig_Loader_String();
            static::$_stringTwig = new Twig_Environment(static::$_stringLoader, $options);

            $i18nFilter = new Twig_SimpleFilter('_', 'BLocale::_');
            static::$_fileTwig->addFilter($i18nFilter);
            static::$_stringTwig->addFilter($i18nFilter);

            static::$_fileTwig->addGlobal('REQUEST', BRequest::i());
            static::$_stringTwig->addGlobal('REQUEST', BRequest::i());

            static::$_fileTwig->addGlobal('LAYOUT', BLayout::i());
            static::$_stringTwig->addGlobal('LAYOUT', BLayout::i());
        }

        static::$_fileLoader->prependPath($path, $namespace);
    }

    public static function renderer($view)
    {
        $viewName = $view->getParam('view_name');

        $pId = BDebug::debug('BTwig render: '.$viewName);

        $source = $view->param('source');
        $args = $view->getAllArgs();
        //TODO: add BRequest and BLayout vars?
        $args['view'] = $view;

        if (!$source) {

            $filename = $view->getTemplateFileName(static::$_defaultFileExt);
            $modName = $view->getParam('module_name');
            $template = static::$_fileTwig->loadTemplate('@'.$modName.'/'.$viewName.static::$_defaultFileExt);
            $output = $template->render($args);

        } else {

            $output = static::$_stringTwig->render($source, $args);

        }
        BDebug::profile($pId);
        return $output;
    }
}
