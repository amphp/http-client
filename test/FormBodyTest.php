<?php

namespace Amp\Http\Client;

use Amp\ByteStream\Message;
use Amp\PHPUnit\TestCase;
use function Amp\Promise\wait;

class FormBodyTest extends TestCase
{
    public function testUrlEncoded()
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
            'f' => 'f'
        ]);

        $this->assertSame([
            ['a', 'a', 'application/json', null],
            ['b', 'b', 'application/json', null],
            ['c', 'c', '', null],
            ['d', 'd', 'text/plain', null],
            ['e', 'e', '', null],
            ['f', 'f', 'text/plain', null],
        ], $body->getFields());

        $content = wait(new Message($body->createBodyStream()));
        $this->assertEquals("a=a&b=b&c=c&d=d&e=e&f=f", $content);
    }

    public function testMultiPartFields()
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
            'f' => 'f'
        ]);

        $file = __DIR__.'/fixture/lorem.txt';
        $body->addFile('file', $file);

        $fields = $body->getFields();
        list($fieldName, $fileBody, $contentType, $fileName) = \end($fields);

        $this->assertSame('file', $fieldName);
        $this->assertInstanceOf(FileBody::class, $fileBody);
        $this->assertSame('application/octet-stream', $contentType);
        $this->assertSame('lorem.txt', $fileName);

        $content = wait(new Message($body->createBodyStream()));
        $this->assertEquals("--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"a\"\r
Content-Type: application/json\r
\r
a\r
--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"b\"\r
Content-Type: application/json\r
\r
b\r
--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"c\"\r
\r
c\r
--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"d\"\r
Content-Type: text/plain\r
\r
d\r
--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"e\"\r
\r
e\r
--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"f\"\r
Content-Type: text/plain\r
\r
f\r
--ea4ba2aa9af22673bc01ae7a64c95440\r
Content-Disposition: form-data; name=\"file\"; filename=\"lorem.txt\"\r
Content-Type: application/octet-stream\r
Content-Transfer-Encoding: binary\r
\r
Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\r
--ea4ba2aa9af22673bc01ae7a64c95440--\r\n", $content);
    }
}
