<?php

class HttpRequestTest extends PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $_SERVER['HTTP_HOST'] = 'Artax';
    $_SERVER['HTTP_CONNECTION'] = 'keep-alive';
    $_SERVER['HTTP_CACHE_CONTROL'] = 'max-age=0';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.75 Safari/535.7';
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
    $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip,deflate,sdch';
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.8';
    $_SERVER['HTTP_ACCEPT_CHARSET'] = 'ISO-8859-1,utf-8;q=0.7,*;q=0.3';
    $_SERVER['PATH'] = '/sbin:/usr/sbin:/bin:/usr/bin';
    $_SERVER['SERVER_SIGNATURE'] = 'Apache/2.2.15 (CentOS) Server at Artax Port 80';
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.2.15 (CentOS)';
    $_SERVER['SERVER_NAME'] = 'Artax';
    $_SERVER['SERVER_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_PORT'] = '80';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['DOCUMENT_ROOT'] = '/mnt/data/dev/php/Artax/webroot';
    $_SERVER['SERVER_ADMIN'] = 'root@localhost';
    $_SERVER['SCRIPT_FILENAME'] = '/mnt/data/dev/php/Artax/webroot/index.php';
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
   * @covers \Artax\Http\HttpRequest::__get
   * @expectedException OutOfBoundsException
   */
  public function testMagicGetThrowsExceptionOnInvalidProperty()
  {
    $r = new Artax\Http\HttpRequest;
    $x = $r->bad_prop_name;
  }
  
  /**
   * @covers \Artax\Http\HttpRequest::__get
   */
  public function testMagicGetReturnsValueForValidProperty()
  {
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals(1327719048, $r->server['REQUEST_TIME']);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectMethod
   * @expectedException Artax\Http\HTTPException
   * @exceptionMessage  Cannot detect method: No valid SERVER key exists
   */
  public function testDetectMethodThrowsExceptionOnMissingServerVal()
  {
    unset($_SERVER['REQUEST_METHOD']);
    $r = new Artax\Http\HttpRequest;
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectMethod
   */
  public function testDetectMethodParsesServerVal()
  {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('POST', $r->method);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectProtocol
   * @covers            \Artax\Http\HttpRequest::__construct
   * @covers            \Artax\Http\BucketAbstract::__construct
   */
  public function testDetectProtocolParsesServerVal()
  {
    $_SERVER['HTTPS'] = 'On';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('https', $r->protocol);
    
    unset($_SERVER['HTTPS']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('http', $r->protocol);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectHost
   * @expectedException Artax\Http\HTTPException
   * @exceptionMessage  Cannot detect host: No "Host" header exists
   */
  public function testDetectHostThrowsExceptionOnMissingServerVal()
  {
    unset($_SERVER['HTTP_HOST']);
    $r = new Artax\Http\HttpRequest;
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectHost
   */
  public function testDetectHostParsesServerVal()
  {
    $_SERVER['HTTP_HOST'] = 'Artax';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('Artax', $r->host);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectUri
   * @expectedException Artax\Http\HTTPException
   * @exceptionMessage  Cannot detect target: No valid SERVER URI keys exist
   */
  public function testDetectUriThrowsExceptionOnMissingServerVal()
  {
    unset($_SERVER['REQUEST_URI']);
    unset($_SERVER['REDIRECT_URL']);
    $r = new Artax\Http\HttpRequest;
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectUri
   */
  public function testDetectUriParsesServerVal()
  {
    $_SERVER['REQUEST_URI'] = '/test.php?qvar=test';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('/test.php', $r->uri);
    
    $_SERVER['REQUEST_URI'] = '/';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('/', $r->uri);
    
    unset($_SERVER['REQUEST_URI']);
    $_SERVER['REDIRECT_URL'] = '/';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('/', $r->uri);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectQueryString
   */
  public function testDetectQueryStringParsesServerVal()
  {
    $_SERVER['QUERY_STRING'] = 'test=1';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('test=1', $r->queryString);
    
    unset($_SERVER['QUERY_STRING']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('', $r->queryString);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectAddress
   */
  public function testDetectAddressParsesServerVal()
  {
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('http://Artax/', $r->address);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectBody
   */
  public function testDetectBodyParsesServerVal()
  {
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('', $r->body);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectIsAjax
   */
  public function testDetectIsAjaxParsesServerVal()
  {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlHttpRequest';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals(TRUE, $r->isAjax);
    
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals(FALSE, $r->isAjax);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectClientIP
   */
  public function testDetectClientIPParsesServerVal()
  {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.3';
    $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.2';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('127.0.0.3', $r->clientIP);
    
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('127.0.0.2', $r->clientIP);
    
    unset($_SERVER['HTTP_CLIENT_IP']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('127.0.0.1', $r->clientIP);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectClientIP
   * @expectedException Artax\Http\HTTPException
   * @exceptionMessage  Cannot detect client IP: No valid SERVER keys exist
   */
  public function testDetectClientIPThrowsExceptionOnParseFailure()
  {
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    unset($_SERVER['HTTP_CLIENT_IP']);
    unset($_SERVER['REMOTE_ADDR']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('127.0.0.3', $r->clientIP);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::detectTime
   */
  public function testDetectTimeParsesServerValOrReturnsNullIfMissing()
  {
    $expected = new \DateTime(date(\DateTime::ISO8601, 1327719048));
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals($expected, $r->time);
    
    unset($_SERVER['REQUEST_TIME']);
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals(NULL, $r->time);
  }
  
  /**
   * @covers            Artax\Http\HttpRequest::getTarget
   */
  public function testGetTargetReturnsPropertyValue()
  {
    $r = new Artax\Http\HttpRequest;
    $this->assertEquals('/', $r->getTarget());
  }
}











