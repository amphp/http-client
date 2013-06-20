<?php

use Artax\Ext\Cookies\CookieParser;

class CookieParserTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @dataProvider provideParseExpectations
     */
    function testFromString($rawCookieStr, array $expectedVals) {
        $parser = new CookieParser;
        $cookie = $parser->parse($rawCookieStr);
        
        $this->assertEquals($expectedVals['name'], $cookie->getName());
        $this->assertEquals($expectedVals['value'], $cookie->getValue());
        $this->assertEquals($expectedVals['domain'], $cookie->getDomain());
        $this->assertEquals($expectedVals['path'], $cookie->getPath());
        $this->assertEquals($expectedVals['httpOnly'], $cookie->getHttpOnly());
        $this->assertEquals($expectedVals['secure'], $cookie->getSecure());
    }
    
    function provideParseExpectations() {
        return array(
            // 0 ---------------------------------------------------------------------------------->
            array('CookieName=value', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 1 ---------------------------------------------------------------------------------->
            array('CookieName=value;attr=something', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 2 ---------------------------------------------------------------------------------->
            array('CookieName=value;httponly', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 3 ---------------------------------------------------------------------------------->
            array('CookieName=value;secure', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/',
                'secure' => TRUE,
                'httpOnly' => TRUE
            )),
            
            // 4 ---------------------------------------------------------------------------------->
            array('CookieName=value;secure=yes;ignorable-attr=test', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/',
                'secure' => TRUE,
                'httpOnly' => TRUE
            )),
            
            // 5 ---------------------------------------------------------------------------------->
            array('CookieName=value;ignorable-attr=test;path=/', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 6 ---------------------------------------------------------------------------------->
            array('CookieName=value;ignorable-attr=test;path=/my/nested/dir', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => '',
                'path' => '/my/nested/dir',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 7 ---------------------------------------------------------------------------------->
            array('CookieName=value;ignorable-attr=test;path=/;domain=example.com', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => 'example.com',
                'path' => '/',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 8 ---------------------------------------------------------------------------------->
            array('CookieName=value;ignorable-attr=test;domain=subdomain.example.com;path=/', array(
                'name' => 'CookieName',
                'value' => 'value',
                'expires' => NULL,
                'domain' => 'subdomain.example.com',
                'path' => '/',
                'secure' => FALSE,
                'httpOnly' => TRUE
            )),
            
            // 9 ---------------- RFC 822 date ---------------------------------------------------->
            array('NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
                'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
                '17-Dec-2013 18:01:46 GMT; path=/; domain=.google.com; HttpOnly',
                array(
                    'name' => 'NID',
                    'value' => '67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLgC5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU',
                    'expires' => 1387303306,
                    'domain' => '.google.com',
                    'path' => '/',
                    'secure' => FALSE,
                    'httpOnly' => TRUE
                )
            ),
            
            // 10 --------------------------------------------------------------------------------->
            array('name=value; expires=Tuesday, 17-Dec-13 18:01:46 GMT; path=/; domain=google.com',
                array(
                    'name' => 'name',
                    'value' => 'value',
                    'expires' => 1387303306,
                    'domain' => 'google.com',
                    'path' => '/',
                    'secure' => FALSE,
                    'httpOnly' => TRUE
                )
            ),
            
            // 11 --------------------------------------------------------------------------------->
            array('name=value; expires=Tue Dec  17 18:01:46 2013; path=/; domain=google.com',
                array(
                    'name' => 'name',
                    'value' => 'value',
                    'expires' => 1387303306,
                    'domain' => 'google.com',
                    'path' => '/',
                    'secure' => FALSE,
                    'httpOnly' => TRUE
                )
            ),
        );
    }
    
    function testMaxAgeOverridesExpires() {
        $rawCookieStr = 'NID=67=HYYufVEDmbRDoqR56XYYo-2Of3r4LBitqd22wc0Ma0-aiKe9YKuAZjsTX4FTzFoLOTLg' .
            'C5_N8o1NR_Akc74mzHsQet-5UJds3eMJbdkNczmNRUSWTh2gkKhGaKlpiJHU; expires=Tue, ' .
            '17-Dec-2013 18:01:46 GMT; path=/; domain=.google.com; Max-Age=-1; HttpOnly';
        
        $cookie = (new CookieParser)->parse($rawCookieStr);
        
        $this->assertTrue($cookie->isExpired());
    }
}

