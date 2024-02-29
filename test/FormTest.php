<?php declare(strict_types=1);

namespace Amp\Http\Client;

use PHPUnit\Framework\TestCase;
use function Amp\ByteStream\buffer;

class FormTest extends TestCase
{
    public function testUrlEncoded(): void
    {
        $body = new Form();
        $body->addField('a', 'a', 'application/json');
        $body->addField('b', 'b', 'application/json');
        $body->addField('c', 'c', '');
        $body->addField('d', 'd');
        $body->addField('encoding', '1+2');

        $body->addNestedFields('list', ['one', 'two']);
        $body->addNestedFields('map', ['one' => 'one', 'two' => 'two']);

        $content = buffer($body->getContent());
        $this->assertEquals("a=a&b=b&c=c&d=d&encoding=1%2B2&list%5B0%5D=one&list%5B1%5D=two&map%5Bone%5D=one&map%5Btwo%5D=two", $content);
    }

    public function testNestedArrays(): void
    {
        $body = new Form();

        $body->addNestedFields('map', [
            [
                'one' => 'one',
                'two' => 'two',
            ],
            [
                'one' => [1],
                'two' => [1, 2],
                'three' => [1, 2, 3],
            ],
            [
                3 => 'three',
                10 => 'ten',
                42 => 'forty-two',
            ],
        ]);

        $content = buffer($body->getContent());
        $this->assertEquals("map%5B0%5D%5Bone%5D=one&map%5B0%5D%5Btwo%5D=two&map%5B1%5D%5Bone%5D%5B0%5D=1&map%5B1%5D%5Btwo%5D%5B0%5D=1&map%5B1%5D%5Btwo%5D%5B1%5D=2&map%5B1%5D%5Bthree%5D%5B0%5D=1&map%5B1%5D%5Bthree%5D%5B1%5D=2&map%5B1%5D%5Bthree%5D%5B2%5D=3&map%5B2%5D%5B3%5D=three&map%5B2%5D%5B10%5D=ten&map%5B2%5D%5B42%5D=forty-two", $content);
    }

    public function testMultiPartFieldsStream(): void
    {
        $body = new Form('ea4ba2aa9af22673bc01ae7a64c95440');
        $body->addField('a', 'a', 'application/json');
        $body->addField('b', 'b', 'application/json');
        $body->addField('c', 'c', '');
        $body->addField('d', 'd');

        $file = __DIR__ . '/fixture/lorem.txt';
        $body->addStream('file', StreamedContent::fromFile($file), 'lorem.txt');

        $content = buffer($body->getContent());
        $this->assertSame(
            "--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"a\"\r\nContent-Type: application/json\r\n\r\na\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"b\"\r\nContent-Type: application/json\r\n\r\nb\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"c\"\r\n\r\nc\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"d\"\r\n\r\nd\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"file\"; filename=\"lorem.txt\"\r\nContent-Transfer-Encoding: binary\r\n\r\nLorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\r\n--ea4ba2aa9af22673bc01ae7a64c95440--\r\n",
            $content
        );
    }

    public function testMultiPartFieldsFile(): void
    {
        $body = new Form('ea4ba2aa9af22673bc01ae7a64c95440');
        $body->addField('a', 'a', 'application/json');
        $body->addField('b', 'b', 'application/json');
        $body->addField('c', 'c', '');
        $body->addField('d', 'd');

        $file = __DIR__ . '/fixture/lorem.txt';
        $body->addFile('file', $file);

        $content = buffer($body->getContent());
        $this->assertSame(
            "--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"a\"\r\nContent-Type: application/json\r\n\r\na\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"b\"\r\nContent-Type: application/json\r\n\r\nb\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"c\"\r\n\r\nc\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"d\"\r\n\r\nd\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"file\"; filename=\"lorem.txt\"\r\nContent-Transfer-Encoding: binary\r\n\r\nLorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\r\n--ea4ba2aa9af22673bc01ae7a64c95440--\r\n",
            $content
        );
    }
}
