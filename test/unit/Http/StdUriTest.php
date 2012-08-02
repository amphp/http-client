<?php

use Artax\Http\StdUri;

class StdUriTest extends PHPUnit_Framework_TestCase {
    
    public function provideValidStdUris() {
        return array(
            array('https://localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('https://user@localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('http://www.google.com/')
        );
    }
    
    public function provideBadStdUris() {
        return array(
            array('https://'),
            array('www.google.com') // scheme is required
        );
    }
    
    /**
     * @dataProvider provideBadStdUris
     * @covers Artax\Http\StdUri
     * @expectedException InvalidArgumentException
     */
    public function testStdUriThrowsExceptionOnMalformedStdUri($rawStdUriString) {
        $url = new StdUri($rawStdUriString);
    }
    
    /**
     * @covers Artax\Http\StdUri::__construct
     * @covers Artax\Http\StdUri::parseUri
     * @covers Artax\Http\StdUri::getScheme
     * @covers Artax\Http\StdUri::getHost
     * @covers Artax\Http\StdUri::getPath
     * @covers Artax\Http\StdUri::getQuery
     * @covers Artax\Http\StdUri::getFragment
     * @covers Artax\Http\StdUri::getPort
     * @covers Artax\Http\StdUri::getUserInfo
     */
    public function testConstructorSetsParsedUriPropertyValues() {
        $url = new StdUri('https://localhost.localdomain/myPage.html?var1=one#afterHash');
        
        $this->assertEquals('https', $url->getScheme());
        $this->assertEquals('localhost.localdomain', $url->getHost());
        $this->assertEquals('/myPage.html', $url->getPath());
        $this->assertEquals('var1=one', $url->getQuery());
        $this->assertEquals('afterHash', $url->getFragment());
        $this->assertEquals('80', $url->getPort());
        $this->assertEquals('', $url->getUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdUri::getAuthority
     * @covers Artax\Http\StdUri::__construct
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testGetAuthorityAppendsPortIfNot80() {
        $url = new StdUri('https://localhost.localdomain:4395');
        $this->assertEquals('localhost.localdomain:4395', $url->getAuthority());
    }
    
    /**
     * @dataProvider provideValidStdUris
     * @covers Artax\Http\StdUri::__toString
     * @covers Artax\Http\StdUri::__construct
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testToStringReturnsFullStdUri($fullStdUriString) {
        $url = new StdUri($fullStdUriString);
        $this->assertEquals($fullStdUriString, $url->__toString());
    }
    
    /**
     * @covers Artax\Http\StdUri::__toString
     * @covers Artax\Http\StdUri::parseUri
     * @covers Artax\Http\StdUri::setUserInfo
     */
    public function testToStringUsesProtectedUserInfo() {
        $url = new StdUri('https://user:pass@localhost.localdomain');
        $this->assertEquals('https://user:********@localhost.localdomain/', $url->__toString());
    }
    
    /**
     * @covers Artax\Http\StdUri::getRawUri
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testRawStdUriGetterUsesRawUserInfo() {
        $raw = 'https://user:pass@localhost.localdomain:80/index.php?var1=one#afterHash';
        $url = new StdUri($raw);
        $this->assertEquals($raw, $url->getRawUri());
    }
    
    /**
     * @covers Artax\Http\StdUri::getRawUserInfo
     * @covers Artax\Http\StdUri::protectUserInfo
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testRawUserInfoGetterReturnsProperty() {
        $url = new StdUri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:pass', $url->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdUri::getUserInfo
     * @covers Artax\Http\StdUri::protectUserInfo
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testUserInfoGetterReturnsProtectedUserInfo() {
        $url = new StdUri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:********', $url->getUserInfo());
        
        $url = new StdUri('https://user:@localhost.localdomain');
        $this->assertEquals('user', $url->getUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdUri::getRawAuthority
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testRawAuthorityGetterAppendsPortIfNot80() {
        $url = new StdUri('https://user@localhost.localdomain:4395');
        $this->assertEquals('user@localhost.localdomain:4395', $url->getRawAuthority());
        
        $url = new StdUri('https://user:pass@localhost.localdomain:4395');
        $this->assertEquals('user:pass@localhost.localdomain:4395', $url->getRawAuthority());
    }
}
