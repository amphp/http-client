<?php

class UsesRequestTraitTest extends PHPUnit_Framework_TestCase
{
  public function setUp()
  {
    $_SERVER = [];
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
    $_SERVER['REQUEST_URI'] = '/widgets';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['REQUEST_TIME_FLOAT'] = 1327719048.014;
    $_SERVER['REQUEST_TIME'] = 1327719048;
  }
  
  public function testIsInitiallyEmpty()
  {
    $traitObj = new RequestTraitImplementationClass();
    $this->assertAttributeEmpty('request', $traitObj);
    return $traitObj;
  }
  
  /**
   * @depends testIsInitiallyEmpty
   * @covers artax\UsesRequestTrait::setRequest
   * @covers artax\UsesRequestTrait::getRequest
   */
  public function testSetterAssignsRequestProperty($traitObj)
  {
    $r = new artax\blocks\http\HttpRequest();
    $traitObj->setRequest($r);
    $this->assertEquals($r, $traitObj->getRequest());
  }
}

class RequestTraitImplementationClass
{
  use artax\UsesRequestTrait;
}
