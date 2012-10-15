<?php

use Artax\Http\Header;

class HeaderTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidHeaderNames() {
        return array(
            array(42),
            array(true),
            array(new StdClass)
        );
    }
    
    /**
     * @dataProvider provideInvalidHeaderNames
     * @covers Artax\Http\Header::__construct
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnNonStringName($name) {
        $header = new Header($name, 'value');
    }
    
    public function provideInvalidHeaderValueTypes() {
        return array(
            array(new StdClass)
        );
    }
    
    /**
     * @dataProvider provideInvalidHeaderValueTypes
     * @covers Artax\Http\Header::__construct
     * @covers Artax\Http\Header::setValue
     * @covers Artax\Http\Header::isHeaderValueValid
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnNonScalarOrNonArrayValue($value) {
        $header = new Header('X-MyHeader', $value);
    }
    
    public function provideInvalidHeaderValueArrays() {
        return array(
            array(array()),
            array(array(new StdClass)),
            array(array(array()))
        );
    }
    
    /**
     * @dataProvider provideInvalidHeaderValueArrays
     * @covers Artax\Http\Header::__construct
     * @covers Artax\Http\Header::setValue
     * @covers Artax\Http\Header::isHeaderValueValid
     * @expectedException Spl\TypeException
     */
    public function testConstructorThrowsExceptionOnInvalidArrayValue($value) {
        $header = new Header('X-MyHeader', $value);
    }
    
    /**
     * @covers Artax\Http\Header::__construct
     */
    public function testConstructorInitializesObject() {
        $header = new Header('X-MyHeader', 'test');
        $this->assertEquals('X-MyHeader', $header->getName());
        $this->assertEquals('test', $header->getValue());
        
        $header = new Header('X-MyHeader', array(1, 2, 3));
        $this->assertEquals(array(1, 2, 3), $header->getValueArray());
    }
    
    /**
     * @covers Artax\Http\Header::__construct
     */
    public function testConstructorTrimsTrailingSpacesAndColonsFromName() {
        $header = new Header('X-MyHeader : ', 'test');
        $this->assertEquals('X-MyHeader', $header->getName());
    }
    
    /**
     * @covers Artax\Http\Header::__construct
     */
    public function testConstructorAcceptsNameObjectsWithToStringMethod() {
        $objWithToString = new StubNameObject('X-MyHeader');
        $header = new Header($objWithToString, 'test');
        $this->assertEquals('X-MyHeader', $header->getName());
    }
    
    /**
     * @covers Artax\Http\Header::__toString
     * @covers Artax\Http\Header::setValue
     * @covers Artax\Http\Header::isHeaderValueValid
     */
    public function testToStringReturnsRawMessageStyleStringWithTrailingCRLF() {
        $header = new Header('X-MyHeader', '42');
        $expected = "X-MyHeader: 42\r\n";
        $this->assertEquals($expected, (string) $header);
    }
    
    /**
     * @covers Artax\Http\Header::__toString
     * @covers Artax\Http\Header::setValue
     * @covers Artax\Http\Header::isHeaderValueValid
     */
    public function testToStringTurnsMultipleValuesIntoMultipleHeaderLines() {
        $header = new Header('Set-Cookie', array('cookie1', 'cookie2'));
        $expected = "Set-Cookie: cookie1\r\n" .
                    "Set-Cookie: cookie2\r\n";
        $this->assertEquals($expected, (string) $header);
    }
    
    /**
     * @covers Artax\Http\Header::getName
     */
    public function testNameAccessorReturnsHeaderFieldName() {
        $header = new Header('X-MyHeader', 'test');
        $this->assertEquals('X-MyHeader', $header->getName());
    }
    
    /**
     * @covers Artax\Http\Header::getValue
     */
    public function testValueAccessorReturnsFirstHeaderFieldValueIfOnlyOneIsAssigned() {
        $header = new Header('X-MyHeader', 'test');
        $this->assertEquals('test', $header->getValue());
    }
    
    /**
     * @covers Artax\Http\Header::getValue
     */
    public function testValueAccessorReturnsCommaSeparatedValuesIfMultiplesAreAssigned() {
        $header = new Header('Set-Cookie', array('cookie1', 'cookie2'));
        $this->assertEquals("cookie1,cookie2", $header->getValue());
    }
    
    public function provideValidHeaderValuesForArrayValueRetrieval() {
        return array(
            array(
                'value' => 'test1',
                'expected' => array('test1')
            ),
            array(
                'value' => array('test2'),
                'expected' => array('test2')
            ),
            array(
                'value' => array('test3', 'test4'),
                'expected' => array('test3', 'test4')
            )
        );
    }
    
    /**
     * @dataProvider provideValidHeaderValuesForArrayValueRetrieval
     * @covers Artax\Http\Header::getValueArray
     */
    public function testValueArrayAccessorReturnsArrayOfHeaderValues($value, $expectedResult) {
        $header = new Header('X-MyHeader', $value);
        $this->assertEquals($expectedResult, $header->getValueArray());
    }
    
    /**
     * @covers Artax\Http\Header::count
     */
    public function testCountReturnsNumberOfAssignedValues() {
        $header = new Header('X-MyHeader', 'test');
        $this->assertEquals(1, count($header));
        
        $header = new Header('X-MyHeader', array('test1', 'test2'));
        $this->assertEquals(2, count($header));
    }
    
    /**
     * @covers Artax\Http\Header::send
     * @runInSeparateProcess
     */
    public function testSendOutputsHeaders() {
        $header = new Header('X-MyHeader', array('test1', 'test2'));
        $header->send();
    }
    
    /**
     * @covers Artax\Http\Header::rewind
     * @covers Artax\Http\Header::current
     * @covers Artax\Http\Header::key
     * @covers Artax\Http\Header::next
     * @covers Artax\Http\Header::valid
     */
    public function testIteratorMethods() {
        $values = array('test1', 'test2');
        $header = new Header('X-MyHeader', $values);
        
        foreach ($header as $key => $value) {
            $this->assertEquals($values[$key], $value);
        }
    }
    
    /**
     * @covers Artax\Http\Header::appendValue
     */
    public function testAppendValueAddsScalarToExistingValueArray() {
        $values = array('test1', 'test2');
        $header = new Header('X-MyHeader', $values);
        $header->appendValue('test3');
        $this->assertEquals(array('test1', 'test2', 'test3'), $header->getValueArray());
    }
    
    /**
     * @covers Artax\Http\Header::appendValue
     */
    public function testAppendValueMergesArrayWithExistingValueArray() {
        $values = array('test1', 'test2');
        $header = new Header('X-MyHeader', $values);
        $header->appendValue(array('test3', 'test4'));
        $this->assertEquals(array('test1', 'test2', 'test3', 'test4'), $header->getValueArray());
    }
    
    /**
     * @covers Artax\Http\Header::appendValue
     * @expectedException Spl\TypeException
     */
    public function testAppendValueThrowsExceptionOnInvalidType() {
        $header = new Header('X-MyHeader', 'test');
        $header->appendValue(new StdClass);
    }
    
    /**
     * @covers Artax\Http\Header::appendValue
     * @expectedException Spl\TypeException
     */
    public function testAppendValueThrowsExceptionOnInvalidArrayValue() {
        $header = new Header('X-MyHeader', 'test');
        $header->appendValue(array(new StdClass));
    }
}

class StubNameObject {
    private $name;
    public function __construct($name) {
        $this->name = $name;
    }
    public function __toString() {
        return $this->name;
    }
}
