<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\FormField;
use PHPUnit\Framework\TestCase;
use function Amp\ByteStream\buffer;

class FormBodyTest extends TestCase
{
    public function testUrlEncoded(): void
    {
        $body = new Form();
        $body->addText('a', 'a', 'application/json');
        $body->addText('b', 'b', 'application/json');
        $body->addText('c', 'c', '');
        $body->addText('d', 'd');

        $content = buffer($body->getContent());
        $this->assertEquals("a=a&b=b&c=c&d=d", $content);
    }

    public function testMultiPartFields(): void
    {
        $body = new Form('ea4ba2aa9af22673bc01ae7a64c95440');
        $body->addText('a', 'a', 'application/json');
        $body->addText('b', 'b', 'application/json');
        $body->addText('c', 'c', '');
        $body->addText('d', 'd');

        $file = __DIR__ . '/fixture/lorem.txt';
        $body->addStream('file', StreamedContent::file($file), 'lorem.txt');

        $content = buffer($body->getContent());
        $this->assertSame(
            "--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"a\"\r\nContent-Type: application/json\r\n\r\na\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"b\"\r\nContent-Type: application/json\r\n\r\nb\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"c\"\r\n\r\nc\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"d\"\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nd\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"file\"; filename=\"lorem.txt\"\r\nContent-Type: application/octet-stream\r\nContent-Transfer-Encoding: binary\r\n\r\nLorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\r\n--ea4ba2aa9af22673bc01ae7a64c95440--\r\n",
            $content
        );
    }
}
