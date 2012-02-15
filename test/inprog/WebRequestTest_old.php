<?php

class WebRequestTest extends BaseTest
{
  public function setUp()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
  }
    
  /**
   * @covers \Artax\WebRequest::__get
   */
  public function test__GetThrowsExceptionOnInvalidProperty()
  {
    $r = $this->getMockForAbstractClass('Artax\WebRequest');
    try {
      $ex = FALSE;
      $x = $r->bad_prop_name;
    } catch(Artax\OutOfBoundsException $e) {
      $ex = TRUE;
    }
    $this->assertTrue($ex);
  }
  
  /**
   * @covers \Artax\WebRequest::setRoutedURI
   */
  public function testSetRoutedURIAssignsValueToObjectProperty()
  {
    $r = $this->getMockForAbstractClass('Artax\WebRequest');
    $r->setRoutedURI('test');
    $this->assertEquals($r->routed_uri, 'test');
  }
  
  /**
   * @covers \Artax\WebRequest::getControllerName
   * @expectedException \Artax\RuntimeException
   */
  public function testgetControllerNameThrowsExceptionIfNoControllerSet()
  {
    $_SERVER['REQUEST_URI'] = '';
    $r = new Artax\WebRequest;
    $r->getControllerName();    
  }
  
  /**
   * @covers \Artax\WebRequest::getControllerName
   * @depends testConstructorSetsCfgIfPassedAsParameter
   */
  public function testgetControllerNameReturnsPath($r)
  {
    $r->setRoutedURI('test');
    $r->populateFromURI();
    $expected = $r->domain . '\Test';
    
    $this->assertEquals($r->getControllerName(), $expected);
  }
  
  /**
   * @covers \Artax\WebRequest::setControllerDomain
   */
  public function testSetControllerDomainParsesArrayAsExpected()
  {
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers');
    $cfg = new Artax\Config($cfg_arr);
    
    // Get mock object for the abstract class
    $obj = $this->getMockForAbstractClass('Artax\WebRequest', array($cfg));
    
    // Create reflection method to test protected method in the mock object
    $m = new ReflectionMethod('Artax\WebRequest', 'setControllerDomain');
    $m->setAccessible(TRUE);
    
    $vals = array('level1', 'controller_name', 'method_name');
    
    $vals = $m->invoke($obj, $vals);
    
    $this->assertEquals($obj->domain, '\Level1');
    $this->assertEquals($obj->controller, 'ControllerName');
    $this->assertEquals($vals, array('method_name'));
    
    return $obj;
  }
  
  /**
   * @covers \Artax\WebRequest::populateFromURI
   * @expectedException \Artax\LogicException
   */
  public function testPopulateFromURIThrowsExceptionIfNoConfigDependency()
  {
    $req = new Artax\WebRequest();
    $req->populateFromURI();
  }
  
  /**
   * @covers \Artax\WebRequest::populateFromURI
   * @expectedException \Artax\RuntimeException
   */
  public function testPopulateFromURIThrowsExceptionIfNoURIDetected()
  {
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers');
    $cfg = new Artax\Config($cfg_arr);
    
    $_SERVER['REQUEST_URI'] = '';
    $req = new Artax\WebRequest($cfg);
    $req->populateFromURI();
  }
  
  /**
   * @covers \Artax\WebRequest::populateFromURI
   */
  public function testPopulateFromURIBuildsPropertiesFromURI()
  {
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers');
    $cfg = new Artax\Config($cfg_arr);
    
    // Get mock object for the abstract class
    $obj = $this->getMockForAbstractClass('Artax\WebRequest', array($cfg));
    $routed_uri = 'level1/controller_name/method_name/param1/param2';
    $obj->setRoutedURI($routed_uri)->populateFromURI();
    
    $this->assertEquals($obj->domain, '\Level1');
    $this->assertEquals($obj->controller, 'ControllerName');
    $this->assertEquals($obj->action, 'methodName');
    $this->assertEquals($obj->params, array('param1', 'param2'));
    
    
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers',
      'route_default' => 'builtin/default'
    );
    $cfg = new Artax\Config($cfg_arr);
    
    $obj = $this->getMockForAbstractClass('Artax\WebRequest', array($cfg));
    $obj->setRoutedURI('')->populateFromURI();
    
    $this->assertEquals($obj->domain, '\\');
    $this->assertEquals($obj->controller, 'Builtin');
    $this->assertEquals($obj->action, 'default');
    $this->assertEquals($obj->params, array());
  }
  
  /**
   * @covers \Artax\WebRequest::__construct
   */
  public function testConstructorSetsObjectProperties()
  {
    $r = new Artax\WebRequest();
    $props = array('ajax_flag', 'referrer', 'user_agent', 'client_ip');
    foreach ($props as $prop) {
      $null = is_null($r->$prop);
      $msg = "$prop should not be NULL after object construction";
      $this->assertFalse($null, $msg);
    }
  }
  
  /**
   * @covers \Artax\WebRequest::setProtocol
   */
  public function testSetProtocolReturnsValueIfFoundInServerSuperglobalArray()
  {
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\WebRequest', 'setProtocol');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->protocol, 'http');
    
    $_SERVER['HTTPS'] = 'On';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->protocol, 'https');
  }
  
  /**
   * @covers \Artax\WebRequest::setAjaxFlag
   */
  public function testSetAjaxFlagReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\WebRequest', 'setAjaxFlag');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->ajax_flag, FALSE);
    
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'still_false';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->ajax_flag, FALSE);
    
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlHttpWebRequest';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->ajax_flag, TRUE);
  }
  
  /**
   * @covers \Artax\WebRequest::setReferrer
   */
  public function testSetReferrerReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\WebRequest', 'setReferrer');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->referrer, '');
    
    $_SERVER['HTTP_REFERER'] = 'Test referrer';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->referrer, 'Test referrer');
  }
  
  /**
   * @covers \Artax\WebRequest::setUserAgent
   */
  public function testSetUserAgentReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\WebRequest', 'setUserAgent');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->user_agent, '');
    
    $_SERVER['HTTP_USER_AGENT'] = 'Test User-Agent';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->user_agent, 'Test User-Agent');
  }
  
  /**
   * @covers \Artax\WebRequest::setClientIP
   */
  public function testSetClientIPReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\WebRequest', 'setClientIP');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->client_ip, '');
    
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'forwarded_for';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->client_ip, 'forwarded_for');
    
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
    $_SERVER['HTTP_CLIENT_IP'] = 'client_ip';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->client_ip, 'client_ip');
    
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
    $_SERVER['HTTP_CLIENT_IP'] = '';
    $_SERVER['REMOTE_ADDR'] = 'remote_addr';
    $actual = $m->invoke(new \Artax\WebRequest());
    $this->assertEquals($actual->client_ip, 'remote_addr');
  }
  
  /**
   * @covers \Artax\WebRequest::detectURI
   */
  public function testDetectURIUsesServerWebRequestURIIfAvailableAndStripsQueryString()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    $r = new Artax\WebRequest();
    $this->assertEquals($r->uri, 'test/uri/to/resource');
  }
  
  /**
   * @covers \Artax\WebRequest::detectURI
   */
  public function testDetectURIUsesServerRedirectURLIfWebRequestURINotFound()
  {
    $_SERVER['REQUEST_URI'] = '';
    $_SERVER['REDIRECT_URL'] = 'test/uri/to/resource';
    $r = new Artax\WebRequest();
    $this->assertEquals($r->uri, 'test/uri/to/resource');
  }
}










?>
