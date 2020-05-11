<?php

declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Http\Client\Body\PsrStreamBody;
use Amp\Http\Client\Internal\PsrRequestStream;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

final class PsrAdapter
{

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    public function __construct(RequestFactoryInterface $requestFactory)
    {
        $this->requestFactory = $requestFactory;
    }

    public function fromPsrRequest(RequestInterface $source): Request
    {
        $target = new Request($source->getUri(), $source->getMethod());
        $target->setHeaders($source->getHeaders());
        $target->setProtocolVersions([$source->getProtocolVersion()]);
        $target->setBody(new PsrStreamBody($source->getBody()));

        return $target;
    }

    public function toPsrRequest(
        Request $source,
        ?string $protocolVersion = null
    ): RequestInterface {
        $target = $this
            ->requestFactory
            ->createRequest($source->getMethod(), $source->getUri())
            ->withBody(new PsrRequestStream($source->getBody()->createBodyStream()));
        foreach ($source->getHeaders() as $headerName => $headerValues) {
            $target = $target->withHeader($headerName, $headerValues);
        }
        $protocolVersions = $source->getProtocolVersions();
        if (isset($protocolVersion)) {
            if (!\in_array($protocolVersion, $protocolVersions)) {
                throw new \RuntimeException(
                    "Source request doesn't support provided HTTP protocol version: {$protocolVersion}"
                );
            }

            return $target->withProtocolVersion($protocolVersion);
        }
        if (\count($protocolVersions) == 1) {
            return $target->withProtocolVersion($protocolVersions[0]);
        }

        if (!\in_array($target->getProtocolVersion(), $protocolVersions)) {
            throw new \RuntimeException("Can't choose HTTP protocol version automatically");
        }

        return $target;
    }
}
