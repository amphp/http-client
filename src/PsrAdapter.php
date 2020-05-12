<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\Coroutine;
use Amp\Http\Client\Body\PsrStreamBody;
use Amp\Http\Client\Internal\PsrInputStream;
use Amp\Promise;
use Amp\Success;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function Amp\call;

final class PsrAdapter
{
    /**
     * @param RequestInterface $source
     * @return Promise<Request>
     */
    public function fromPsrRequest(RequestInterface $source): Promise
    {
        $target = new Request($source->getUri(), $source->getMethod());
        $target->setHeaders($source->getHeaders());
        $target->setProtocolVersions([$source->getProtocolVersion()]);
        $target->setBody(new PsrStreamBody($source->getBody()));

        return new Success($target);
    }

    /**
     * @param ResponseInterface $source
     * @param Request           $request
     * @param Response|null     $previousResponse
     * @return Promise<Response>
     */
    public function fromPsrResponse(
        ResponseInterface $source,
        Request $request,
        ?Response $previousResponse = null
    ): Promise {
        $response = new Response(
            $source->getProtocolVersion(),
            $source->getStatusCode(),
            $source->getReasonPhrase(),
            $source->getHeaders(),
            new PsrInputStream($source->getBody()),
            $request,
            null,
            $previousResponse
        );

        return new Success($response);
    }

    /**
     * @param RequestFactoryInterface $requestFactory
     * @param Request                 $source
     * @param string|null             $protocolVersion
     * @return Promise<RequestInterface>
     */
    public function toPsrRequest(
        RequestFactoryInterface $requestFactory,
        Request $source,
        ?string $protocolVersion = null
    ): Promise {
        $target = $this->toPsrRequestWithoutBody($requestFactory, $source, $protocolVersion);

        return call(
            function () use ($target, $source) {
                yield new Coroutine(
                    $this->copyInputToPsrStream($source->getBody()->createBodyStream(), $target->getBody())
                );

                return $target;
            }
        );
    }

    private function copyInputToPsrStream(InputStream $source, StreamInterface $target): \Generator
    {
        while (null !== $data = yield $source->read()) {
            $target->write($data);
        }
        $target->rewind();
    }

    private function toPsrRequestWithoutBody(
        RequestFactoryInterface $requestFactory,
        Request $source,
        ?string $protocolVersion = null
    ): RequestInterface {
        $target = $requestFactory
            ->createRequest($source->getMethod(), $source->getUri());
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

    /**
     * @param ResponseFactoryInterface $responseFactory
     * @param Response                 $response
     * @return Promise<ResponseInterface>
     */
    public function toPsrResponse(ResponseFactoryInterface $responseFactory, Response $response): Promise
    {
        $psrResponse = $responseFactory
            ->createResponse($response->getStatus(), $response->getReason())
            ->withProtocolVersion($response->getProtocolVersion());
        foreach ($response->getHeaders() as $headerName => $headerValues) {
            $psrResponse = $psrResponse->withHeader($headerName, $headerValues);
        }

        return call(
            function () use ($psrResponse, $response) {
                yield new Coroutine($this->copyInputToPsrStream($response->getBody(), $psrResponse->getBody()));

                return $psrResponse;
            }
        );
    }
}
