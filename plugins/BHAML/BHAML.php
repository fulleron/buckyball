<?php

/**
 * BHAML template format integration
 *
 * Had to make some changes to library:
 * - Disable HTML escaping within filters (javascript/css/plain)
 *   - MtHaml/NodeVisitor/RendererAbstract.php - lines 300-350
 *   - MtHaml/NodeVisitor/PhpRenderer.php - line 57
 *
 * @uses https://github.com/arnaud-lb/MtHaml
 *
 */
class BHAML extends BClass
{
    protected static $_haml;

    protected static $_cacheDir;

    static public function bootstrap()
    {
        BLayout::i()->addRenderer('BHAML', array(
            'description' => 'HAML',
            'callback' => 'BHAML::renderer',
            'file_ext' => array('.haml'),
        ));
    }

    /**
     * @return MtHaml\Environment
     */
    static public function haml()
    {
        if (!static::$_haml) {
            BApp::m('BHAML')->autoload('lib');

            $c = BConfig::i();
            $options = (array)$c->get('modules/BHAML/haml');
            static::$_haml = new MtHaml\Environment('php', $options);
            static::$_cacheDir = $c->get('fs/cache_dir').'/haml';
            BUtil::ensureDir(static::$_cacheDir);
        }
        return static::$_haml;
    }

    static public function renderer($view)
    {
        $viewName = $view->param('view_name');
        $pId = BDebug::debug('BHAML render: '.$viewName);
        $haml = static::haml();

        $source = $view->getParam('source');
        if ($source) {
            $sourceFile = $view->getParam('source_name');
            $md5 = md5($source);
            $mtime = $view->getParam('source_mtime');
        } else {
            $sourceFile = $view->getTemplateFileName();
            $md5 = md5($sourceFile);
            $mtime = filemtime($sourceFile);
        }

        $cacheDir = static::$_cacheDir.'/'.substr($md5, 0, 2);
        $cacheFilename = $cacheDir.'/.'.$md5.'.php.cache'; // to help preventing direct php run
        if (!file_exists($cacheFilename) || $mtime > filemtime($cacheFilename)) {
            BUtil::ensureDir($cacheDir);
            if (!$source) {
                $source = file_get_contents($sourceFile);
            }
            file_put_contents($cacheFilename, $haml->compileString($source, $sourceFile));
        }
        if ($view->getParam('source_untrusted')) {
            $output = file_get_contents($cacheFilename);
        } else {
            $output = $view->renderFile($cacheFilename);
        }
        BDebug::profile($pId);
        return $output;
    }
}

