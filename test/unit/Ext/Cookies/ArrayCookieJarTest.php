<?php

use Artax\Ext\Cookies\Cookie,
    Artax\Ext\Cookies\ArrayCookieJar;

class ArrayCookieJarTest extends PHPUnit_Framework_TestCase {
    
    function testGet() {
        $name = 'SomeName';
        $value = 'someval';
        $expires = NULL;
        $path = '/';
        $domain = 'example.com';
        $secure = FALSE;
        $httpOnly = TRUE;
        
        $cookie = new Cookie($name, $value, $expires, $path, $domain, $secure, $httpOnly);
        
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $fetched = current($jar->get($domain, $path, $name));
        
        $this->assertSame($cookie, $fetched);
    }
    
    function testGetMatchesWildcardSubdomain() {
        $rawCookieStr = 'NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
            'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
            '17-Dec-2013 18:01:46 GMT; path=/; domain=.google.com; HttpOnly';
        
        $cookie = Cookie::fromString($rawCookieStr);
        
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $fetched = current($jar->get($domain = 'news.google.com', $path = '/'));
        
        $this->assertSame($cookie, $fetched);
    }
    
    function testGetWontMatchWildcardSubdomainForIpAddress() {
        $rawCookieStr = 'NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
            'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
            '17-Dec-2013 18:01:46 GMT; path=/; domain=.168.1.1; HttpOnly';
        
        $cookie = Cookie::fromString($rawCookieStr);
        
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $matches = $jar->get($domain = '192.168.1.1', $path = '/');
        
        $this->assertEmpty($matches);
    }
    
    function testGetWontMatchUnspecifiedSubdomain() {
        $rawCookieStr = 'NID=value; expires=Tue, 17-Dec-2013 18:01:46 GMT; path=/; domain=google.com';
        $cookie = Cookie::fromString($rawCookieStr);
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $matches = $jar->get($domain = 'subdomain.google.com', $path = '/');
        
        $this->assertEmpty($matches);
    }
    
    function testGetMatchesChildPath() {
        $rawCookieStr = 'NID=value; expires=Tue, 17-Dec-2013 18:01:46 GMT; path=/; domain=google.com';
        $cookie = Cookie::fromString($rawCookieStr);
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $fetched = current($jar->get($domain = 'google.com', $path = '/level1/level2'));
        
        $this->assertSame($cookie, $fetched);
    }
    
    function testGetWontMatchExternalPath() {
        $rawCookieStr = 'NID=value; expires=Tue, 17-Dec-2013 18:01:46 GMT; path=/test; domain=google.com';
        $cookie = Cookie::fromString($rawCookieStr);
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $matches = $jar->get($domain = 'google.com', $path = '/level1/level2');
        
        $this->assertEmpty($matches);
    }
    
    function testGetClearsExpiredCookiesPriorToRetrieval() {
        $rawCookieStr = 'NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
            'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
            '17-Dec-2013 18:01:46 GMT; path=/; domain=.google.com; Max-Age=-1; HttpOnly';
        
        $cookie = Cookie::fromString($rawCookieStr);
        
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $matches = $jar->get($domain = '.google.com', $path = '/');
        
        $this->assertEmpty($matches);
    }
    
    function testRemove() {
        $rawCookieStr = 'NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
            'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
            '17-Dec-2013 18:01:46 GMT; path=/; domain=.google.com; HttpOnly';
        
        $cookie = Cookie::fromString($rawCookieStr);
        
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $count = count($jar->get($domain = '.google.com', $path = '/'));
        $this->assertEquals(1, $count);
        
        $jar->remove($cookie);
        
        $count = count($jar->get($domain = '.google.com', $path = '/'));
        $this->assertEquals(0, $count);
    }
    
    function testRemoveAll() {
        $rawCookieStr = 'NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
            'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
            '17-Dec-2013 18:01:46 GMT; path=/; domain=.google.com; HttpOnly';
        
        $cookie = Cookie::fromString($rawCookieStr);
        
        $jar = new ArrayCookieJar;
        $jar->store($cookie);
        
        $count = count($jar->get($domain = '.google.com', $path = '/'));
        $this->assertEquals(1, $count);
        
        $jar->removeAll();
        
        $count = count($jar->get($domain = '.google.com', $path = '/'));
        $this->assertEquals(0, $count);
    }
    
}
