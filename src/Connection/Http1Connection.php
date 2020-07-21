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
use Amp\Http\Client\Connection\Internal\RequestNormalizer;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
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
use Amp\TimeoutException as PromiseTimeoutException;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\getCurrentTime;
use function Amp\Http\Client\Internal\normalizeRequestPathWithQuery;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class Http1Connection implements Connection
{
    use ForbidSerialization;
    use ForbidCloning;

    private const MAX_KEEP_ALIVE_TIMEOUT = 60;
    private const PROTOCOL_VERSIONS = ['1.0', '1.1'];

    /** @var EncryptableSocket|null */
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
    private $lastUsedAt;

    /** @var bool */
    private $explicitTimeout = false;

    /** @var SocketAddress */
    private $localAddress;

    /** @var SocketAddress */
    private $remoteAddress;

    /** @var TlsInfo|null */
    private $tlsInfo;

    public function __construct(EncryptableSocket $socket, int $timeoutGracePeriod = 2000)
    {
        $this->socket = $socket;
        $this->localAddress = $socket->getLocalAddress();
        $this->remoteAddress = $socket->getRemoteAddress();
        $this->tlsInfo = $socket->getTlsInfo();
        $this->timeoutGracePeriod = $timeoutGracePeriod;
        $this->lastUsedAt = getCurrentTime();
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
        $this->socket = null;

        $this->lastUsedAt = 0;

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
        return !$this->busy
            && $this->socket
            && !$this->socket->isClosed()
            && ($this->getRemainingTime() > 0 || $request->isIdempotent());
    }

    /** @inheritdoc */
    private function request(Request $request, CancellationToken $cancellation, Stream $stream): Promise
    {
        return call(function () use ($request, $cancellation, $stream) {
            ++$this->requestCounter;

            if ($this->timeoutWatcher !== null) {
                Loop::cancel($this->timeoutWatcher);
                $this->timeoutWatcher = null;
            }

            yield RequestNormalizer::normalizeRequest($request);

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
                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->startSendingRequest($request, $stream);
                }

                yield from $this->writeRequest($request, $protocolVersion, $combinedCancellation);

                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->completeSendingRequest($request, $stream);
                }

                return yield from $this->readResponse($request, $cancellation, $combinedCancellation, $stream);
            } catch (\Throwable $e) {
                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->abort($request, $e);
                }

                if ($this->socket !== null) {
                    $this->socket->close();
                }

                throw $e;
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

    /**
     * @param Request           $request
     * @param CancellationToken $originalCancellation
     * @param CancellationToken $readingCancellation
     *
     * @param Stream            $stream
     *
     * @return \Generator
     * @throws CancelledException
     * @throws HttpException
     * @throws ParseException
     * @throws SocketException
     */
    private function readResponse(
        Request $request,
        CancellationToken $originalCancellation,
        CancellationToken $readingCancellation,
        Stream $stream
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

        $start = getCurrentTime();
        $timeout = $request->getInactivityTimeout();

        try {
            if ($this->socket === null) {
                throw new SocketException('Socket closed prior to response completion');
            }

            while (null !== $chunk = yield $timeout > 0
                    ? Promise\timeout($this->socket->read(), $timeout)
                    : $this->socket->read()
            ) {
                parseChunk:
                $response = $parser->parse($chunk);
                if ($response === null) {
                    if ($this->socket === null) {
                        throw new SocketException('Socket closed prior to response completion');
                    }

                    continue;
                }

                $this->lastUsedAt = getCurrentTime();

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

                    foreach ($request->getEventListeners() as $eventListener) {
                        yield $eventListener->completeReceivingResponse($request, $stream);
                    }

                    $trailersDeferred->resolve($trailers);

                    return $this->handleUpgradeResponse($request, $response, $parser->getBuffer());
                }

                if ($status < 200) { // 1XX responses (excluding 101, handled above)
                    $onInformationalResponse = $request->getInformationalResponseHandler();

                    if ($onInformationalResponse !== null) {
                        yield call($onInformationalResponse, $response);
                    }

                    $chunk = $parser->getBuffer();
                    $parser = new Http1Parser($request, $bodyCallback, $trailersCallback);
                    goto parseChunk;
                }

                foreach ($request->getEventListeners() as $eventListener) {
                    yield $eventListener->startReceivingResponse($request, $stream);
                }

                if ($status >= 200 && $status < 300 && $request->getMethod() === 'CONNECT') {
                    foreach ($request->getEventListeners() as $eventListener) {
                        yield $eventListener->completeReceivingResponse($request, $stream);
                    }

                    $trailersDeferred->resolve($trailers);

                    return $this->handleUpgradeResponse($request, $response, $parser->getBuffer());
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
                    $stream,
                    $timeout,
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

                            try {
                                /** @psalm-suppress PossiblyNullReference */
                                do {
                                    /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                                    $parser->parse($chunk);
                                    /**
                                     * @noinspection NotOptimalIfConditionsInspection
                                     * @psalm-suppress TypeDoesNotContainType
                                     */
                                    if ($parser->isComplete()) {
                                        break;
                                    }

                                    if (!$backpressure instanceof Success) {
                                        yield $this->withCancellation($backpressure, $bodyCancellationToken);
                                    }

                                    /** @psalm-suppress TypeDoesNotContainNull */
                                    if ($this->socket === null) {
                                        throw new SocketException('Socket closed prior to response completion');
                                    }
                                } while (null !== $chunk = yield $timeout > 0
                                    ? Promise\timeout($this->socket->read(), $timeout)
                                    : $this->socket->read()
                                );
                            } catch (PromiseTimeoutException $e) {
                                $this->close();
                                throw new TimeoutException(
                                    'Inactivity timeout exceeded, more than ' . $timeout . ' ms elapsed from last data received',
                                    0,
                                    $e
                                );
                            }

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
                        } else {
                            $this->close();
                        }

                        $this->busy = false;

                        foreach ($request->getEventListeners() as $eventListener) {
                            yield $eventListener->completeReceivingResponse($request, $stream);
                        }

                        $bodyEmitter->complete();
                        $trailersDeferred->resolve($trailers);
                    } catch (\Throwable $e) {
                        $this->close();

                        try {
                            foreach ($request->getEventListeners() as $eventListener) {
                                yield $eventListener->abort($request, $e);
                            }
                        } finally {
                            $bodyEmitter->fail($e);
                            $trailersDeferred->fail($e);
                        }
                    } finally {
                        $bodyCancellationToken->unsubscribe($id);
                    }
                });

                return $response;
            }

            $originalCancellation->throwIfRequested();

            throw new SocketException(\sprintf(
                "Receiving the response headers for '%s' failed, because the socket to '%s' @ '%s' closed early with %d bytes received within %d milliseconds",
                (string) $request->getUri()->withUserInfo(''),
                (string) $request->getUri()->withUserInfo('')->getAuthority(),
                $this->socket === null ? '???' : (string) $this->socket->getRemoteAddress(),
                \strlen($parser->getBuffer()),
                getCurrentTime() - $start
            ));
        } catch (HttpException $e) {
            $this->close();
            throw $e;
        } catch (PromiseTimeoutException $e) {
            $this->close();
            throw new TimeoutException(
                'Inactivity timeout exceeded, more than ' . $timeout . ' ms elapsed from last data received',
                0,
                $e
            );
        } catch (\Throwable $e) {
            $this->close();
            throw new SocketException('Receiving the response headers failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function handleUpgradeResponse(Request $request, Response $response, string $buffer): Response
    {
        if ($this->socket === null) {
            throw new SocketException('Socket closed while upgrading');
        }

        $socket = new UpgradedSocket($this->socket, $buffer);
        $this->free(); // Mark this connection as unusable without closing socket.

        if (($onUpgrade = $request->getUpgradeHandler()) === null) {
            $socket->close();

            throw new HttpException('CONNECT or upgrade request made without upgrade handler callback');
        }

        asyncCall(static function () use ($onUpgrade, $socket, $request, $response): \Generator {
            try {
                yield call($onUpgrade, $socket, $request, $response);
            } catch (\Throwable $exception) {
                $socket->close();

                throw new HttpException('Upgrade handler threw an exception', 0, $exception);
            }
        });

        return $response;
    }

    /**
     * @return int Approximate number of milliseconds remaining until the connection is closed.
     */
    private function getRemainingTime(): int
    {
        $timestamp = $this->lastUsedAt + ($this->explicitTimeout ? $this->priorTimeout * 1000 : $this->timeoutGracePeriod);
        return \max(0, $timestamp - getCurrentTime());
    }

    private function withCancellation(Promise $promise, CancellationToken $cancellationToken): Promise
    {
        $deferred = new Deferred;
        $newPromise = $deferred->promise();

        $promise->onResolve(static function ($error, $value) use (&$deferred): void {
            if ($deferred) {
                $temp = $deferred;
                $deferred = null;

                if ($error) {
                    $temp->fail($error);
                } else {
                    $temp->resolve($value);
                }
            }
        });

        $cancellationSubscription = $cancellationToken->subscribe(static function ($e) use (&$deferred): void {
            if ($deferred) {
                $temp = $deferred;
                $deferred = null;
                $temp->fail($e);
            }
        });

        $newPromise->onResolve(static function () use ($cancellationToken, $cancellationSubscription): void {
            $cancellationToken->unsubscribe($cancellationSubscription);
        });

        return $newPromise;
    }

    private function determineKeepAliveTimeout(Response $response): int
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('connection') ?? '';
        $responseConnHeader = $response->getHeader('connection') ?? '';

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

            if ($this->socket === null) {
                throw new UnprocessedRequestException(new SocketException('Socket closed before request started'));
            }

            yield $this->socket->write($rawHeaders);

            if ($request->getMethod() === 'CONNECT') {
                return;
            }

            $body = $request->getBody()->createBodyStream();
            $chunking = $request->getHeader("transfer-encoding") === "chunked";
            $remainingBytes = $request->getHeader("content-length");

            if ($remainingBytes !== null) {
                $remainingBytes = (int) $remainingBytes;
            }

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
        $requestUri = normalizeRequestPathWithQuery($request);

        $method = $request->getMethod();

        if ($method === 'CONNECT') {
            $defaultPort = $uri->getScheme() === 'https' ? 443 : 80;
            $requestUri = $uri->getHost() . ':' . ($uri->getPort() ?? $defaultPort);
        }

        $header = $method . ' ' . $requestUri . ' HTTP/' . $protocolVersion . "\r\n";

        try {
            $header .= Rfc7230::formatRawHeaders($request->getRawHeaders());
        } catch (InvalidHeaderException $e) {
            throw new HttpException($e->getMessage());
        }

        return $header . "\r\n";
    }
}
