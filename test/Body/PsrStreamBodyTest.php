<?php

declare(strict_types=1);

namespace Amp\Http\Client\Body;

use Amp\ByteStream\InputStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\StreamInterface;

use function Amp\call;

/**
 * @covers \Amp\Http\Client\Body\PsrStreamBody
 */
class PsrStreamBodyTest extends AsyncTestCase
{

    /**
     * @param int|null $size
     * @param int      $expectedSize
     * @return \Generator
     * @dataProvider providerBodyLength
     */
    public function testGetBodyLengthReturnsValueFromStream(?int $size, int $expectedSize): \Generator
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn($size);
        $body = new PsrStreamBody($stream);
        self::assertSame($expectedSize, yield $body->getBodyLength());
    }

    public function providerBodyLength(): array
    {
        return [
            'Stream provides zero size' => [0, 0],
            'Stream provides positive size' => [1, 1],
            'Stream doesn\'t provide its size' => [null, -1],
        ];
    }

    public function testGetHeadersReturnsEmptyList(): \Generator
    {
        $stream = $this->createMock(StreamInterface::class);
        $body = new PsrStreamBody($stream);
        self::assertSame([], yield $body->getHeaders());
    }

    public function testCreateBodyStreamResultReadsFromOriginalStream(): \Generator
    {
        $stream = (new StreamFactory())->createStream('body_content');
        $body = new PsrStreamBody($stream);
        self::assertSame('body_content', yield $this->readStream($body->createBodyStream()));
    }

    private function readStream(InputStream $stream): Promise
    {
        return call(
            function () use ($stream): \Generator {
                $buffer = [];
                do {
                    $chunk = yield $stream->read();
                    if (isset($chunk)) {
                        $buffer[] = $chunk;
                    } else {
                        break;
                    }
                } while (true);

                return \implode('', $buffer);
            }
        );
    }
}
