<?php

use Artax\MimeType,
    Artax\MediaRange;

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
     * @covers Artax\MediaRange::__construct
     * @covers Artax\MediaRange::parse
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
     * @covers Artax\MediaRange::__construct
     * @covers Artax\MediaRange::parse
     */
    public function testConstructorInitializesValidMediaRange($validRange) {
        $mediaRange = new MediaRange($validRange);
        $this->assertInstanceOf('Artax\\MediaRange', $mediaRange);
    }
    
    /**
     * @covers Artax\MediaRange::matches
     */
    public function testMatchesReturnsTrueOnExactMimeTypeMatch() {
        $mediaRange = new MediaRange('application/json');
        $mimeType = new MimeType('application/json');
        $this->assertTrue($mediaRange->matches($mimeType));
    }
    
    /**
     * @covers Artax\MediaRange::matches
     */
    public function testMatchesReturnsTrueOnTopLevelMimeTypeMatch() {
        $mediaRange = new MediaRange('application/*');
        $mimeType = new MimeType('application/json');
        $this->assertTrue($mediaRange->matches($mimeType));
    }
    
    /**
     * @covers Artax\MediaRange::matches
     */
    public function testMatchesReturnsTrueOnWildcardMimeTypeMatch() {
        $mediaRange = new MediaRange('*/*');
        $mimeType = new MimeType('application/json');
        $this->assertTrue($mediaRange->matches($mimeType));
    }
    
    public function provideUnmatchingMimeTypes() {
        return array(
            array('application/json'),
            array('example/example'),
            array('application/atom+xml'),
            array('x-custom/my-custom-format'),
            array('application/vnd.atreyu+xml')
        );
    }
    
    /**
     * @dataProvider provideUnmatchingMimeTypes
     * @covers Artax\MediaRange::matches
     */
    public function testMatchesReturnsFalseOnMatchFailure($mimeStr) {
        $mediaRange = new MediaRange('text/*');
        $mimeType = new MimeType($mimeStr);
        $this->assertFalse($mediaRange->matches($mimeType));
    }
}
