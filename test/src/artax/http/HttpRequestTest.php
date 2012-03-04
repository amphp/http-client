<?php

class HttpRequestTest extends PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $_SERVER['HTTP_HOST'] = 'artax';
    $_SERVER['HTTP_CONNECTION'] = 'keep-alive';
    $_SERVER['HTTP_CACHE_CONTROL'] = 'max-age=0';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.75 Safari/535.7';
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
    $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip,deflate,sdch';
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.8';
    $_SERVER['HTTP_ACCEPT_CHARSET'] = 'ISO-8859-1,utf-8;q=0.7,*;q=0.3';
    $_SERVER['PATH'] = '/sbin:/usr/sbin:/bin:/usr/bin';
    $_SERVER['SERVER_SIGNATURE'] = 'Apache/2.2.15 (CentOS) Server at artax Port 80';
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.2.15 (CentOS)';
    $_SERVER['SERVER_NAME'] = 'artax';
    $_SERVER['SERVER_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['DOCUMENT_ROOT'] = '/mnt/data/dev/php/artax/webroot';
    $_SERVER['SERVER_ADMIN'] = 'root@localhost';
    $_SERVER['SCRIPT_FILENAME'] = '/mnt/data/dev/php/artax/webroot/index.php';
    $_SERVER['REMOTE_PORT'] = 36764;
    $_SERVER['GATEWAY_INTERFACE'] = 'CGI/1.1';
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['QUERY_STRING'] = '';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['REQUEST_TIME_FLOAT'] = 1327719048.014;
    $_SERVER['REQUEST_TIME'] = 1327719048;
  }
  
  /**
   * @covers \artax\http\HttpRequest::__get
   * @expectedException OutOfBoundsException
   */
  public function testMagicGetThrowsExceptionOnInvalidProperty()
  {
    $r = new artax\http\HttpRequest;
    $x = $r->bad_prop_name;
  }
  
  /**
   * @covers \artax\http\HttpRequest::__get
   */
  public function testMagicGetReturnsValueForValidProperty()
  {
    $r = new artax\http\HttpRequest;
    $this->assertEquals(1327719048, $r->server['REQUEST_TIME']);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectMethod
   * @expectedException artax\http\HTTPException
   * @exceptionMessage  Cannot detect method: No valid SERVER key exists
   */
  public function testDetectMethodThrowsExceptionOnMissingServerVal()
  {
    unset($_SERVER['REQUEST_METHOD']);
    $r = new artax\http\HttpRequest;
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectMethod
   */
  public function testDetectMethodParsesServerVal()
  {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('POST', $r->method);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectProtocol
   * @covers            \artax\http\HttpRequest::__construct
   * @covers            \artax\http\BucketAbstract::__construct
   */
  public function testDetectProtocolParsesServerVal()
  {
    $_SERVER['HTTPS'] = 'On';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('https', $r->protocol);
    
    unset($_SERVER['HTTPS']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals('http', $r->protocol);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectHost
   * @expectedException artax\http\HTTPException
   * @exceptionMessage  Cannot detect host: No "Host" header exists
   */
  public function testDetectHostThrowsExceptionOnMissingServerVal()
  {
    unset($_SERVER['HTTP_HOST']);
    $r = new artax\http\HttpRequest;
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectHost
   */
  public function testDetectHostParsesServerVal()
  {
    $_SERVER['HTTP_HOST'] = 'artax';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('artax', $r->host);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectUri
   * @expectedException artax\http\HTTPException
   * @exceptionMessage  Cannot detect target: No valid SERVER URI keys exist
   */
  public function testDetectUriThrowsExceptionOnMissingServerVal()
  {
    unset($_SERVER['REQUEST_URI']);
    unset($_SERVER['REDIRECT_URL']);
    $r = new artax\http\HttpRequest;
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectUri
   */
  public function testDetectUriParsesServerVal()
  {
    $_SERVER['REQUEST_URI'] = '/test.php?qvar=test';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('/test.php', $r->uri);
    
    $_SERVER['REQUEST_URI'] = '/';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('/', $r->uri);
    
    unset($_SERVER['REQUEST_URI']);
    $_SERVER['REDIRECT_URL'] = '/';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('/', $r->uri);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectQueryString
   */
  public function testDetectQueryStringParsesServerVal()
  {
    $_SERVER['QUERY_STRING'] = 'test=1';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('test=1', $r->queryString);
    
    unset($_SERVER['QUERY_STRING']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals('', $r->queryString);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectAddress
   */
  public function testDetectAddressParsesServerVal()
  {
    $r = new artax\http\HttpRequest;
    $this->assertEquals('http://artax/', $r->address);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectBody
   */
  public function testDetectBodyParsesServerVal()
  {
    $r = new artax\http\HttpRequest;
    $this->assertEquals('', $r->body);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectIsAjax
   */
  public function testDetectIsAjaxParsesServerVal()
  {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlHttpRequest';
    $r = new artax\http\HttpRequest;
    $this->assertEquals(TRUE, $r->isAjax);
    
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals(FALSE, $r->isAjax);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectClientIP
   */
  public function testDetectClientIPParsesServerVal()
  {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.3';
    $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.2';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $r = new artax\http\HttpRequest;
    $this->assertEquals('127.0.0.3', $r->clientIP);
    
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals('127.0.0.2', $r->clientIP);
    
    unset($_SERVER['HTTP_CLIENT_IP']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals('127.0.0.1', $r->clientIP);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectClientIP
   * @expectedException artax\http\HTTPException
   * @exceptionMessage  Cannot detect client IP: No valid SERVER keys exist
   */
  public function testDetectClientIPThrowsExceptionOnParseFailure()
  {
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    unset($_SERVER['HTTP_CLIENT_IP']);
    unset($_SERVER['REMOTE_ADDR']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals('127.0.0.3', $r->clientIP);
  }
  
  /**
   * @covers            artax\http\HttpRequest::detectTime
   */
  public function testDetectTimeParsesServerValOrReturnsNullIfMissing()
  {
    $expected = new \DateTime(date(\DateTime::ISO8601, 1327719048));
    $r = new artax\http\HttpRequest;
    $this->assertEquals($expected, $r->time);
    
    unset($_SERVER['REQUEST_TIME']);
    $r = new artax\http\HttpRequest;
    $this->assertEquals(NULL, $r->time);
  }
  
  /**
   * @covers            artax\http\HttpRequest::getTarget
   */
  public function testGetTargetReturnsPropertyValue()
  {
    $r = new artax\http\HttpRequest;
    $this->assertEquals('/', $r->getTarget());
  }
}











