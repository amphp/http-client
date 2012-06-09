<?php

use Artax\ReflectionCacher;

class ReflectionCacherTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\ReflectionCacher::getClass
     */
    public function testGetClassRetrievesNewReflectionIfNotCached()
    {
        $rc   = new ReflectionCacher;
        $refl = $rc->getClass('Test');
        $this->assertInstanceOf('ReflectionClass', $refl);
        return $rc;
    }
    
    /**
     * @depends testGetClassRetrievesNewReflectionIfNotCached
     * @covers Artax\ReflectionCacher::getClass
     */
    public function testGetClassRetrievesCachedReflectionIfAvailable($rc)
    {
        $cached = $rc->getClass('Test');
        $new    = new ReflectionCacher;
        
        $this->assertFalse($new === $cached);
        $this->assertTrue($rc->getClass('Test') === $cached);
        return $rc;
    }
    
    /**
     * @depends testGetClassRetrievesCachedReflectionIfAvailable
     * @covers Artax\ReflectionCacher::getConstructor
     */
    public function testGetConstructorRetrievesNewReflectionIfNotCached($rc)
    {
        $ctor = $rc->getConstructor('Test');
        $this->assertInstanceOf('ReflectionMethod', $ctor);
        return $rc;
    }
    
    /**
     * @depends testGetConstructorRetrievesNewReflectionIfNotCached
     * @covers Artax\ReflectionCacher::getConstructor
     */
    public function testGetConstructorCachedReflectionIfAvailable($rc)
    {
        $cached = $rc->getConstructor('Test');
        $new    = $rc->getClass('Test')->getConstructor();
        
        $this->assertFalse($cached === $new);
        $this->assertTrue($rc->getConstructor('Test') === $cached);
        
        return $rc;
    }
    
    /**
     * @depends testGetConstructorCachedReflectionIfAvailable
     * @covers Artax\ReflectionCacher::getConstructorParameters
     */
    public function testGetCtorParamsRetrievesNewReflectionIfNotCached($rc)
    {
        $params = $rc->getConstructorParameters('Test');
        $this->assertTrue(is_array($params));
        $this->assertInstanceOf('ReflectionParameter', $params[0]);
        
        return $rc;
    }
    
    /**
     * @depends testGetCtorParamsRetrievesNewReflectionIfNotCached
     * @covers Artax\ReflectionCacher::getConstructorParameters
     */
    public function testGetCtorParamsReturnsNullIfNoConstructorExists($rc)
    {
        $params = $rc->getConstructorParameters('Param');
        $this->assertNull($params);
        
        return $rc;
    }
    
    /**
     * @depends testGetCtorParamsReturnsNullIfNoConstructorExists
     * @covers Artax\ReflectionCacher::getConstructorParameters
     */
    public function testGetCtorParamsRetrievesCachedReflectionIfAvailable($rc)
    {
        $params = $rc->getConstructorParameters('Test');
        $p1 = $rc->getConstructor('Test')->getParameters();
        $this->assertTrue(is_array($p1));
        $this->assertEquals($p1[0], $params[0]);
        
        return $rc;
    }
    
    /**
     * @depends testGetCtorParamsRetrievesCachedReflectionIfAvailable
     * @covers Artax\ReflectionCacher::getTypehint
     */
    public function testGetTypehintRetrievesNewClassNameIfNotStoredForParam($rc)
    {
        $param = $rc->getConstructorParameters('Test');
        $typehint = $rc->getTypehint($param[0]);
        $this->assertEquals('Param', $typehint);
        
        return $rc;
    }
    
    /**
     * @depends testGetTypehintRetrievesNewClassNameIfNotStoredForParam
     * @covers Artax\ReflectionCacher::getTypehint
     */
    public function testGetTypehintFetchesCachedParamTypehintIfAvailable($rc)
    {
        $param = $rc->getConstructorParameters('Test');
        $typehint = $rc->getTypehint($param[0]);
        $this->assertEquals('Param', $typehint);
        
        return $rc;
    }
    
    /**
     * @depends testGetTypehintFetchesCachedParamTypehintIfAvailable
     * @covers Artax\ReflectionCacher::getTypehint
     */
    public function testGetTypehintStoresNewReflectionClassIfFound($rc)
    {
        $reflMethod = new ReflectionMethod('TypehintTester', 'myMethod');
        $params = $reflMethod->getParameters();
        
        $typehint = $rc->getTypehint($params[0]);
        $this->assertEquals('Typehint', $typehint);
        
        return $rc;
    }
    
    /**
     * @depends testGetTypehintStoresNewReflectionClassIfFound
     * @covers Artax\ReflectionCacher::getTypehint
     */
    public function testGetTypehintReturnsNullIfParamHasNoTypehint($rc)
    {
        $reflMethod = new ReflectionMethod('TypehintTester', 'myMethod');
        $params = $reflMethod->getParameters();
        
        $typehint = $rc->getTypehint($params[1]);
        $this->assertEquals(NULL, $typehint);
        
        return $rc;
    }
}

class Param {}

class Test
{
    public function __construct(Param $param) {}
}

class Typehint {}

class TypehintTester 
{
    public function myMethod(Typehint $arg, $noHint){}
}







