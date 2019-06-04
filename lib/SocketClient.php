<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Http\Client\Internal\CombinedCancellationToken;
use Amp\Http\Client\Internal\Parser;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use function Amp\call;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class SocketClient implements Client
{
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; amphp/http-client)';

    private $socket;
    private $connectionInfo;
    private $backpressure;
    private $pending;

    public function __construct(
        EncryptableSocket $socket,
        ConnectionInfo $connectionInfo
    ) {
        $this->socket = $socket;
        $this->connectionInfo = $connectionInfo;
    }

    /** @inheritdoc */
    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
        if ($this->pending) {
            throw new HttpException(self::class . ' does not support concurrent requests');
        }

        $this->pending = true;

        return call(function () use ($request, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;

            /** @var array $headers */
            $headers = yield $request->getBody()->getHeaders();
            foreach ($headers as $name => $header) {
                if (!$request->hasHeader($name)) {
                    $request = $request->withHeaders([$name => $header]);
                }
            }

            /** @var Request $request */
            $request = yield from $this->normalizeRequestBodyHeaders($request);
            $request = $this->normalizeRequestHeaders($request);

            // Always normalize this as last item, because we need to strip sensitive headers
            $request = $this->normalizeTraceRequest($request);

            $protocolVersions = $request->getProtocolVersions();

            if (\in_array("1.1", $protocolVersions, true)) {
                $protocolVersion = "1.1";
            } elseif (\in_array("1.0", $protocolVersions, true)) {
                $protocolVersion = "1.0";
            } else {
                throw new HttpException(
                    "None of the requested protocol versions is supported: " . \implode(", ", $protocolVersions)
                );
            }

            $completionDeferred = new Deferred;

            $timeout = $request->getOptions()->getTransferTimeout();
            if ($timeout > 0) {
                // TODO: Exception message \sprintf('Allowed transfer timeout exceeded: %d ms', $timeout)
                $timeoutToken = new TimeoutCancellationToken($timeout);
                $subCancellation = new CombinedCancellationToken($cancellation, $timeoutToken);
            } else {
                $subCancellation = $cancellation;
            }

            $cancellationId = $subCancellation->subscribe(function () {
                $this->socket->close();
            });

            $completionDeferred->promise()->onResolve(static function () use ($subCancellation, $cancellationId) {
                $subCancellation->unsubscribe($cancellationId);
            });

            try {
                yield from $this->doWrite($request, $protocolVersion);

                return yield from $this->doRead($request, $subCancellation, $completionDeferred);
            } catch (HttpException $e) {
                $cancellation->throwIfRequested();

                throw $e;
            }
        });
    }

    /**
     * @param Request           $request
     * @param CancellationToken $cancellationToken
     * @param Deferred          $completionDeferred
     *
     * @return \Generator
     * @throws HttpException
     * @throws SocketException
     */
    private function doRead(
        Request $request,
        CancellationToken $cancellationToken,
        Deferred $completionDeferred
    ): \Generator {
        $bodyEmitter = new Emitter;

        $this->backpressure = new Success;
        $bodyCallback = $request->getOptions()->isDiscardBody()
            ? null
            : function ($data) use ($bodyEmitter) {
                $this->backpressure = $bodyEmitter->emit($data);
            };

        $parser = new Parser($bodyCallback);
        $parser->enqueueResponseMethodMatch($request->getMethod());
        $parser->setHeaderSizeLimit($request->getOptions()->getHeaderSizeLimit());
        $parser->setBodySizeLimit($request->getOptions()->getBodySizeLimit());

        try {
            while (null !== $chunk = yield $this->socket->read()) {
                $cancellationToken->throwIfRequested();

                $parseResult = $parser->parse($chunk);

                if (!$parseResult) {
                    continue;
                }

                $bodyCancellationSource = new CancellationTokenSource;
                $bodyCancellationToken = new CombinedCancellationToken($cancellationToken, $bodyCancellationSource->getToken());
                $bodyCancellationToken->subscribe(function () {
                    $this->socket->close();
                });

                $response = $this->finalizeResponse($request, $bodyEmitter, $parseResult, $this->connectionInfo, $bodyCancellationSource, $completionDeferred->promise());

                Promise\rethrow(new Coroutine($this->doReadBody($parser, $parseResult, $response, $bodyEmitter, $completionDeferred, $bodyCancellationToken)));

                return $response;
            }

            throw new SocketException('Reading the response failed, socket closed before a complete response was received');
        } catch (StreamException $e) {
            throw new SocketException('Reading the response failed: ' . $e->getMessage());
        }
    }

    /**
     * @param Parser            $parser
     * @param array             $parseResult
     * @param Response          $response
     * @param Emitter           $bodyEmitter
     * @param Deferred          $completionDeferred
     * @param CancellationToken $bodyCancellationToken
     *
     * @return \Generator
     */
    private function doReadBody(
        Parser $parser,
        array $parseResult,
        Response $response,
        Emitter $bodyEmitter,
        Deferred $completionDeferred,
        CancellationToken $bodyCancellationToken
    ): \Generator {
        try {
            $shouldCloseSocketAfterResponse = $this->shouldCloseSocketAfterResponse($response);
            $ignoreIncompleteBodyCheck = false;
            $responseHeaders = $response->getHeaders();

            // Required, otherwise responses without body hang
            if ($parseResult["headersOnly"]) {
                // Directly parse again in case we already have the full body but aborted parsing
                // to resolve promise with headers.
                $chunk = null;

                do {
                    /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                    $parseResult = $parser->parse($chunk);

                    if ($parseResult) {
                        break;
                    }

                    if (!$this->backpressure instanceof Success) {
                        yield $this->withCancellation($this->backpressure, $bodyCancellationToken);
                    }

                    /* if ($bodyTooLarge) {
                        throw new HttpException("Response body exceeded the specified size limit");
                    } */
                } while (null !== $chunk = yield $this->socket->read());

                $bodyCancellationToken->throwIfRequested();

                $parserState = $parser->getState();
                if ($parserState !== Parser::AWAITING_HEADERS) {
                    // Ignore check if neither content-length nor chunked encoding are given.
                    $ignoreIncompleteBodyCheck = $parserState === Parser::BODY_IDENTITY_EOF &&
                        !isset($responseHeaders["content-length"]) &&
                        \strcasecmp('identity', $responseHeaders['transfer-encoding'][0] ?? "");

                    if (!$ignoreIncompleteBodyCheck) {
                        throw new SocketException(\sprintf(
                            'Socket disconnected prior to response completion (parser state: %s)',
                            $parserState
                        ));
                    }
                }
            }

            if ($shouldCloseSocketAfterResponse || $ignoreIncompleteBodyCheck) {
                $this->socket->close();
            }

            $bodyEmitter->complete();
            $completionDeferred->resolve();
        } catch (\Throwable $e) {
            $this->socket->close();

            $bodyEmitter->fail($e);
            $completionDeferred->fail($e);
        } finally {
            $this->pending = false;
        }
    }

    private function withCancellation(Promise $promise, CancellationToken $cancellationToken): Promise
    {
        $deferred = new Deferred;
        $newPromise = $deferred->promise();

        $promise->onResolve(static function ($error, $value) use (&$deferred) {
            if ($deferred) {
                if ($error) {
                    $deferred->fail($error);
                    $deferred = null;
                } else {
                    $deferred->resolve($value);
                    $deferred = null;
                }
            }
        });

        $cancellationSubscription = $cancellationToken->subscribe(static function ($e) use (&$deferred) {
            if ($deferred) {
                $deferred->fail($e);
                $deferred = null;
            }
        });

        $newPromise->onResolve(static function () use ($cancellationToken, $cancellationSubscription) {
            $cancellationToken->unsubscribe($cancellationSubscription);
        });

        return $newPromise;
    }

    /**
     * @param Request $request
     * @param string  $protocolVersion
     *
     * @return \Generator
     *
     * @throws HttpException
     * @throws SocketException
     */
    private function doWrite(Request $request, string $protocolVersion): \Generator
    {
        try {
            $rawHeaders = $this->generateRawRequestHeader($request, $protocolVersion);
            yield $this->socket->write($rawHeaders);

            $body = $request->getBody()->createBodyStream();
            $chunking = $request->getHeader("transfer-encoding") === "chunked";
            $remainingBytes = $request->getHeader("content-length");

            if ($chunking && $protocolVersion === "1.0") {
                throw new HttpException("Can't send chunked bodies over HTTP/1.0");
            }

            // We always buffer the last chunk to make sure we don't write $contentLength bytes if the body is too long.
            $buffer = "";

            while (null !== $chunk = yield $body->read()) {
                if ($chunk === "") {
                    continue;
                }

                if ($chunking) {
                    $chunk = \dechex(\strlen($chunk)) . "\r\n" . $chunk . "\r\n";
                } elseif ($remainingBytes !== null) {
                    $remainingBytes -= \strlen($chunk);

                    if ($remainingBytes < 0) {
                        throw new HttpException("Body contained more bytes than specified in Content-Length, aborting request");
                    }
                }

                yield $this->socket->write($buffer);
                $buffer = $chunk;
            }

            // Flush last buffered chunk.
            yield $this->socket->write($buffer);

            if ($chunking) {
                yield $this->socket->write("0\r\n\r\n");
            } elseif ($remainingBytes !== null && $remainingBytes > 0) {
                throw new HttpException("Body contained fewer bytes than specified in Content-Length, aborting request");
            }
        } catch (StreamException $exception) {
            throw new SocketException('Socket disconnected prior to response completion');
        }
    }

    private function normalizeRequestBodyHeaders(Request $request): \Generator
    {
        if ($request->hasHeader("transfer-encoding")) {
            return $request->withoutHeader("content-length");
        }

        if ($request->hasHeader("content-length")) {
            return $request;
        }

        /** @var RequestBody $body */
        $body = $request->getBody();
        $bodyLength = yield $body->getBodyLength();

        if ($bodyLength === 0) {
            $request = $request->withHeader('content-length', '0');
            $request = $request->withoutHeader('transfer-encoding');
        } elseif ($bodyLength > 0) {
            $request = $request->withHeader("content-length", $bodyLength);
            $request = $request->withoutHeader("transfer-encoding");
        } else {
            $request = $request->withHeader("transfer-encoding", "chunked");
        }

        return $request;
    }

    private function normalizeRequestHeaders(Request $request): Request
    {
        $request = $this->normalizeRequestHostHeader($request);
        $request = $this->normalizeRequestUserAgent($request);
        $request = $this->normalizeRequestAcceptHeader($request);

        return $request;
    }

    private function normalizeTraceRequest(Request $request): Request
    {
        $method = $request->getMethod();

        if ($method !== 'TRACE') {
            return $request;
        }

        // https://tools.ietf.org/html/rfc7231#section-4.3.8
        /** @var Request $request */
        $request = $request->withBody(null);

        // Remove all body and sensitive headers
        $request = $request->withHeaders([
            "Transfer-Encoding" => [],
            "Content-Length" => [],
            "Authorization" => [],
            "Proxy-Authorization" => [],
            "Cookie" => [],
        ]);

        return $request;
    }

    private function normalizeRequestHostHeader(Request $request): Request
    {
        if ($request->hasHeader('host')) {
            $host = $request->getHeader('host');
        } else {
            $host = $request->getUri()->withUserInfo('')->getAuthority();
        }

        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80 or 443
        if ($request->getUri()->getScheme() === 'http' && \strpos($host, ':80') === \strlen($host) - 3) {
            $request = $request->withHeader('host', \substr($host, 0, -3));
        } else if ($request->getUri()->getScheme() === 'https' && \strpos($host, ':443') === \strlen($host) - 4) {
            $request = $request->withHeader('host', \substr($host, 0, -4));
        } else {
            $request = $request->withHeader('host', $host);
        }

        return $request;
    }

    private function normalizeRequestUserAgent(Request $request): Request
    {
        if ($request->hasHeader('User-Agent')) {
            return $request;
        }

        return $request->withHeader('User-Agent', self::DEFAULT_USER_AGENT);
    }

    private function normalizeRequestAcceptHeader(Request $request): Request
    {
        if ($request->hasHeader('Accept')) {
            return $request;
        }

        return $request->withHeader('Accept', '*/*');
    }

    private function finalizeResponse(
        Request $request,
        Emitter $bodyEmitter,
        array $parserResult,
        ConnectionInfo $connectionInfo,
        CancellationTokenSource $bodyCancellation,
        Promise $completionPromise
    ): Response {
        $body = new IteratorStream($bodyEmitter->iterate());
        $body = new class($body, $bodyCancellation) implements InputStream
        {
            private $body;
            private $bodyCancellation;
            private $successfulEnd = false;

            public function __construct(InputStream $body, CancellationTokenSource $bodyCancellation)
            {
                $this->body = $body;
                $this->bodyCancellation = $bodyCancellation;
            }

            public function read(): Promise
            {
                $promise = $this->body->read();
                $promise->onResolve(function ($error, $value) {
                    if ($value === null && $error === null) {
                        $this->successfulEnd = true;
                    }
                });

                return $promise;
            }

            public function __destruct()
            {
                if (!$this->successfulEnd) {
                    $this->bodyCancellation->cancel();
                }
            }
        };

        return new Response(
            $parserResult["protocol"],
            $parserResult["status"],
            $parserResult["reason"],
            $parserResult["headers"],
            $body,
            $request,
            $connectionInfo,
            $completionPromise
        );
    }

    private function shouldCloseSocketAfterResponse(Response $response): bool
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('connection');
        $responseConnHeader = $response->getHeader('connection');

        if ($requestConnHeader && !\strcasecmp($requestConnHeader, 'close')) {
            return true;
        }

        if ($responseConnHeader && !\strcasecmp($responseConnHeader, 'close')) {
            return true;
        }

        if (!$responseConnHeader && $response->getProtocolVersion() === '1.0') {
            return true;
        }

        return false;
    }

    /**
     * @param Request $request
     * @param string  $protocolVersion
     *
     * @return string
     *
     * @throws HttpException
     *
     * @TODO Send absolute URIs in the request line when using a proxy server
     *       Right now this doesn't matter because all proxy requests use a CONNECT
     *       tunnel but this likely will not always be the case.
     */
    private function generateRawRequestHeader(Request $request, string $protocolVersion): string
    {
        $uri = $request->getUri();
        $requestUri = $uri->getPath() ?: '/';

        if ('' !== $query = $uri->getQuery()) {
            $requestUri .= '?' . $query;
        }

        $header = $request->getMethod() . ' ' . $requestUri . ' HTTP/' . $protocolVersion . "\r\n";

        try {
            $header .= Rfc7230::formatHeaders($request->getHeaders());
        } catch (InvalidHeaderException $e) {
            throw new HttpException($e->getMessage());
        }

        return $header . "\r\n";
    }
}
