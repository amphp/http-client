<?php

use Artax\Http\StdUri;

class StdUriTest extends PHPUnit_Framework_TestCase {
    
    public function provideValidStdUris() {
        return array(
            array('https://localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('https://user@localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('http://www.google.com/'),
            array('http://www.google.com')
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
        $uri = new StdUri($rawStdUriString);
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
        $uri = new StdUri('https://localhost.localdomain/myPage.html?var1=one#afterHash');
        
        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals('localhost.localdomain', $uri->getHost());
        $this->assertEquals('/myPage.html', $uri->getPath());
        $this->assertEquals('var1=one', $uri->getQuery());
        $this->assertEquals('afterHash', $uri->getFragment());
        $this->assertEquals('80', $uri->getPort());
        $this->assertEquals('', $uri->getUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdUri::getAuthority
     * @covers Artax\Http\StdUri::__construct
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testGetAuthorityAppendsPortIfNot80() {
        $uri = new StdUri('https://localhost.localdomain:4395');
        $this->assertEquals('localhost.localdomain:4395', $uri->getAuthority());
    }
    
    /**
     * @dataProvider provideValidStdUris
     * @covers Artax\Http\StdUri::__toString
     * @covers Artax\Http\StdUri::getRawUri
     * @covers Artax\Http\StdUri::__construct
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testToStringReturnsFullStdUri($fullStdUriString) {
        $uri = new StdUri($fullStdUriString);
        $this->assertEquals($fullStdUriString, $uri->__toString());
        $this->assertEquals($fullStdUriString, $uri->getRawUri());
    }
    
    /**
     * @covers Artax\Http\StdUri::__toString
     * @covers Artax\Http\StdUri::parseUri
     * @covers Artax\Http\StdUri::setUserInfo
     */
    public function testToStringUsesProtectedUserInfo() {
        $uri = new StdUri('https://user:pass@localhost.localdomain');
        $this->assertEquals('https://user:********@localhost.localdomain', $uri->__toString());
    }
    
    /**
     * @covers Artax\Http\StdUri::getRawUri
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testRawStdUriGetterUsesRawUserInfo() {
        $raw = 'https://user:pass@localhost.localdomain:80/index.php?var1=one#afterHash';
        $uri = new StdUri($raw);
        $this->assertEquals($raw, $uri->getRawUri());
    }
    
    /**
     * @covers Artax\Http\StdUri::getRawUserInfo
     * @covers Artax\Http\StdUri::protectUserInfo
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testRawUserInfoGetterReturnsProperty() {
        $uri = new StdUri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:pass', $uri->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdUri::getUserInfo
     * @covers Artax\Http\StdUri::protectUserInfo
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testUserInfoGetterReturnsProtectedUserInfo() {
        $uri = new StdUri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:********', $uri->getUserInfo());
        
        $uri = new StdUri('https://user:@localhost.localdomain');
        $this->assertEquals('user', $uri->getUserInfo());
    }
    
    /**
     * @covers Artax\Http\StdUri::getRawAuthority
     * @covers Artax\Http\StdUri::parseUri
     */
    public function testRawAuthorityGetterAppendsPortIfNot80() {
        $uri = new StdUri('https://user@localhost.localdomain:4395');
        $this->assertEquals('user@localhost.localdomain:4395', $uri->getRawAuthority());
        
        $uri = new StdUri('https://user:pass@localhost.localdomain:4395');
        $this->assertEquals('user:pass@localhost.localdomain:4395', $uri->getRawAuthority());
    }
}
