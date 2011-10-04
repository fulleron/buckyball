<?php

class BSolrPhpClient extends BClass
{
    static public function bootstrap()
    {
        BModuleRegistry::i()->currentModule()->autoload('');
    }
}