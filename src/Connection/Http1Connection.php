<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\CombinedCancellationToken;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Http;
use Amp\Http\Client\Connection\Internal\Http1Parser;
use Amp\Http\Client\HarAttributes;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\getCurrentTime;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class Http1Connection implements Connection
{
    use ForbidSerialization;
    use ForbidCloning;

    private const PROTOCOL_VERSIONS = ['1.0', '1.1'];

    /** @var EncryptableSocket */
    private $socket;

    /** @var bool */
    private $busy = false;

    /** @var int Number of requests made on this connection. */
    private $requestCounter = 0;

    /** @var string|null Keep alive timeout watcher ID. */
    private $timeoutWatcher;

    /** @var int Keep-Alive timeout from last response. */
    private $priorTimeout = self::MAX_KEEP_ALIVE_TIMEOUT;

    /** @var callable[]|null */
    private $onClose = [];

    /** @var int */
    private $timeoutGracePeriod;

    /** @var int */
    private $estimatedClose;

    /** @var bool */
    private $explicitTimeout = false;

    /** @var SocketAddress */
    private $localAddress;

    /** @var SocketAddress */
    private $remoteAddress;

    /** @var TlsInfo|null */
    private $tlsInfo;

    public function __construct(EncryptableSocket $socket, int $timeoutGracePeriod)
    {
        $this->socket = $socket;
        $this->localAddress = $socket->getLocalAddress();
        $this->remoteAddress = $socket->getRemoteAddress();
        $this->tlsInfo = $socket->getTlsInfo();
        $this->timeoutGracePeriod = $timeoutGracePeriod;
        $this->estimatedClose = getCurrentTime() + self::MAX_KEEP_ALIVE_TIMEOUT * 1000;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function onClose(callable $onClose): void
    {
        if (!$this->socket || $this->socket->isClosed()) {
            Promise\rethrow(call($onClose, $this));
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): Promise
    {
        if ($this->socket) {
            $this->socket->close();
        }

        return $this->free();
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->remoteAddress;
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->tlsInfo;
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function getStream(Request $request): Promise
    {
        if ($this->busy || ($this->requestCounter && !$this->hasStreamFor($request))) {
            return new Success;
        }

        $this->busy = true;

        return new Success(HttpStream::fromConnection(
            $this,
            \Closure::fromCallable([$this, 'request']),
            \Closure::fromCallable([$this, 'release'])
        ));
    }

    private function free(): Promise
    {
        $this->estimatedClose = 0;

        if ($this->timeoutWatcher !== null) {
            Loop::cancel($this->timeoutWatcher);
        }

        if ($this->onClose !== null) {
            $onClose = $this->onClose;
            $this->onClose = null;

            foreach ($onClose as $callback) {
                asyncCall($callback, $this);
            }
        }

        return new Success;
    }

    private function hasStreamFor(Request $request): bool
    {
        $connectionUnlikelyToClose = $this->explicitTimeout && $this->getRemainingTime() > $this->timeoutGracePeriod;

        return !$this->busy
            && $this->socket
            && !$this->socket->isClosed()
            && ($connectionUnlikelyToClose || $request->isIdempotent());
    }

    /** @inheritdoc */
    private function request(Request $request, CancellationToken $cancellation): Promise
    {
        return call(function () use ($request, $cancellation) {
            ++$this->requestCounter;

            if ($this->timeoutWatcher !== null) {
                Loop::cancel($this->timeoutWatcher);
                $this->timeoutWatcher = null;
            }

            /** @var Request $request */
            $request = yield from $this->buildRequest($request);
            $protocolVersion = $this->determineProtocolVersion($request);

            $request->setProtocolVersions([$protocolVersion]);

            if ($request->getTransferTimeout() > 0) {
                $timeoutToken = new TimeoutCancellationToken($request->getTransferTimeout());
                $combinedCancellation = new CombinedCancellationToken($cancellation, $timeoutToken);
            } else {
                $combinedCancellation = $cancellation;
            }

            $id = $combinedCancellation->subscribe([$this, 'close']);

            try {
                $request->setAttribute(HarAttributes::TIME_SEND, getCurrentTime());
                yield from $this->writeRequest($request, $protocolVersion, $combinedCancellation);
                $request->setAttribute(HarAttributes::TIME_WAIT, getCurrentTime());
                return yield from $this->readResponse($request, $cancellation, $combinedCancellation);
            } finally {
                $combinedCancellation->unsubscribe($id);
                $cancellation->throwIfRequested();
            }
        });
    }

    private function release(): void
    {
        $this->busy = false;
    }

    private function buildRequest(Request $request): \Generator
    {
        /** @var array $headers */
        $headers = yield $request->getBody()->getHeaders();
        foreach ($headers as $name => $header) {
            if (!$request->hasHeader($name)) {
                $request->setHeaders([$name => $header]);
            }
        }

        /** @var Request $request */
        $request = yield from $this->normalizeRequestBodyHeaders($request);

        // Always normalize this as last item, because we need to strip sensitive headers
        $request = $this->normalizeTraceRequest($request);

        return $request;
    }

    /**
     * @param Request           $request
     * @param CancellationToken $originalCancellation
     * @param CancellationToken $readingCancellation
     *
     * @return \Generator
     * @throws ParseException
     * @throws SocketException
     * @throws CancelledException
     */
    private function readResponse(
        Request $request,
        CancellationToken $originalCancellation,
        CancellationToken $readingCancellation
    ): \Generator {
        $bodyEmitter = new Emitter;

        $backpressure = new Success;
        $bodyCallback = static function ($data) use ($bodyEmitter, &$backpressure): void {
            $backpressure = $bodyEmitter->emit($data);
        };

        $trailersDeferred = new Deferred;

        $trailers = [];
        $trailersCallback = static function (array $headers) use (&$trailers): void {
            $trailers = $headers;
        };

        $parser = new Http1Parser($request, $bodyCallback, $trailersCallback);

        $firstRead = true;

        try {
            while (null !== $chunk = yield $this->socket->read()) {
                if ($firstRead) {
                    $request->setAttribute(HarAttributes::TIME_RECEIVE, getCurrentTime());
                    $firstRead = false;
                }

                parseChunk:
                $response = $parser->parse($chunk);
                if ($response === null) {
                    continue;
                }

                $status = $response->getStatus();

                if ($status === Http\Status::SWITCHING_PROTOCOLS) {
                    $connection = Http\createFieldValueComponentMap(Http\parseFieldValueComponents(
                        $response,
                        'connection'
                    ));
                    if (!isset($connection['upgrade'])) {
                        throw new HttpException('Switching protocols response missing "Connection: upgrade" header');
                    }

                    if (!$response->hasHeader('upgrade')) {
                        throw new HttpException('Switching protocols response missing "Upgrade" header');
                    }

                    if (($onUpgrade = $request->getUpgradeHandler()) === null) {
                        throw new HttpException('Received switching protocols response without upgrade handler callback');
                    }

                    $socket = new UpgradedSocket($this->socket, $parser->getBuffer());
                    $this->socket = null;
                    asyncCall($onUpgrade, $socket, clone $request, $response);

                    $this->free(); // Close this connection without closing socket.

                    $trailersDeferred->resolve($trailers);

                    return $response;
                }

                if ($status < Http\Status::OK) {
                    $chunk = $parser->getBuffer();
                    $parser = new Http1Parser($request, $bodyCallback, $trailersCallback);
                    goto parseChunk;
                }

                if ($status === Http\Status::OK && $request->getMethod() === 'CONNECT' && $request->getUpgradeHandler() !== null) {
                    $socket = new UpgradedSocket($this->socket, $parser->getBuffer());
                    $this->socket = null;
                    asyncCall($request->getUpgradeHandler(), $socket, clone $request, $response);

                    return $response;
                }

                $bodyCancellationSource = new CancellationTokenSource;
                $bodyCancellationToken = new CombinedCancellationToken(
                    $readingCancellation,
                    $bodyCancellationSource->getToken()
                );

                $response->setTrailers($trailersDeferred->promise());
                $response->setBody(new ResponseBodyStream(
                    new IteratorStream($bodyEmitter->iterate()),
                    $bodyCancellationSource
                ));

                // Read body async
                asyncCall(function () use (
                    $parser,
                    $request,
                    $response,
                    $bodyEmitter,
                    $trailersDeferred,
                    $originalCancellation,
                    $readingCancellation,
                    $bodyCancellationToken,
                    &$backpressure,
                    &$trailers
                ) {
                    $id = $bodyCancellationToken->subscribe([$this, 'close']);

                    try {
                        // Required, otherwise responses without body hang
                        if (!$parser->isComplete()) {
                            // Directly parse again in case we already have the full body but aborted parsing
                            // to resolve promise with headers.
                            $chunk = null;

                            do {
                                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                                $parser->parse($chunk);
                                /** @noinspection NotOptimalIfConditionsInspection */
                                if ($parser->isComplete()) {
                                    break;
                                }

                                if (!$backpressure instanceof Success) {
                                    yield $this->withCancellation($backpressure, $bodyCancellationToken);
                                }
                            } while (null !== $chunk = yield $this->socket->read());

                            $originalCancellation->throwIfRequested();

                            if ($readingCancellation->isRequested()) {
                                throw new TimeoutException('Allowed transfer timeout exceeded, took longer than ' . $request->getTransferTimeout() . ' ms');
                            }

                            $bodyCancellationToken->throwIfRequested();

                            // Ignore check if neither content-length nor chunked encoding are given.
                            if (!$parser->isComplete() && $parser->getState() !== Http1Parser::BODY_IDENTITY_EOF) {
                                throw new SocketException('Socket disconnected prior to response completion');
                            }
                        }

                        $timeout = $this->determineKeepAliveTimeout($response);

                        if ($timeout > 0 && $parser->getState() !== Http1Parser::BODY_IDENTITY_EOF) {
                            $this->timeoutWatcher = Loop::delay($timeout * 1000, [$this, 'close']);
                            Loop::unreference($this->timeoutWatcher);
                            $this->estimatedClose = getCurrentTime() + $timeout * 1000;
                        } else {
                            $this->close();
                        }

                        $this->busy = false;

                        $request->setAttribute(HarAttributes::TIME_COMPLETE, getCurrentTime());

                        $bodyEmitter->complete();
                        $trailersDeferred->resolve($trailers);
                    } catch (\Throwable $e) {
                        $this->close();

                        $bodyEmitter->fail($e);
                        $trailersDeferred->fail($e);
                    } finally {
                        $bodyCancellationToken->unsubscribe($id);
                    }
                });

                return $response;
            }

            $originalCancellation->throwIfRequested();

            throw new SocketException('Receiving the response headers failed, because the socket closed early');
        } catch (StreamException $e) {
            throw new SocketException('Receiving the response headers failed: ' . $e->getMessage());
        }
    }

    /**
     * @return int Approximate number of milliseconds remaining until the connection is closed.
     */
    private function getRemainingTime(): int
    {
        return \max(0, $this->estimatedClose - getCurrentTime());
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

    private function normalizeRequestBodyHeaders(Request $request): \Generator
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
            $request->setHeader('host', \substr($host, 0, -3));
        } elseif ($request->getUri()->getScheme() === 'https' && \strpos($host, ':443') === \strlen($host) - 4) {
            $request->setHeader('host', \substr($host, 0, -4));
        } else {
            $request->setHeader('host', $host);
        }

        if ($request->hasHeader("transfer-encoding")) {
            $request->removeHeader("content-length");
            return $request;
        }

        if ($request->hasHeader("content-length")) {
            return $request;
        }

        /** @var RequestBody $body */
        $body = $request->getBody();
        $bodyLength = yield $body->getBodyLength();

        if ($bodyLength === 0) {
            $request->setHeader('content-length', '0');
            $request->removeHeader('transfer-encoding');
        } elseif ($bodyLength > 0) {
            $request->setHeader("content-length", $bodyLength);
            $request->removeHeader("transfer-encoding");
        } else {
            $request->setHeader("transfer-encoding", "chunked");
        }

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
        $request->setBody(null);

        // Remove all body and sensitive headers
        $request->setHeaders([
            "transfer-encoding" => [],
            "content-length" => [],
            "authorization" => [],
            "proxy-authorization" => [],
            "cookie" => [],
        ]);

        return $request;
    }

    private function determineKeepAliveTimeout(Response $response): int
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('connection');
        $responseConnHeader = $response->getHeader('connection');

        if (!\strcasecmp($requestConnHeader, 'close')) {
            return 0;
        }

        if ($response->getProtocolVersion() === '1.0') {
            return 0;
        }

        if (!\strcasecmp($responseConnHeader, 'close')) {
            return 0;
        }

        $params = Http\createFieldValueComponentMap(Http\parseFieldValueComponents($response, 'keep-alive'));

        $timeout = (int) ($params['timeout'] ?? $this->priorTimeout);
        if (isset($params['timeout'])) {
            $this->explicitTimeout = true;
        }

        return $this->priorTimeout = \min(\max(0, $timeout), self::MAX_KEEP_ALIVE_TIMEOUT);
    }

    private function determineProtocolVersion(Request $request): string
    {
        $protocolVersions = $request->getProtocolVersions();

        if (\in_array("1.1", $protocolVersions, true)) {
            return "1.1";
        }

        if (\in_array("1.0", $protocolVersions, true)) {
            return "1.0";
        }

        throw new InvalidRequestException(
            $request,
            "None of the requested protocol versions is supported: " . \implode(", ", $protocolVersions)
        );
    }

    private function writeRequest(
        Request $request,
        string $protocolVersion,
        CancellationToken $cancellation
    ): \Generator {
        try {
            $rawHeaders = $this->generateRawHeader($request, $protocolVersion);
            yield $this->socket->write($rawHeaders);

            if ($request->getMethod() === 'CONNECT') {
                return;
            }

            $body = $request->getBody()->createBodyStream();
            $chunking = $request->getHeader("transfer-encoding") === "chunked";
            $remainingBytes = $request->getHeader("content-length");

            if ($chunking && $protocolVersion === "1.0") {
                throw new InvalidRequestException($request, "Can't send chunked bodies over HTTP/1.0");
            }

            // We always buffer the last chunk to make sure we don't write $contentLength bytes if the body is too long.
            $buffer = "";

            while (null !== $chunk = yield $body->read()) {
                $cancellation->throwIfRequested();

                if ($chunk === "") {
                    continue;
                }

                if ($chunking) {
                    $chunk = \dechex(\strlen($chunk)) . "\r\n" . $chunk . "\r\n";
                } elseif ($remainingBytes !== null) {
                    $remainingBytes -= \strlen($chunk);

                    if ($remainingBytes < 0) {
                        throw new InvalidRequestException(
                            $request,
                            "Body contained more bytes than specified in Content-Length, aborting request"
                        );
                    }
                }

                yield $this->socket->write($buffer);
                $buffer = $chunk;
            }

            $cancellation->throwIfRequested();

            // Flush last buffered chunk.
            yield $this->socket->write($buffer);

            if ($chunking) {
                yield $this->socket->write("0\r\n\r\n");
            } elseif ($remainingBytes !== null && $remainingBytes > 0) {
                throw new InvalidRequestException(
                    $request,
                    "Body contained fewer bytes than specified in Content-Length, aborting request"
                );
            }
        } catch (StreamException $exception) {
            throw new SocketException('Socket disconnected prior to response completion');
        }
    }

    /**
     * @param Request $request
     * @param string  $protocolVersion
     *
     * @return string
     *
     * @throws HttpException
     */
    private function generateRawHeader(Request $request, string $protocolVersion): string
    {
        $uri = $request->getUri();
        $requestUri = $uri->getPath() ?: '/';

        if ('' !== $query = $uri->getQuery()) {
            $requestUri .= '?' . $query;
        }

        if ($request->getMethod() === 'CONNECT') {
            $defaultPort = $uri->getScheme() === 'https' ? 443 : 80;
            $requestUri = $uri->getHost() . ':' . ($uri->getPort() ?? $defaultPort);
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
