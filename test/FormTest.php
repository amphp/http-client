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

        $content = buffer($body->getContent());
        $this->assertEquals("a=a&b=b&c=c&d=d&encoding=1%2B2", $content);
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
