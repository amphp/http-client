<?php

declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;

/**
 * @covers \Amp\Http\Client\Internal\PsrRequestStream
 */
class PsrRequestStreamTest extends AsyncTestCase
{
    public function testToStringReturnsContentFromStream(): void
    {
        $inputStream = new InMemoryStream('abcd');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertSame('abcd', (string) $requestStream);
    }

    public function testToStringReturnsEmptyStringIfStreamThrowsException(): void
    {
        $inputStream = $this->createMock(InputStream::class);
        $inputStream
            ->method('read')
            ->willThrowException(new \Exception());
        $requestStream = new PsrRequestStream($inputStream);
        self::assertSame('', (string) $requestStream);
    }

    public function testReadAfterCloseThrowsException(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $requestStream->close();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream is closed');
        $requestStream->read(1);
    }

    public function testReadAfterDetachThrowsException(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $requestStream->detach();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream is closed');
        $requestStream->read(1);
    }

    public function testEofBeforeReadReturnsFalse(): void
    {
        $inputStream = new InMemoryStream('');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertFalse($requestStream->eof());
    }

    public function testEofAfterPartialReadReturnsFalse(): void
    {
        $inputStream = new InMemoryStream('ab');
        $requestStream = new PsrRequestStream($inputStream);
        $requestStream->read(1);
        self::assertFalse($requestStream->eof());
    }

    public function testEofAfterFullReadReturnsTrue(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $requestStream->read(2);
        self::assertTrue($requestStream->eof());
    }

    public function testTellThrowsException(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not seekable');
        $requestStream->tell();
    }

    public function testRewindThrowsException(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not seekable');
        $requestStream->rewind();
    }

    public function testSeekThrowsException(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not seekable');
        $requestStream->seek(0);
    }

    public function testGetSizeReturnsNull(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertNull($requestStream->getSize());
    }

    public function testIsSeekableReturnsFalse(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertFalse($requestStream->isSeekable());
    }

    public function testIsWritableReturnsFalse(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertFalse($requestStream->isWritable());
    }

    public function testWriteThrowsException(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not writable');
        $requestStream->write('a');
    }

    public function testIsReadableAfterConstructionReturnsTrue(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertTrue($requestStream->isReadable());
    }

    public function testIsReadableAfterCloseReturnsFalse(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $requestStream->close();
        self::assertFalse($requestStream->isReadable());
    }

    public function testIsReadableAfterDetachReturnsFalse(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        $requestStream->detach();
        self::assertFalse($requestStream->isReadable());
    }

    public function testGetContentsReadsAllDataFromStream(): void
    {
        $inputStream = new InMemoryStream('abcd');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertSame('abcd', $requestStream->getContents());
    }

    public function testGetMetadataReturnsNullWithKey(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertNull($requestStream->getMetadata('b'));
    }

    public function testGetMetadataReturnsEmptyArrayWithoutKey(): void
    {
        $inputStream = new InMemoryStream('a');
        $requestStream = new PsrRequestStream($inputStream);
        self::assertSame([], $requestStream->getMetadata());
    }

    public function testReadThrowsExceptionOnInvalidDataFromStream(): void
    {
        $inputStream = $this->createMock(InputStream::class);
        $requestStream = new PsrRequestStream($inputStream);
        $inputStream->method('read')->willReturn(new Success(1));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid data received from stream');
        $requestStream->read(1);
    }

    /**
     * @param string|null $firstChunk
     * @param string|null $secondChunk
     * @param int         $firstChunkSize
     * @param int         $secondChunkSize
     * @param string      $expectedFirstResult
     * @param string      $expectedSecondResult
     * @dataProvider providerReadChunks
     */
    public function testReadReturnsCorrectDataFromStreamReadingTwice(
        ?string $firstChunk,
        ?string $secondChunk,
        int $firstChunkSize,
        int $secondChunkSize,
        string $expectedFirstResult,
        string $expectedSecondResult
    ): void {
        $inputStream = $this->createMock(InputStream::class);
        $inputStream->method('read')->willReturn(new Success($firstChunk), new Success($secondChunk));
        $requestStream = new PsrRequestStream($inputStream);
        self::assertSame($expectedFirstResult, $requestStream->read($firstChunkSize));
        self::assertSame($expectedSecondResult, $requestStream->read($secondChunkSize));
    }

    public function providerReadChunks(): array
    {
        return [
            'Source chunks match target chunks' => ['a', 'b', 1, 1, 'a', 'b'],
            'Source chunks border within first target chunk' => ['ab', 'c', 1, 2, 'a', 'bc'],
            'Source chunks border within second target chunk' => ['a', 'bc', 2, 1, 'ab', 'c'],
            'Second source chunk overflows second target chunk' => ['a', 'bc', 1, 1, 'a', 'b'],
        ];
    }
}
