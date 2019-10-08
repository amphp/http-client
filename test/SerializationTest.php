<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use PHPUnit\Framework\TestCase;

class SerializationTest extends TestCase
{
    public function testClient(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Serialization of class Amp\Http\Client\Client is not allowed');

        \serialize(new Client);
    }

    public function testRequest(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Serialization of class Amp\Http\Client\Request is not allowed');

        \serialize(new Request('https://google.com/'));
    }

    public function testResponse(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Serialization of class Amp\Http\Client\Response is not allowed');

        \serialize(new Response('1.1', 200, 'OK', [], new InMemoryStream(''), new Request('/')));
    }
}
