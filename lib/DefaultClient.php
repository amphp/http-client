<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Emitter;
use Amp\Failure;
use Amp\Http\Client\Internal\CombinedCancellationToken;
use Amp\Http\Client\Internal\Parser;
use Amp\Http\Client\Internal\RequestCycle;
use Amp\Loop;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\ResourceSocket;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use League\Uri;
use Psr\Http\Message\UriInterface;
use function Amp\asyncCall;
use function Amp\call;

/**
 * Standard client implementation.
 *
 * Use the `Client` interface for your type declarations so people can use composition to add layers like caching.
 *
 * @see Client
 */
final class DefaultClient implements Client
{
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; amphp/http-client)';

    private $socketPool;
    private $connectContext;
    private $hasZlib;

    public function __construct(
        ?HttpSocketPool $socketPool = null,
        ?ConnectContext $connectContext = null
    ) {
        $this->socketPool = $socketPool ?? new HttpSocketPool;
        $this->connectContext = $connectContext ?? new ConnectContext;
        $this->hasZlib = \extension_loaded('zlib');
    }

    /** @inheritdoc */
    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
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

            /** @var Response $response */
            $response = yield $this->doRequest($request, $cancellation);

            return $response;
        });
    }

    private function doRequest(
        Request $request,
        ?CancellationToken $cancellation = null
    ): Promise {
        $deferred = new Deferred;

        $requestCycle = new RequestCycle;
        $requestCycle->request = $request;
        $requestCycle->deferred = $deferred;
        $requestCycle->bodyDeferred = new Deferred;
        $requestCycle->body = new Emitter;
        $requestCycle->cancellation = $cancellation ?? new NullCancellationToken;

        $protocolVersions = $request->getProtocolVersions();

        if (\in_array("1.1", $protocolVersions, true)) {
            $requestCycle->protocolVersion = "1.1";
        } elseif (\in_array("1.0", $protocolVersions, true)) {
            $requestCycle->protocolVersion = "1.0";
        } else {
            return new Failure(new HttpException(
                "None of the requested protocol versions are supported: " . \implode(", ", $protocolVersions)
            ));
        }

        asyncCall(function () use ($requestCycle) {
            try {
                yield from $this->doWrite($requestCycle);
            } catch (\Throwable $e) {
                $this->fail($requestCycle, $e);
            }
        });

        return $deferred->promise();
    }

    /**
     * @param RequestCycle      $requestCycle
     * @param EncryptableSocket $socket
     * @param ConnectionInfo    $connectionInfo
     *
     * @return \Generator
     * @throws DnsException
     * @throws HttpException
     * @throws SocketException
     */
    private function doRead(
        RequestCycle $requestCycle,
        EncryptableSocket $socket,
        ConnectionInfo $connectionInfo
    ): \Generator {
        try {
            $backpressure = new Success;
            $bodyCallback = $requestCycle->request->getOptions()->isDiscardBody()
                ? null
                : static function ($data) use ($requestCycle, &$backpressure) {
                    $backpressure = $requestCycle->body->emit($data);
                };

            $parser = new Parser($bodyCallback);

            $parser->enqueueResponseMethodMatch($requestCycle->request->getMethod());
            $parser->setHeaderSizeLimit($requestCycle->request->getOptions()->getHeaderSizeLimit());
            $parser->setBodySizeLimit($requestCycle->request->getOptions()->getBodySizeLimit());

            while (null !== $chunk = yield $socket->read()) {
                $requestCycle->cancellation->throwIfRequested();

                $parseResult = $parser->parse($chunk);

                if (!$parseResult) {
                    continue;
                }

                $parseResult["headers"] = \array_change_key_case($parseResult["headers"], \CASE_LOWER);

                $response = $this->finalizeResponse($requestCycle, $parseResult, $connectionInfo);
                $shouldCloseSocketAfterResponse = $this->shouldCloseSocketAfterResponse($response);
                $ignoreIncompleteBodyCheck = false;
                $responseHeaders = $response->getHeaders();

                if ($requestCycle->deferred) {
                    $deferred = $requestCycle->deferred;
                    $requestCycle->deferred = null;
                    $deferred->resolve($response);
                    $response = null; // clear references
                    $deferred = null; // there's also a reference in the deferred
                } else {
                    return;
                }

                // Required, otherwise responses without body hang
                if ($parseResult["headersOnly"]) {
                    // Directly parse again in case we already have the full body but aborted parsing
                    // to resolve promise with headers.
                    $chunk = null;

                    do {
                        try {
                            $parseResult = $parser->parse($chunk);
                        } catch (ParseException $e) {
                            $this->fail($requestCycle, $e);
                            throw $e;
                        }

                        if ($parseResult) {
                            break;
                        }

                        if (!$backpressure instanceof Success) {
                            yield $this->withCancellation($backpressure, $requestCycle->cancellation);
                        }

                        if ($requestCycle->bodyTooLarge) {
                            throw new HttpException("Response body exceeded the specified size limit");
                        }
                    } while (null !== $chunk = yield $socket->read());

                    $parserState = $parser->getState();
                    if ($parserState !== Parser::AWAITING_HEADERS) {
                        // Ignore check if neither content-length nor chunked encoding are given.
                        $ignoreIncompleteBodyCheck = $parserState === Parser::BODY_IDENTITY_EOF &&
                            !isset($responseHeaders["content-length"]) &&
                            \strcasecmp('identity', $responseHeaders['transfer-encoding'][0] ?? "");

                        if (!$ignoreIncompleteBodyCheck) {
                            throw new SocketException(\sprintf(
                                'Socket disconnected prior to response completion (Parser state: %s)',
                                $parserState
                            ));
                        }
                    }
                }

                if ($shouldCloseSocketAfterResponse || $ignoreIncompleteBodyCheck) {
                    $this->socketPool->clear($socket);
                    $socket->close();
                } else {
                    $this->socketPool->checkin($socket);
                }

                $requestCycle->socket = null;

                // Complete body AFTER socket checkin, so the socket can be reused for a potential redirect
                $body = $requestCycle->body;
                $requestCycle->body = null;

                $bodyDeferred = $requestCycle->bodyDeferred;
                $requestCycle->bodyDeferred = null;

                $body->complete();
                $bodyDeferred->resolve();

                return;
            }
        } catch (\Throwable $e) {
            $this->fail($requestCycle, $e);

            return;
        }

        if (!$socket->isClosed()) {
            $requestCycle->socket = null;
            $this->socketPool->clear($socket);
            $socket->close();
        }

        // Required, because if the write fails, the read() call immediately resolves.
        yield new Delayed(0);

        if ($requestCycle->deferred === null) {
            return;
        }

        $parserState = $parser->getState();

        if ($parserState === Parser::AWAITING_HEADERS && $requestCycle->retryCount < 1) {
            $requestCycle->retryCount++;
            yield from $this->doWrite($requestCycle);
        } else {
            $this->fail($requestCycle, new SocketException(\sprintf(
                'Socket disconnected prior to response completion (Parser state: %s)',
                $parserState
            )));
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
     * @param RequestCycle $requestCycle
     *
     * @return \Generator
     *
     * @throws DnsException
     * @throws HttpException
     * @throws SocketException
     */
    private function doWrite(RequestCycle $requestCycle): \Generator
    {
        $timeout = $requestCycle->request->getOptions()->getTransferTimeout();
        $timeoutToken = new NullCancellationToken;

        if ($timeout > 0) {
            $transferTimeoutWatcher = Loop::delay($timeout, function () use ($requestCycle, $timeout) {
                $this->fail($requestCycle, new TimeoutException(
                    \sprintf('Allowed transfer timeout exceeded: %d ms', $timeout)
                ));
            });

            $requestCycle->bodyDeferred->promise()->onResolve(static function () use ($transferTimeoutWatcher) {
                Loop::cancel($transferTimeoutWatcher);
            });

            $timeoutToken = new TimeoutCancellationToken($timeout);
        }

        $authority = $this->generateAuthorityFromUri($requestCycle->request->getUri());
        $socketCheckoutUri = $requestCycle->request->getUri()->getScheme() . "://{$authority}";
        $connectTimeoutToken = new CombinedCancellationToken($requestCycle->cancellation, $timeoutToken);

        if ($requestCycle->request->getUri()->getScheme() === 'https') {
            $tlsContext = $this->connectContext->getTlsContext() ?? new ClientTlsContext($requestCycle->request->getUri()->getHost());
            $tlsContext = $tlsContext->withPeerName($requestCycle->request->getUri()->getHost());
            $tlsContext = $tlsContext->withPeerCapturing();
            $connectContext = $this->connectContext->withTlsContext($tlsContext);
        } else {
            $connectContext = $this->connectContext;
        }

        try {
            /** @var EncryptableSocket $socket */
            $socket = yield $this->socketPool->checkout($socketCheckoutUri, $connectContext, $connectTimeoutToken);
            $requestCycle->socket = $socket;
        } catch (Socket\SocketException $e) {
            throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
        } catch (CancelledException $e) {
            // In case of a user cancellation request, throw the expected exception
            $requestCycle->cancellation->throwIfRequested();

            // Otherwise we ran into a timeout of our TimeoutCancellationToken
            throw new SocketException(\sprintf("Connection to '%s' timed out", $authority), 0, $e);
        }

        $cancellation = $requestCycle->cancellation->subscribe(function ($error) use ($requestCycle) {
            $this->fail($requestCycle, $error);
        });

        try {
            if ($requestCycle->request->getUri()->getScheme() === 'https') {
                $tlsState = $socket->getTlsState();
                if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                    yield $socket->setupTls();
                } else {
                    throw new HttpException('Failed to setup TLS connection, connection was in TLS state "' . $tlsState . '"');
                }
            }

            // Collect this here, because it fails in case the remote closes the connection directly.
            $connectionInfo = $this->collectConnectionInfo($socket);

            $rawHeaders = $this->generateRawRequestHeaders($requestCycle->request, $requestCycle->protocolVersion);
            yield $socket->write($rawHeaders);

            $body = $requestCycle->request->getBody()->createBodyStream();
            $chunking = $requestCycle->request->getHeader("transfer-encoding") === "chunked";
            $remainingBytes = $requestCycle->request->getHeader("content-length");

            if ($chunking && $requestCycle->protocolVersion === "1.0") {
                throw new HttpException("Can't send chunked bodies over HTTP/1.0");
            }

            // We always buffer the last chunk to make sure we don't write $contentLength bytes if the body is too long.
            $buffer = "";

            while (null !== $chunk = yield $body->read()) {
                $requestCycle->cancellation->throwIfRequested();

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

                yield $socket->write($buffer);
                $buffer = $chunk;
            }

            // Flush last buffered chunk.
            yield $socket->write($buffer);

            if ($chunking) {
                yield $socket->write("0\r\n\r\n");
            } elseif ($remainingBytes !== null && $remainingBytes > 0) {
                throw new HttpException("Body contained fewer bytes than specified in Content-Length, aborting request");
            }

            yield from $this->doRead($requestCycle, $socket, $connectionInfo);
        } catch (StreamException $exception) {
            $this->fail($requestCycle, new SocketException('Socket disconnected prior to response completion'));
        } finally {
            $requestCycle->cancellation->unsubscribe($cancellation);
        }
    }

    private function fail(RequestCycle $requestCycle, \Throwable $error): void
    {
        $toFails = [];
        $socket = null;

        if ($requestCycle->deferred) {
            $toFails[] = $requestCycle->deferred;
            $requestCycle->deferred = null;
        }

        if ($requestCycle->body) {
            $toFails[] = $requestCycle->body;
            $requestCycle->body = null;
        }

        if ($requestCycle->bodyDeferred) {
            $toFails[] = $requestCycle->bodyDeferred;
            $requestCycle->bodyDeferred = null;
        }

        if ($requestCycle->socket) {
            $this->socketPool->clear($requestCycle->socket);
            $socket = $requestCycle->socket;
            $requestCycle->socket = null;
            $socket->close();
        }

        foreach ($toFails as $toFail) {
            $toFail->fail($error);
        }
    }

    private function normalizeRequestBodyHeaders(Request $request): \Generator
    {
        if ($request->hasHeader("Transfer-Encoding")) {
            return $request->withoutHeader("Content-Length");
        }

        if ($request->hasHeader("Content-Length")) {
            return $request;
        }

        /** @var RequestBody $body */
        $body = $request->getBody();
        $bodyLength = yield $body->getBodyLength();

        if ($bodyLength === 0) {
            $request = $request->withHeader('Content-Length', '0');
            $request = $request->withoutHeader('Transfer-Encoding');
        } elseif ($bodyLength > 0) {
            $request = $request->withHeader("Content-Length", $bodyLength);
            $request = $request->withoutHeader("Transfer-Encoding");
        } else {
            $request = $request->withHeader("Transfer-Encoding", "chunked");
        }

        return $request;
    }

    private function normalizeRequestHeaders(Request $request): Request
    {
        $request = $this->normalizeRequestEncodingHeaderForZlib($request);
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

    private function normalizeRequestEncodingHeaderForZlib(Request $request): Request
    {
        if ($this->hasZlib) {
            return $request->withHeader('Accept-Encoding', 'gzip, deflate, identity');
        }

        return $request->withoutHeader('Accept-Encoding');
    }

    private function normalizeRequestHostHeader(Request $request): Request
    {
        if (!$request->hasHeader('host')) {
            $request = $request->withHeader('host', $this->generateAuthorityFromUri($request->getUri()));
        }

        return $request->withHeader('host', $this->normalizeHostHeader($request->getHeader('host')));
    }

    private function normalizeHostHeader(string $host): string
    {
        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80 or 443
        if (\strpos($host, ':80') === \strlen($host) - 3) {
            return \substr($host, 0, -3);
        }

        if (\strpos($host, ':443') === \strlen($host) - 4) {
            return \substr($host, 0, -4);
        }

        return $host;
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

    private function generateAuthorityFromUri(UriInterface $uri): string
    {
        $host = $uri->getHost();
        $port = $uri->getPort();

        return "{$host}:{$port}";
    }

    private function finalizeResponse(
        RequestCycle $requestCycle,
        array $parserResult,
        ConnectionInfo $connectionInfo
    ): Response {
        $body = new IteratorStream($requestCycle->body->iterate());

        if ($encoding = $this->determineCompressionEncoding($parserResult["headers"])) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $body = new ZlibInputStream($body, $encoding);
        }

        // Wrap the input stream so we can discard the body in case it's destructed but hasn't been consumed.
        // This allows reusing the connection for further requests. It's important to have __destruct in InputStream and
        // not in Payload, because an InputStream might be pulled out of Payload and used separately.
        $body = new class($body, $requestCycle, $this->socketPool) implements InputStream
        {
            private $body;
            private $bodySize = 0;
            private $requestCycle;
            private $socketPool;
            private $successfulEnd = false;

            public function __construct(InputStream $body, RequestCycle $requestCycle, HttpSocketPool $socketPool)
            {
                $this->body = $body;
                $this->requestCycle = $requestCycle;
                $this->socketPool = $socketPool;
            }

            public function read(): Promise
            {
                $promise = $this->body->read();
                $promise->onResolve(function ($error, $value) {
                    if ($value !== null) {
                        $this->bodySize += \strlen($value);
                        $maxBytes = $this->requestCycle->request->getOptions()->getBodySizeLimit();
                        if ($maxBytes !== 0 && $this->bodySize >= $maxBytes) {
                            $this->requestCycle->bodyTooLarge = true;
                        }
                    } elseif ($error === null) {
                        $this->successfulEnd = true;
                    }
                });

                return $promise;
            }

            public function __destruct()
            {
                if (!$this->successfulEnd && $this->requestCycle->socket) {
                    $this->socketPool->clear($this->requestCycle->socket);
                    $socket = $this->requestCycle->socket;
                    $this->requestCycle->socket = null;
                    $socket->close();
                }
            }
        };

        return new Response(
            $parserResult["protocol"],
            $parserResult["status"],
            $parserResult["reason"],
            $parserResult["headers"],
            $body,
            $requestCycle->request,
            null,
            new MetaInfo($connectionInfo)
        );
    }

    private function shouldCloseSocketAfterResponse(Response $response): bool
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('Connection');
        $responseConnHeader = $response->getHeader('Connection');

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

    private function determineCompressionEncoding(array $responseHeaders): int
    {
        if (!$this->hasZlib) {
            return 0;
        }

        if (!isset($responseHeaders["content-encoding"])) {
            return 0;
        }

        $contentEncodingHeader = \trim(\current($responseHeaders["content-encoding"]));

        if (\strcasecmp($contentEncodingHeader, 'gzip') === 0) {
            return \ZLIB_ENCODING_GZIP;
        }

        if (\strcasecmp($contentEncodingHeader, 'deflate') === 0) {
            return \ZLIB_ENCODING_DEFLATE;
        }

        return 0;
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
    private function generateRawRequestHeaders(Request $request, string $protocolVersion): string
    {
        $uri = $request->getUri();
        $uri = Uri\Http::createFromString($uri);

        $requestUri = $uri->getPath() ?: '/';

        if ($query = $uri->getQuery()) {
            $requestUri .= '?' . $query;
        }

        $head = $request->getMethod() . ' ' . $requestUri . ' HTTP/' . $protocolVersion . "\r\n";

        foreach ($request->getHeaders(true) as $field => $values) {
            if (\strcspn($field, "\r\n") !== \strlen($field)) {
                throw new HttpException("Blocked header injection attempt for header '{$field}'");
            }

            foreach ($values as $value) {
                if (\strcspn($value, "\r\n") !== \strlen($value)) {
                    throw new HttpException("Blocked header injection attempt for header '{$field}' with value '{$value}'");
                }

                $head .= "{$field}: {$value}\r\n";
            }
        }

        $head .= "\r\n";

        return $head;
    }

    /**
     * @param EncryptableSocket $socket
     *
     * @return ConnectionInfo
     * @throws SocketException
     */
    private function collectConnectionInfo(EncryptableSocket $socket): ConnectionInfo
    {
        if (!$socket instanceof ResourceSocket) {
            return new ConnectionInfo($socket->getLocalAddress(), $socket->getRemoteAddress());
        }

        $stream = $socket->getResource();

        if ($stream === null) {
            throw new SocketException("Socket closed before connection information could be collected");
        }

        $crypto = \stream_get_meta_data($stream)["crypto"] ?? null;

        return new ConnectionInfo(
            $socket->getLocalAddress(),
            $socket->getRemoteAddress(),
            $crypto ? TlsInfo::fromMetaData($crypto, \stream_context_get_options($stream)["ssl"]) : null
        );
    }
}
