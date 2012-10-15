<?php

use Artax\Negotiation\MimeType;

class MimeTypeTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidMimeTypes() {
        return array(
            array('application'),
            array('blah/*'),
            array('text-html'),
            array('*/*'),
            array(null),
            array(42),
            array('text/*')
        );
    }
    
    public function provideValidMimeTypes() {
        return array(
            array('application/json'),
            array('text/html'),
            array('example/example'),
            array('application/atom+xml'),
            array('application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml'),
            array('text/plain'),
            array('x-custom/my-custom-format')
        );
    }
    
    /**
     * @dataProvider provideInvalidMimeTypes
     * @covers Artax\Negotiation\MimeType::__construct
     * @covers Artax\Negotiation\MimeType::parse
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionOnInvalidMimeFormat($invalidMime) {
        $mimeType = new MimeType($invalidMime);
    }
    
    /**
     * @dataProvider provideValidMimeTypes
     * @covers Artax\Negotiation\MimeType::__construct
     * @covers Artax\Negotiation\MimeType::parse
     */
    public function testConstructorInitializedValidMimeType($validMime) {
        $mimeType = new MimeType($validMime);
        $this->assertInstanceOf('Artax\\Negotiation\\MimeType', $mimeType);
    }
    
    /**
     * @covers Artax\Negotiation\MimeType::__toString
     */
    public function testToStringReturnsValidMimeTypeRepresentation() {
        $mimeType = new MimeType('application/json');
        $this->assertEquals('application/json', $mimeType);
    }
    
    /**
     * @covers Artax\Negotiation\MimeType::getTopLevelType
     */
    public function testGetTopLevelTypeReturnsTopLevelMimePart() {
        $mimeType = new MimeType('application/json');
        $this->assertEquals('application', $mimeType->getTopLevelType());
    }
    
    /**
     * @covers Artax\Negotiation\MimeType::getSubType
     */
    public function testGetSubTypeReturnsSpecificSubLevelMimePart() {
        $mimeType = new MimeType('application/json');
        $this->assertEquals('json', $mimeType->getSubType());
    }
    
    /**
     * @covers Artax\Negotiation\MimeType::parse
     * @covers Artax\Negotiation\MimeType::getSuffix
     */
    public function testGetSuffixReturnsSuffixPartIfAvailable() {
        $mimeType = new MimeType('application/json');
        $this->assertEquals(null, $mimeType->getSuffix());
        
        $mimeType = new MimeType('application/vnd.atreyu-test+xml');
        $this->assertEquals('xml', $mimeType->getSuffix());
    }
    
    /**
     * @covers Artax\Negotiation\MimeType::isExperimental
     */
    public function testIsExperimentalReturnsBooleanStatus() {
        $mimeType = new MimeType('application/json');
        $this->assertFalse($mimeType->isExperimental());
        
        $mimeType = new MimeType('application/x-my-format+xml');
        $this->assertTrue($mimeType->isExperimental());
    }
}
