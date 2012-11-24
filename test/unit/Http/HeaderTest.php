<?php

use Artax\Http\Header;

class HeaderTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidTypes() {
        return array(
            array(array()),
            array(new StdClass),
            array(null)
        );
    }
    
    /**
     * @dataProvider provideInvalidTypes
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnInvalidFieldType($badType) {
        $header = new Header($badType, 'value');
    }
    
    /**
     * @dataProvider provideInvalidTypes
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnInvalidValueType($badType) {
        $header = new Header('Valid-Header', $badType);
    }
    
    public function provideInvalidFields() {
        // CTL chars
        $ctl = range("\x00", "\x20");
        
        // separators
        $separators = array();
        $separatorStr = "\(\)<>@,;:\"/\[\]\?={}\\\x20\x09";
        for ($i=0; $i<strlen($separatorStr); $i++) {
            $separators[] = $separatorStr[$i];
        }
        
        return array(array_merge($ctl, $separators));
    }
    
    /**
     * @dataProvider provideInvalidFields
     * @expectedException Spl\DomainException
     */
    public function testConstructorThrowsExceptionOnInvalidFieldChars($badField) {
        $header = new Header($badField, 'value');
    }
    
    public function provideInvalidValues() {
        $invalid1 = range("\x00", "\x08");
        $invalid2 = range("\x0a", "\x1f");
        
        return array(array_merge($invalid1, $invalid2));
    }
    
    /**
     * @dataProvider provideInvalidValues
     * @expectedException Spl\DomainException
     */
    public function testConstructorThrowsExceptionOnInvalidValueChars($badValue) {
        $header = new Header('Some-Header-Field', $badValue);
    }
    
    
    public function provideValidHeaderComponents() {
        $headerParts = array();
        
        // 0 -------------------------------------------------------------------------------------->
        $field = 'Content-Type';
        $value = 'text/html; charset=iso-8859-1';
        $headerParts[] = array($field, $value);
        
        // 1 -------------------------------------------------------------------------------------->
        $field = 'Content-Length';
        $value = '0';
        $headerParts[] = array($field, $value);
        
        // 2 -------------------------------------------------------------------------------------->
        $field = 'Content-Length';
        $value = '42';
        $headerParts[] = array($field, $value);
        
        // 3 -------------------------------------------------------------------------------------->
        $field = 'Cache-Control';
        $value = 'max-age=15';
        $headerParts[] = array($field, $value);
        
        // 4 -------------------------------------------------------------------------------------->
        $field = 'Transfer-Encoding';
        $value = 'chunked';
        $headerParts[] = array($field, $value);
        
        // 5 -------------------------------------------------------------------------------------->
        $field = 'Date';
        $value = 'Thu, 18 Oct 2012 04:54:20 GMT';
        $headerParts[] = array($field, $value);
        
        // 6 -------------------------------------------------------------------------------------->
        $field = 'Age';
        $value = '3';
        $headerParts[] = array($field, $value);
        
        // 7 -------------------------------------------------------------------------------------->
        $field = 'Connection';
        $value = 'keep-alive';
        $headerParts[] = array($field, $value);
        
        // 8 -------------------------------------------------------------------------------------->
        $field = 'P3P';
        $value = 'CP="CAO DSP COR CURa ADMa DEVa TAIa PSAa PSDa IVAi IVDi CONi OUR SAMo OTRo BUS ' .
                 'PHY ONL UNI PUR COM NAV INT DEM CNT STA PRE"';
        $headerParts[] = array($field, $value);
        
        // 9 -------------------------------------------------------------------------------------->
        $field = 'Set-Cookie';
        $value = 'SWID=50602B17-7E59-47D1-8BED-5216F627C643; path=/; expires=Thu, 18-Oct-2032 ' .
                 '04:54:20 GMT; domain=go.com;';
        $headerParts[] = array($field, $value);
        
        // 10 ------------------------------------------------------------------------------------->
        $field = 'Vary';
        $value = 'Accept-Encoding';
        $headerParts[] = array($field, $value);
        
        // 11 ------------------------------------------------------------------------------------->
        $field = 'Some-Empty-Header';
        $value = '';
        $headerParts[] = array($field, $value);
        
        // x -------------------------------------------------------------------------------------->
        
        return $headerParts;
    }
    
    /**
     * @dataProvider provideValidHeaderComponents
     */
    public function testToString($field, $value) {
        $header = new Header($field, $value);
        $expected = "$field: $value";
        $this->assertEquals($expected, (string) $header);
    }
    
    /**
     * @dataProvider provideValidHeaderComponents
     */
    public function testGetField($field, $value) {
        $header = new Header($field, $value);
        $this->assertEquals($field, $header->getField());
    }
    
    /**
     * @dataProvider provideValidHeaderComponents
     */
    public function testGetValue($field, $value) {
        $header = new Header($field, $value);
        $this->assertEquals($value, $header->getValue());
    }
    
    public function testGetRawValuePreservesLws() {
        $field = 'Some-Header';
        $value = "Line1\r\n\t      Line2";
        
        $header = new Header($field, $value);
        $this->assertEquals($value, $header->getRawValue());
        $this->assertEquals("Line1 Line2", $header->getValue());
    }
}