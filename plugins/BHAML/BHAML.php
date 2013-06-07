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
    protected static $_defaultFileExt = '.haml';

    protected static $_haml;

    protected static $_cacheDir;

    static public function bootstrap()
    {
        BLayout::i()->addExtRenderer(static::$_defaultFileExt, 'BHAML::renderer');
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
        $sourceFile = $view->getTemplateFileName(static::$_defaultFileExt);
        //return $view->renderEval('?'.'>'.$haml->haml2PHP($sourceFile));

        $md5 = md5($sourceFile);
        $cacheDir = static::$_cacheDir.'/'.substr($md5, 0, 2);
        $cacheFilename = $cacheDir.'/'.$md5.'.php';
        if (!file_exists($cacheFilename) || filemtime($sourceFile) > filemtime($cacheFilename)) {
            BUtil::ensureDir($cacheDir);
            file_put_contents($cacheFilename, $haml->compileString(file_get_contents($sourceFile), $sourceFile));
        }
        $output = $view->renderFile($cacheFilename);
        BDebug::profile($pId);
        return $output;
    }
}

