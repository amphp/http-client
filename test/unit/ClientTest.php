<?php

use Artax\Client,
    Spl\Mediator;

class ClientTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Client::__construct
     */
    public function testBeginsEmpty() {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $this->assertInstanceOf('Artax\\Client', $client);
    }
    
    public function provideInvalidMultiRequestLists() {
        return array(
            array(42),
            array(new StdClass),
            array(array(42))
        );
    }
    
    /**
     * @dataProvider provideInvalidMultiRequestLists
     * @covers Artax\Client::sendMulti
     * @covers Artax\Client::validateRequestList
     * @expectedException Spl\TypeException
     */
    public function testSendMultiThrowsExceptionOnInvalidRequestTraversable($badRequestList) {
        $mediator = $this->getMock('Spl\\Mediator');
        $client = new Client($mediator);
        $client->sendMulti($badRequestList);
    }
    
}

class CustomStreamWrapper {
    
    public $context;
    
    public static $position = 0;
    public static $body = '';
    public static $returnOnNextRead = null;
    public static $returnOnNextWrite = null;
    public static $writtenData = '';
    
    public static function reset() {
        static::$position = 0;
        static::$body = '';
        static::$returnOnNextRead = null;
        static::$returnOnNextWrite = null;
        static::$writtenData = '';
    }
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }

    public function stream_read($bytes) {
        if (!is_null(static::$returnOnNextRead)) {
            return static::$returnOnNextRead;
        } else {
            $chunk = substr(static::$body, static::$position, $bytes);
            static::$position += strlen($chunk);
            return $chunk;
        }
    }
    
    public function stream_write($data) {
        static::$writtenData .= $data;
        return is_null(static::$returnOnNextWrite) ? strlen($data) : static::$returnOnNextWrite;
    }

    public function stream_eof() {
        return static::$position >= strlen(static::$body);
    }
    
    public function stream_tell() {
        return static::$position;
    }
    
    public function stream_close() {
        return null;
    }
}