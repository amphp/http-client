<?php

declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Laminas\Diactoros\Request as PsrRequest;

use Laminas\Diactoros\RequestFactory;
use function Amp\call;

/**
 * @covers \Amp\Http\Client\PsrAdapter
 */
class PsrAdapterTest extends AsyncTestCase
{
    public function testFromPsrRequestReturnsRequestWithEqualUri(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new PsrRequest('https://user:password@localhost/foo?a=b#c');
        $target = $adapter->fromPsrRequest($source);
        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testFromPsrRequestReturnsRequestWithEqualMethod(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new PsrRequest(null, 'POST');
        $target = $adapter->fromPsrRequest($source);
        self::assertSame('POST', $target->getMethod());
    }

    public function testFromPsrRequestReturnsRequestWithAllAddedHeaders(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new PsrRequest(null, null, 'php://memory', ['a' => 'b', 'c' => ['d', 'e']]);
        $target = $adapter->fromPsrRequest($source);
        $actualHeaders = \array_map([$target, 'getHeaderArray'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    public function testFromPsrRequestReturnsRequestWithSameProtocolVersion(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = (new PsrRequest())->withProtocolVersion('2');
        $target = $adapter->fromPsrRequest($source);
        self::assertSame(['2'], $target->getProtocolVersions());
    }

    public function testFromPsrRequestReturnsRequestWithMatchingBody(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new PsrRequest();
        $source->getBody()->write('body_content');
        $target = $adapter->fromPsrRequest($source);

        self::assertSame('body_content', yield $this->readBody($target->getBody()));
    }

    private function readBody(RequestBody $body): Promise
    {
        return call(
            function () use ($body): \Generator {
                $stream = $body->createBodyStream();
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

    public function testToPsrRequestReturnsRequestWithEqualUri(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new Request('https://user:password@localhost/foo?a=b#c');
        $target = $adapter->toPsrRequest($source);
        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testToPsrRequestReturnsRequestWithEqualMethod(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new Request('', 'POST');
        $target = $adapter->toPsrRequest($source);
        self::assertSame('POST', $target->getMethod());
    }

    public function testToPsrRequestReturnsRequestWithAllAddedHeaders(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new Request('');
        $source->setHeaders(['a' => 'b', 'c' => ['d', 'e']]);
        $target = $adapter->toPsrRequest($source);
        $actualHeaders = \array_map([$target, 'getHeader'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    /**
     * @param array       $sourceVersions
     * @param string|null $selectedVersion
     * @param string      $targetVersion
     * @dataProvider providerSuccessfulProtocolVersions
     */
    public function testToPsrRequestReturnsRequestWithMatchingProtocolVersion(
        array $sourceVersions,
        ?string $selectedVersion,
        string $targetVersion
    ): void {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new Request('');
        $source->setProtocolVersions($sourceVersions);
        $target = $adapter->toPsrRequest($source, $selectedVersion);
        self::assertSame($targetVersion, $target->getProtocolVersion());
    }

    public function providerSuccessfulProtocolVersions(): array
    {
        return [
            'Default version is set when available in list and not explicitly provided' => [['1.1', '2'], null, '1.1'],
            'The only available version is picked from list if not explicitly provided' => [['2'], null, '2'],
            'Explicitly provided version is set when available in list' => [['1.1', '2'], '2', '2'],
        ];
    }

    public function testToPsrRequestThrowsExceptionIfProvidedVersionNotInSource(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new Request('');
        $source->setProtocolVersions(['2']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source request doesn\'t support provided HTTP protocol version: 1.1');
        $adapter->toPsrRequest($source, '1.1');
    }

    public function testToPsrRequestThrowsExceptionIfDefaultVersionNotInSource(): void
    {
        $adapter = new PsrAdapter(new RequestFactory());
        $source = new Request('');
        $source->setProtocolVersions(['1.0', '2']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can\'t choose HTTP protocol version automatically');
        $adapter->toPsrRequest($source);
    }
}
