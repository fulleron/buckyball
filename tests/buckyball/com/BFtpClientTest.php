<?php

class BFtpClientTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var BFtpClient
     */
    protected $object;

    /**
     * @var string change bellow to match your setup
     */
    protected $host = '192.168.56.101';
    protected $userName = 'pp';
    protected $password = '111111';

    protected function setUp()
    {
        $this->object = new BFtpClient(
            array(
                 'hostname' => $this->host,
                 'username' => $this->userName,
                 'password' => $this->password,
            )
        );
        $testFile     = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ftp/test.txt';
        if (file_exists($testFile) == false) {
            $this->fail(sprintf("To test BFtpClient, you need to have ftp/test.txt in %s folder.", dirname(__FILE__)));
        }
    }

    /**
     * @covers BFtpClient::ftpUpload
     */
    public function testFtpUpload()
    {
        $this->markTestSkipped("If you have actual FTP server to use, comment this line out to test its connection.");
        $from = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'ftp/test.txt';
        $to   = 'ftp/test.txt';

        $result = $this->object->ftpUpload($from, $to);
        $this->assertEmpty($result); // empty result means no errors
    }
}
