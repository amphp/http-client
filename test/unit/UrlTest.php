<?php

use Artax\Url;

class UrlTest extends PHPUnit_Framework_TestCase {
    
    public function provideValidUrls() {
        return array(
            array('https://localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('https://user@localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('http://www.google.com/')
        );
    }
    
    public function provideBadUrls() {
        return array(
            array('https://'),
            array('www.google.com') // scheme is required
        );
    }
    
    /**
     * @dataProvider provideBadUrls
     * @covers Artax\Url
     * @expectedException InvalidArgumentException
     */
    public function testUrlThrowsExceptionOnMalformedUrl($rawUrlString) {
        $url = new Url($rawUrlString);
    }
    
    /**
     * @covers Artax\Url::__construct
     * @covers Artax\Url::parseFullUrlString
     * @covers Artax\Url::getScheme
     * @covers Artax\Url::getHost
     * @covers Artax\Url::getPath
     * @covers Artax\Url::getQuery
     * @covers Artax\Url::getFragment
     * @covers Artax\Url::getPort
     * @covers Artax\Url::getUserInfo
     */
    public function testConstructorSetsParsedUriPropertyValues() {
        $url = new Url('https://localhost.localdomain/myPage.html?var1=one#afterHash');
        
        $this->assertEquals('https', $url->getScheme());
        $this->assertEquals('localhost.localdomain', $url->getHost());
        $this->assertEquals('/myPage.html', $url->getPath());
        $this->assertEquals('var1=one', $url->getQuery());
        $this->assertEquals('afterHash', $url->getFragment());
        $this->assertEquals('80', $url->getPort());
        $this->assertEquals('', $url->getUserInfo());
    }
    
    /**
     * @covers Artax\Url::getAuthority
     * @covers Artax\Url::__construct
     * @covers Artax\Url::parseFullUrlString
     */
    public function testGetAuthorityAppendsPortIfNot80() {
        $url = new Url('https://localhost.localdomain:4395');
        $this->assertEquals('localhost.localdomain:4395', $url->getAuthority());
    }
    
    /**
     * @dataProvider provideValidUrls
     * @covers Artax\Url::__toString
     * @covers Artax\Url::__construct
     * @covers Artax\Url::parseFullUrlString
     */
    public function testToStringReturnsFullUrl($fullUrlString) {
        $url = new Url($fullUrlString);
        $this->assertEquals($fullUrlString, $url->__toString());
    }
    
    /**
     * @covers Artax\Url::__toString
     * @covers Artax\Url::parseFullUrlString
     * @covers Artax\Url::setUserInfo
     */
    public function testToStringUsesProtectedUserInfo() {
        $url = new Url('https://user:pass@localhost.localdomain');
        $this->assertEquals('https://user:********@localhost.localdomain/', $url->__toString());
    }
    
    /**
     * @covers Artax\Url::getRawUrl
     * @covers Artax\Url::parseFullUrlString
     */
    public function testRawUrlGetterUsesRawUserInfo() {
        $raw = 'https://user:pass@localhost.localdomain:80/index.php?var1=one#afterHash';
        $url = new Url($raw);
        $this->assertEquals($raw, $url->getRawUrl());
    }
    
    /**
     * @covers Artax\Url::getRawUserInfo
     * @covers Artax\Url::protectUserInfo
     * @covers Artax\Url::parseFullUrlString
     */
    public function testRawUserInfoGetterReturnsProperty() {
        $url = new Url('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:pass', $url->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Url::getUserInfo
     * @covers Artax\Url::protectUserInfo
     * @covers Artax\Url::parseFullUrlString
     */
    public function testUserInfoGetterReturnsProtectedUserInfo() {
        $url = new Url('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:********', $url->getUserInfo());
        
        $url = new Url('https://user:@localhost.localdomain');
        $this->assertEquals('user', $url->getUserInfo());
    }
    
    /**
     * @covers Artax\Url::getRawAuthority
     * @covers Artax\Url::parseFullUrlString
     */
    public function testRawAuthorityGetterAppendsPortIfNot80() {
        $url = new Url('https://user@localhost.localdomain:4395');
        $this->assertEquals('user@localhost.localdomain:4395', $url->getRawAuthority());
        
        $url = new Url('https://user:pass@localhost.localdomain:4395');
        $this->assertEquals('user:pass@localhost.localdomain:4395', $url->getRawAuthority());
    }
}
