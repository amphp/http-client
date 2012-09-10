<?php

use Artax\Uri;

class UriTest extends PHPUnit_Framework_TestCase {
    
    public function provideValidUriStrings() {
        return array(
            array('https://localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('https://user:pass@localhost.localdomain/myPage.html?var1=one#afterHash'),
            array('http://www.google.com/'),
            array('http://www.google.com')
        );
    }
    
    /**
     * @dataProvider provideValidUriStrings
     * @covers Artax\Uri::__construct
     */
    public function testConstructorDoesntBorkOnValidUriStrings($uriString) {
        $uri = new Uri($uriString);
        $this->assertInstanceOf('Artax\\Uri', $uri);
    }
    
    /**
     * @covers Artax\Uri::__toString
     */
    public function testToStringObscuresPasswordInUserInfo() {
        $uri = new Uri('https://user:pass@localhost.localdomain/myPage.html?var1=one#afterHash');
        $expected = 'https://user:********@localhost.localdomain/myPage.html?var1=one#afterHash';
        $this->assertEquals($expected, $uri->__toString());
    }
    
    public function provideBadUris() {
        return array(
            array('https://'),
            array('www.google.com') // scheme is required
        );
    }
    
    /**
     * @dataProvider provideBadUris
     * @covers Artax\Uri
     * @expectedException Spl\ValueException
     */
    public function testUriThrowsExceptionOnMalformedUri($rawUriString) {
        $uri = new Uri($rawUriString);
    }
    
    /**
     * @covers Artax\Uri::__construct
     * @covers Artax\Uri::parseUri
     * @covers Artax\Uri::getScheme
     * @covers Artax\Uri::getHost
     * @covers Artax\Uri::getPath
     * @covers Artax\Uri::getQuery
     * @covers Artax\Uri::getFragment
     * @covers Artax\Uri::getPort
     * @covers Artax\Uri::getUserInfo
     */
    public function testConstructorSetsParsedUriPropertyValues() {
        $uri = new Uri('https://localhost.localdomain/myPage.html?var1=one#afterHash');
        
        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals('localhost.localdomain', $uri->getHost());
        $this->assertEquals('/myPage.html', $uri->getPath());
        $this->assertEquals('var1=one', $uri->getQuery());
        $this->assertEquals('afterHash', $uri->getFragment());
        $this->assertEquals('443', $uri->getPort());
        $this->assertEquals('', $uri->getUserInfo());
    }
    
    /**
     * @covers Artax\Uri::getAuthority
     * @covers Artax\Uri::__construct
     * @covers Artax\Uri::parseUri
     */
    public function testGetAuthorityAppendsPortIfNot80() {
        $uri = new Uri('https://localhost.localdomain:4395');
        $this->assertEquals('localhost.localdomain:4395', $uri->getAuthority());
    }
    
    /**
     * @covers Artax\Uri::__toString
     * @covers Artax\Uri::parseUri
     * @covers Artax\Uri::setUserInfo
     */
    public function testToStringUsesProtectedUserInfo() {
        $uri = new Uri('https://user:pass@localhost.localdomain');
        $this->assertEquals('https://user:********@localhost.localdomain', $uri->__toString());
    }
    
    /**
     * @covers Artax\Uri::getRawUserInfo
     * @covers Artax\Uri::protectUserInfo
     * @covers Artax\Uri::parseUri
     */
    public function testRawUserInfoGetterReturnsProperty() {
        $uri = new Uri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:pass', $uri->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Uri::getUserInfo
     * @covers Artax\Uri::protectUserInfo
     * @covers Artax\Uri::parseUri
     */
    public function testUserInfoGetterReturnsProtectedUserInfo() {
        $uri = new Uri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:********', $uri->getUserInfo());
        
        $uri = new Uri('https://user:@localhost.localdomain');
        $this->assertEquals('user', $uri->getUserInfo());
    }
    
    public function provideTrailingSlashRenderingUris() {
        return array(
            array(
                'uri' => 'http://localhost',
                'expected' => 'http://localhost'
            ),
            array(
                'uri' => 'http://localhost/',
                'expected' => 'http://localhost/'
            ),
        );
    }
    
    /**
     * @dataProvider provideTrailingSlashRenderingUris
     * @covers Artax\Uri::__toString
     * @covers Artax\Uri::parseUri
     */
    public function testTrailingSlashRenderedInAccordanceWithOriginalUri($testUri, $expected) {
        $uri = new Uri($testUri);
        $this->assertEquals($expected, $uri->__toString());
    }
    
    /**
     * @covers Artax\Uri::wasExplicitPortSpecified
     */
    public function testExplicitPortSpecificationReturnsFalseIfNoPort() {
        $uri = new Uri('http://localhost');
        $this->assertFalse($uri->wasExplicitPortSpecified());
    }
    
    /**
     * @covers Artax\Uri::wasExplicitPortSpecified
     */
    public function testExplicitPortSpecificationReturnsTrueIfPortSpecified() {
        $uri = new Uri('https://localhost:443');
        $this->assertTrue($uri->wasExplicitPortSpecified());
    }
}
