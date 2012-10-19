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
    
    public function testConstructorAssignsParsedUriPropertyValues() {
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
     * @covers Artax\Uri::assignPropertiesFromParsedParts
     */
    public function testGetAuthorityAppendsPortIfNot80() {
        $uri = new Uri('https://localhost.localdomain:4395');
        $this->assertEquals('localhost.localdomain:4395', $uri->getAuthority());
    }
    
    /**
     * @covers Artax\Uri::__toString
     * @covers Artax\Uri::parseUri
     * @covers Artax\Uri::assignPropertiesFromParsedParts
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
     * @covers Artax\Uri::assignPropertiesFromParsedParts
     */
    public function testRawUserInfoGetterReturnsProperty() {
        $uri = new Uri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:pass', $uri->getRawUserInfo());
    }
    
    /**
     * @covers Artax\Uri::getUserInfo
     * @covers Artax\Uri::protectUserInfo
     * @covers Artax\Uri::parseUri
     * @covers Artax\Uri::assignPropertiesFromParsedParts
     */
    public function testUserInfoGetterReturnsProtectedUserInfo() {
        $uri = new Uri('https://user:pass@localhost.localdomain');
        $this->assertEquals('user:********', $uri->getUserInfo());
        
        $uri = new Uri('https://user:@localhost.localdomain');
        $this->assertEquals('user:', $uri->getUserInfo());
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
     * @covers Artax\Uri::assignPropertiesFromParsedParts
     */
    public function testTrailingSlashRenderedInAccordanceWithOriginalUri($testUri, $expected) {
        $uri = new Uri($testUri);
        $this->assertEquals($expected, $uri->__toString());
    }
    
    /**
     * @covers Artax\Uri::parseQueryParameters
     */
    public function testThatNoQueryParametersAreParsedOnAnEmptyQueryString() {
        $uri = new Uri('http://localhost');
        $this->assertEquals(array(), $uri->getAllQueryParameters());
    }
    
    /**
     * @covers Artax\Uri::hasQueryParameter
     * @covers Artax\Uri::parseQueryParameters
     */
    public function testHasQueryParameterReturnsBoolOnParameterAvailability() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $this->assertTrue($uri->hasQueryParameter('var1'));
        $this->assertFalse($uri->hasQueryParameter('var9999'));
    }
    
    /**
     * @covers Artax\Uri::getQueryParameter
     * @covers Artax\Uri::parseQueryParameters
     */
    public function testQueryParameterGetterReturnsRequestedParameterValue() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $this->assertEquals('one', $uri->getQueryParameter('var1'));
    }
    
    /**
     * @covers Artax\Uri::getQueryParameter
     * @expectedException Spl\DomainException
     */
    public function testQueryParameterGetterThrowsExceptionOnInvalidParameterRequest() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $uri->getQueryParameter('var99999');
    }
    
    /**
     * @covers Artax\Uri::getAllQueryParameters
     */
    public function testGetAllQueryParametersReturnsQueryParameterArray() {
        $uri = new Uri('http://localhost/test?var1=one&var2=2');
        $this->assertEquals(array('var1'=>'one', 'var2'=>'2'), $uri->getAllQueryParameters());
    }
    
    public function testQueryStringWithNoKeyValuePairReturnsKeyAndEmptyValue() {
        $uri = new Uri('http://localhost/test?var');
        $this->assertEquals(array('var'=>''), $uri->getAllQueryParameters());
    }
    
    public function provideExpectedPortDefaultsByScheme() {
        return array(
            array('http', 80),
            array('https', 443),
            array('ftp', 21),
            array('ftps', 990),
            array('smtp', 25),
            array('anythingelse', 0)
        );
    }
    
    /**
     * @dataProvider provideExpectedPortDefaultsByScheme
     * @covers Artax\Uri::extrapolatePortFromScheme
     */
    public function testDefaultPortExtrapolationFromScheme($scheme, $expectedPort) {
        $uri = new Uri("$scheme://someurl-without-a-port");
        $this->assertEquals($expectedPort, $uri->getPort());
    }
    
    public function testIpv6UriParsing() {
        $uri = new Uri('https://user:pass@[fe80::1]:42/test.php?var=http://test.com#anchor');
        
        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals('user:********', $uri->getUserInfo());
        $this->assertEquals('user:pass', $uri->getRawUserInfo());
        $this->assertEquals('[fe80::1]', $uri->getHost());
        $this->assertEquals(42, $uri->getPort());
        $this->assertEquals('/test.php', $uri->getPath());
        $this->assertEquals('var=http://test.com', $uri->getQuery());
        $this->assertEquals('anchor', $uri->getFragment());
    }
}