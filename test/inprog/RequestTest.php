<?php

class RequestTest extends BaseTest
{
  public function setUp()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
  }
  
  /**
   * @covers \Artax\Request::__construct
   */
  public function testConstructorSetsCfgIfPassedAsParameter()
  {
    $cfg = new Artax\Config();
    $r = $this->getMockForAbstractClass('Artax\Request',
      array($cfg), 'Test', TRUE
    );
    $correct = $r->cfg === $cfg;
    $this->assertTrue($correct);
    
    return $r;
  }
  
  /**
   * @covers \Artax\Request::setCfg
   * @covers \Artax\Request::__get
   */
  public function testSetCfgAssignsConfigurationObject()
  {
    $cfg = new Artax\Config();
    $r = $this->getMockForAbstractClass('Artax\Request');
    $r->setCfg($cfg);
    
    $this->assertEquals($cfg, $r->cfg);
  }
  
  /**
   * @covers \Artax\Request::__get
   */
  public function test__GetThrowsExceptionOnInvalidProperty()
  {
    $r = $this->getMockForAbstractClass('Artax\Request');
    try {
      $ex = FALSE;
      $x = $r->bad_prop_name;
    } catch(Artax\OutOfBoundsException $e) {
      $ex = TRUE;
    }
    $this->assertTrue($ex);
  }
  
  /**
   * @covers \Artax\Request::setRoutedURI
   */
  public function testSetRoutedURIAssignsValueToObjectProperty()
  {
    $r = $this->getMockForAbstractClass('Artax\Request');
    $r->setRoutedURI('test');
    $this->assertEquals($r->routed_uri, 'test');
  }
  
  /**
   * @covers \Artax\Request::getControllerName
   * @expectedException \Artax\RuntimeException
   */
  public function testgetControllerNameThrowsExceptionIfNoControllerSet()
  {
    $_SERVER['REQUEST_URI'] = '';
    $r = new Artax\Request;
    $r->getControllerName();    
  }
  
  /**
   * @covers \Artax\Request::getControllerName
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
   * @covers \Artax\Request::studlyCaps
   */
  public function testStudlyCapsAppliesStudlyCapsToClassesAndCamelCaseToMethods()
  {
    $uri = 'controller';
    $uri = Artax\Request::studlyCaps($uri);
    $this->assertEquals($uri, 'Controller');
    
    $uri = 'controller_name';
    $uri = Artax\Request::studlyCaps($uri);
    $this->assertEquals($uri, 'ControllerName');
    
    $uri = 'method_name';
    $uri = Artax\Request::studlyCaps($uri, TRUE);
    $this->assertEquals($uri, 'methodName');
    
    $uri = 'method';
    $uri = Artax\Request::studlyCaps($uri, TRUE);
    $this->assertEquals($uri, 'method');
  }
  
  /**
   * @covers \Artax\Request::setControllerDomain
   */
  public function testSetControllerDomainParsesArrayAsExpected()
  {
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers');
    $cfg = new Artax\Config($cfg_arr);
    
    // Get mock object for the abstract class
    $obj = $this->getMockForAbstractClass('Artax\Request', array($cfg));
    
    // Create reflection method to test protected method in the mock object
    $m = new ReflectionMethod('Artax\Request', 'setControllerDomain');
    $m->setAccessible(TRUE);
    
    $vals = array('level1', 'controller_name', 'method_name');
    
    $vals = $m->invoke($obj, $vals);
    
    $this->assertEquals($obj->domain, '\Level1');
    $this->assertEquals($obj->controller, 'ControllerName');
    $this->assertEquals($vals, array('method_name'));
    
    return $obj;
  }
  
  /**
   * @covers \Artax\Request::populateFromURI
   * @expectedException \Artax\LogicException
   */
  public function testPopulateFromURIThrowsExceptionIfNoConfigDependency()
  {
    $req = new Artax\Request();
    $req->populateFromURI();
  }
  
  /**
   * @covers \Artax\Request::populateFromURI
   * @expectedException \Artax\RuntimeException
   */
  public function testPopulateFromURIThrowsExceptionIfNoURIDetected()
  {
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers');
    $cfg = new Artax\Config($cfg_arr);
    
    $_SERVER['REQUEST_URI'] = '';
    $req = new Artax\Request($cfg);
    $req->populateFromURI();
  }
  
  /**
   * @covers \Artax\Request::populateFromURI
   */
  public function testPopulateFromURIBuildsPropertiesFromURI()
  {
    $cfg_arr = array('app_dir_controllers' => 'vfs://root/myapp/controllers');
    $cfg = new Artax\Config($cfg_arr);
    
    // Get mock object for the abstract class
    $obj = $this->getMockForAbstractClass('Artax\Request', array($cfg));
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
    
    $obj = $this->getMockForAbstractClass('Artax\Request', array($cfg));
    $obj->setRoutedURI('')->populateFromURI();
    
    $this->assertEquals($obj->domain, '\\');
    $this->assertEquals($obj->controller, 'Builtin');
    $this->assertEquals($obj->action, 'default');
    $this->assertEquals($obj->params, array());
  }
  
  /**
   * @covers \Artax\Request::__construct
   */
  public function testConstructorSetsObjectProperties()
  {
    $r = new Artax\Request();
    $props = array('ajax_flag', 'referrer', 'user_agent', 'client_ip');
    foreach ($props as $prop) {
      $null = is_null($r->$prop);
      $msg = "$prop should not be NULL after object construction";
      $this->assertFalse($null, $msg);
    }
  }
  
  /**
   * @covers \Artax\Request::setProtocol
   */
  public function testSetProtocolReturnsValueIfFoundInServerSuperglobalArray()
  {
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\Request', 'setProtocol');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->protocol, 'http');
    
    $_SERVER['HTTPS'] = 'On';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->protocol, 'https');
  }
  
  /**
   * @covers \Artax\Request::setAjaxFlag
   */
  public function testSetAjaxFlagReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\Request', 'setAjaxFlag');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->ajax_flag, FALSE);
    
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'still_false';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->ajax_flag, FALSE);
    
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlHttpRequest';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->ajax_flag, TRUE);
  }
  
  /**
   * @covers \Artax\Request::setReferrer
   */
  public function testSetReferrerReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\Request', 'setReferrer');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->referrer, '');
    
    $_SERVER['HTTP_REFERER'] = 'Test referrer';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->referrer, 'Test referrer');
  }
  
  /**
   * @covers \Artax\Request::setUserAgent
   */
  public function testSetUserAgentReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\Request', 'setUserAgent');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->user_agent, '');
    
    $_SERVER['HTTP_USER_AGENT'] = 'Test User-Agent';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->user_agent, 'Test User-Agent');
  }
  
  /**
   * @covers \Artax\Request::setClientIP
   */
  public function testSetClientIPReturnsValueIfFoundInServerSuperglobalArray()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    // Create reflection method to test protected method
    $m = new ReflectionMethod('Artax\Request', 'setClientIP');
    $m->setAccessible(TRUE);
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->client_ip, '');
    
    $_SERVER['HTTP_X_FORWARDED_FOR'] = 'forwarded_for';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->client_ip, 'forwarded_for');
    
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
    $_SERVER['HTTP_CLIENT_IP'] = 'client_ip';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->client_ip, 'client_ip');
    
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
    $_SERVER['HTTP_CLIENT_IP'] = '';
    $_SERVER['REMOTE_ADDR'] = 'remote_addr';
    $actual = $m->invoke(new \Artax\Request());
    $this->assertEquals($actual->client_ip, 'remote_addr');
  }
  
  /**
   * @covers \Artax\Request::detectURI
   */
  public function testDetectURIUsesServerRequestURIIfAvailableAndStripsQueryString()
  {
    $_SERVER['REQUEST_URI'] = 'test/uri/to/resource?q=testparam';
    $r = new Artax\Request();
    $this->assertEquals($r->uri, 'test/uri/to/resource');
  }
  
  /**
   * @covers \Artax\Request::detectURI
   */
  public function testDetectURIUsesServerRedirectURLIfRequestURINotFound()
  {
    $_SERVER['REQUEST_URI'] = '';
    $_SERVER['REDIRECT_URL'] = 'test/uri/to/resource';
    $r = new Artax\Request();
    $this->assertEquals($r->uri, 'test/uri/to/resource');
  }
}










?>
