<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Body\FileBody;
use Amp\Http\Client\Body\FormBody;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\ByteStream\buffer;

class FormBodyTest extends AsyncTestCase
{
    public function testUrlEncoded(): \Generator
    {
        $body = new FormBody();
        $body->addFields([
            'a' => 'a',
        ], 'application/json');
        $body->addField('b', 'b', 'application/json');
        $body->addField('c', 'c', '');
        $body->addField('d', 'd');
        $body->addFields([
            'e' => 'e',
        ], '');
        $body->addFields([
            'f' => 'f',
        ]);

        $this->assertSame([
            ['a', 'a', 'application/json', null],
            ['b', 'b', 'application/json', null],
            ['c', 'c', '', null],
            ['d', 'd', 'text/plain', null],
            ['e', 'e', '', null],
            ['f', 'f', 'text/plain', null],
        ], $body->getFields());

        $content = yield buffer($body->createBodyStream());
        $this->assertEquals("a=a&b=b&c=c&d=d&e=e&f=f", $content);
    }

    public function testMultiPartFields(): \Generator
    {
        $body = new FormBody('ea4ba2aa9af22673bc01ae7a64c95440');
        $body->addFields([
            'a' => 'a',
        ], 'application/json');
        $body->addField('b', 'b', 'application/json');
        $body->addField('c', 'c', '');
        $body->addField('d', 'd');
        $body->addFields([
            'e' => 'e',
        ], '');
        $body->addFields([
            'f' => 'f',
        ]);

        $file = __DIR__ . '/fixture/lorem.txt';
        $body->addFile('file', $file);

        $fields = $body->getFields();
        [$fieldName, $fileBody, $contentType, $fileName] = \end($fields);

        $this->assertSame('file', $fieldName);
        $this->assertInstanceOf(FileBody::class, $fileBody);
        $this->assertSame('application/octet-stream', $contentType);
        $this->assertSame('lorem.txt', $fileName);

        $content = yield buffer($body->createBodyStream());
        $this->assertEquals("--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"a\"\r\nContent-Type: application/json\r\n\r\na\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"b\"\r\nContent-Type: application/json\r\n\r\nb\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"c\"\r\n\r\nc\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"d\"\r\nContent-Type: text/plain\r\n\r\nd\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"e\"\r\n\r\ne\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"f\"\r\nContent-Type: text/plain\r\n\r\nf\r\n--ea4ba2aa9af22673bc01ae7a64c95440\r\nContent-Disposition: form-data; name=\"file\"; filename=\"lorem.txt\"\r\nContent-Type: application/octet-stream\r\nContent-Transfer-Encoding: binary\r\n\r\nLorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\r\n--ea4ba2aa9af22673bc01ae7a64c95440--\r\n", $content);
    }
}
