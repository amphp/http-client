<?php

use Artax\Url;

class UrlTest extends PHPUnit_Framework_TestCase {
    
    public function provideValidUrlParams() {
        return array(
            array('https', 'localhost.localdomain', '', 80, '/myPage.html', 'var1=one', 'afterHash'),
            array('https', 'localhost.localdomain', 'user', 80, '/myPage.html', 'var1=one', 'afterHash'),
            array('https', 'localhost.localdomain', 'user:pass', 80, '/myPage.html', 'var1=one', 'afterHash')
        );
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::getFragment
     * @covers Artax\Url::__construct
     */
    public function testGetFragmentReturnsFragmentPropertyValue($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals($fragment, $url->getFragment());
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::getHost
     * @covers Artax\Url::__construct
     */
    public function testGetHostReturnsHostPropertyValue($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals($host, $url->getHost());
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::getPath
     * @covers Artax\Url::__construct
     */
    public function testGetPathReturnsPathPropertyValue($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals($path, $url->getPath());
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::getPort
     * @covers Artax\Url::__construct
     */
    public function testGetPortReturnsPortPropertyValue($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals($port, $url->getPort());
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::getQuery
     * @covers Artax\Url::__construct
     */
    public function testGetQueryReturnsQueryPropertyValue($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals($query, $url->getQuery());
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::getScheme
     * @covers Artax\Url::__construct
     */
    public function testGetSchemeReturnsSchemePropertyValue($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals($scheme, $url->getScheme());
    }
    
    /**
     * @covers Artax\Url::getAuthority
     * @covers Artax\Url::__construct
     */
    public function testGetAuthorityAppendsPortIfNot80() {
        $url = new Url('https', 'localhost.localdomain', '', 4395);
        $this->assertEquals('localhost.localdomain:4395', $url->getAuthority());
    }
    
    /**
     * @dataProvider provideValidUrlParams
     * @covers Artax\Url::__toString
     * @covers Artax\Url::__construct
     */
    public function testToStringReturnsFullUrl($scheme, $host, $userInfo, $port, $path, $query, $fragment) {
        $url = new Url($scheme, $host, $userInfo, $port, $path, $query, $fragment);
        $this->assertEquals(
            "$scheme://" . $url->getAuthority() . "$path?$query#$fragment",
            $url->__toString()
        );
    }
    
    /**
     * @covers Artax\Url::__toString
     */
    public function testToStringUsesProtectedUserInfo() {
        $url = new Url('https', 'localhost.localdomain', 'user:pass');
        $this->assertEquals('https://user:********@localhost.localdomain/', $url->__toString());
    }
    
    /**
     * @covers Artax\Url::getRawUrl
     */
    public function testRawUrlGetterUsesRawUserInfo() {
        $url = new Url('https', 'localhost.localdomain', 'user:pass', 80, 'index.php', 'var1=one',
            'afterHash'
        );
        $this->assertEquals('https://user:pass@localhost.localdomain/index.php?var1=one#afterHash',
            $url->getRawUrl()
        );
    }
    
    /**
     * @covers Artax\Url::getRawUserInfo
     * @covers Artax\Url::protectUserInfo
     */
    public function testRawUserInfoGetterReturnsProperty() {
        $url = new Url('https', 'localhost.localdomain', 'user:pass');
        $this->assertEquals('user:pass', $url->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Url::getUserInfo
     * @covers Artax\Url::protectUserInfo
     */
    public function testUserInfoGetterReturnsProtectedUserInfo() {
        $url = new Url('https', 'localhost.localdomain', 'user:pass');
        $this->assertEquals('user:********', $url->getUserInfo());
        
        $url = new Url('https', 'localhost.localdomain', 'user:');
        $this->assertEquals('user:', $url->getUserInfo());
    }
    
    /**
     * @covers Artax\Url::getRawAuthority
     */
    public function testRawAuthorityGetterAppendsPortIfNot80() {
        $url = new Url('https', 'localhost.localdomain', 'user', 4395);
        $this->assertEquals('user@localhost.localdomain:4395', $url->getRawAuthority());
        
        $url = new Url('https', 'localhost.localdomain', 'user:pass', 4395);
        $this->assertEquals('user:pass@localhost.localdomain:4395', $url->getRawAuthority());
    }
}
