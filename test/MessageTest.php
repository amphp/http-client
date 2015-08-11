<?php

namespace Amp\Test\Artax;

use Amp\Artax\Message;

class MessageTest extends \PHPUnit_Framework_TestCase {
    public function testGetAndSetProtocol() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setProtocol('1.1');
        $this->assertEquals('1.1', $msg->getProtocol());
    }

    public function testGetAndSetBody() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setBody('test');
        $this->assertEquals('test', $msg->getBody());
    }

    public function testHasBody() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');

        $this->assertFalse($msg->hasBody());

        $msg->setBody('test');
        $this->assertTrue($msg->hasBody());

        $msg->setBody('0');
        $this->assertTrue($msg->hasBody());

        $msg->setBody('');
        $this->assertFalse($msg->hasBody());
    }

    public function testHasHeaderIsFalseBeforeAssignment() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $this->assertFalse($msg->hasHeader('My-Header'));
    }

    public function testHasHeaderFieldNameIsCaseInsensitive() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setHeader('mY-hEaDeR', 'value');
        $this->assertTrue($msg->hasHeader('MY-HEADER'));
    }

    /**
     * @dataProvider provideHeaderExpectations
     */
    public function testHasHeaderTrueWhenSpecified($header, $value) {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setHeader($header, $value);
        $this->assertTrue($msg->hasHeader($header));
    }

    public function provideHeaderExpectations() {
        return [
            ['My-Header', ''],
            ['My-Header', ['']],
            ['My-Header', 'test'],
            ['My-Header', ['val1', 'val2', 'val3', 'val4']],
        ];
    }

    /**
     * @dataProvider provideHeaderExpectations
     */
    public function testGetHeaderReturnsStoredValue($header, $value) {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setHeader($header, $value);
        $expectedValue = is_array($value) ? $value : [$value];
        $this->assertEquals($expectedValue, $msg->getHeader($header));
    }

    public function testGetHeaderFieldNameIsCaseInsensitive() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setHeader('mY-hEaDeR', 'value');
        $this->assertEquals(['value'], $msg->getHeader('MY-HEADER'));
    }

    /**
     * @expectedException DomainException
     */
    public function testGetHeaderThrowsExceptionOnNonexistentHeaderField() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->getHeader('Some-Nonexistent-Header');
    }

    public function testGetAllHeadersReturnsEmptyArrayIfNoHeadersStored() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $this->assertEquals([], $msg->getAllHeaders());
    }

    public function testGetAllHeadersReturnsArrayOfStoredHeaders() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');

        $msg->setHeader('My-Header1', 'val');
        $msg->setHeader('My-Header2', ['val1', 'val2']);

        $expected = [
            'My-Header1' => ['val'],
            'My-Header2' => ['val1', 'val2'],
        ];

        $this->assertEquals($expected, $msg->getAllHeaders());
    }

    /**
     * @dataProvider provideBadHeaderValues
     * @expectedException InvalidArgumentException
     */
    public function testSetHeaderThrowsExceptionOnBadValue($badValue) {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setHeader('Some-Header', $badValue);
    }

    public function provideBadHeaderValues() {
        return [
            [new \StdClass],
            [[[]]]
        ];
    }

    public function testSetAllHeaders() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setAllHeaders([
            'My-Header1' => 'val1',
            'My-Header2' => ['val1', 'val2']
        ]);

        $this->assertEquals(['val1'], $msg->getHeader('MY-HEADER1'));
        $this->assertEquals(['val1', 'val2'], $msg->getHeader('MY-HEADER2'));
    }

    public function testAppendHeaderAddsToExistingHeaderIfAlreadyExists() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->appendHeader('My-Header', 'val1');
        $this->assertEquals(['val1'], $msg->getHeader('my-header'));

        $msg->appendHeader('my-heAder', ['val2']);
        $msg->appendHeader('MY-HEADER', 'val3');

        $this->assertEquals(['val1', 'val2', 'val3'], $msg->getHeader('my-header'));
    }

    public function testRemoveHeader() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->appendHeader('My-Header', ['val1', 'val2']);
        $this->assertTrue($msg->hasHeader('my-header'));
        $msg->removeHeader('MY-HEADER');
        $this->assertFalse($msg->hasHeader('my-header'));
    }

    public function testRemoveAllHeaders() {
        $msg = $this->getMockForAbstractClass('Amp\Artax\Message');
        $msg->setHeader('My-Header', ['val1', 'val2']);
        $this->assertTrue($msg->hasHeader('my-header'));
        $msg->setHeader('My-Other-Header', ['val1', 'val2']);
        $this->assertTrue($msg->hasHeader('my-other-header'));

        $msg->removeAllHeaders();
        $this->assertEquals([], $msg->getAllHeaders());
    }

}
