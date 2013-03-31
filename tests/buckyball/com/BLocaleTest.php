<?php

class BLocale_Test extends PHPUnit_Framework_TestCase
{
    public function testSetLanguage()
    {
        $lang = 'de_DE';
        BLocale::setCurrentLanguage($lang);
        $this->assertEquals($lang, BLocale::getCurrentLanguage());
    }

    public function testTransliterateAlgorithm()
    {
        $str = "aBc dEf 123_[]$%";
        $this->assertEquals("abc-def-123", BLocale::transliterate($str));

        $str = "aBc dEf 123_[]$%";
        $this->assertEquals("abc_def_123", BLocale::transliterate($str,'_'));

        $str = "aBc dEf 123_[]$% ";
        $this->assertEquals("abcrdefr123", BLocale::transliterate($str,'R'));
    }
}