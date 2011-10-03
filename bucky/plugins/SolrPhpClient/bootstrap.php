<?php

class BSolrPhpClient extends BClass
{
    public function bootstrap()
    {
        BModuleRegistry::i()->currentModule()->autoload('');
    }
}