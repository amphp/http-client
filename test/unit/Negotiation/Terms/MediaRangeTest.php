<?php

use Artax\Negotiation\Terms\MimeType,
    Artax\Negotiation\Terms\MediaRange;

class MediaRangeTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidMediaRanges() {
        return array(
            array('application'),
            array('blah/*'),
            array('text-html'),
            array(null),
            array(42),
            array('*/html'),
            array('*/*+xml'),
            array('vnd.openxmlformats-officedocument.presentationml.notesSlide+xml')
        );
    }
    
    /**
     * @dataProvider provideInvalidMediaRanges
     * @covers Artax\Negotiation\Terms\MediaRange::__construct
     * @covers Artax\Negotiation\Terms\MediaRange::parse
     * @expectedException Spl\ValueException
     */
    public function testConstructorThrowsExceptionOnInvalidMediaRangeFormat($invalidRange) {
        $mediaRange = new MediaRange($invalidRange);
    }
    
    public function provideValidMediaRanges() {
        return array(
            array('*/*'),
            array('text/*'),
            array('application/json'),
            array('text/html'),
            array('example/example'),
            array('application/atom+xml'),
            array('text/plain'),
            array('x-custom/my-custom-format'),
            array('x-custom/*'),
            array('application/vnd.atreyu+xml')
        );
    }
    
    /**
     * @dataProvider provideValidMediaRanges
     * @covers Artax\Negotiation\Terms\MediaRange::__construct
     * @covers Artax\Negotiation\Terms\MediaRange::parse
     */
    public function testConstructorInitializesValidMediaRange($validRange) {
        $mediaRange = new MediaRange($validRange);
        $this->assertInstanceOf('Artax\\Negotiation\\Terms\\MediaRange', $mediaRange);
    }
}
