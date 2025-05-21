<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ResourceStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Http;
use Amp\Http\Client\Connection\Internal\Http1Parser;
use Amp\Http\Client\Connection\Internal\RequestNormalizer;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Http\Http1\Rfc7230;
use Amp\Http\InvalidHeaderException;
use Amp\Pipeline\Queue;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\TimeoutCancellation;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\Http\Client\events;
use function Amp\Http\Client\Internal\normalizeRequestPathWithQuery;
use function Amp\now;

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

    private ?Socket $socket;

    private bool $busy = false;

    /** @var int Number of stream requests made on this connection. */
    private int $streamCounter = 0;

    /** @var int Number of requests made on this connection. */
    private int $requestCounter = 0;

    /** @var string|null Keep alive timeout watcher ID. */
    private ?string $timeoutWatcher = null;

    /** @var int Keep-Alive timeout from last response. */
    private int $priorTimeout = self::MAX_KEEP_ALIVE_TIMEOUT;

    /** @var list<\Closure():void>|null */
    private ?array $onClose = [];

    private float $lastUsedAt;

    private bool $explicitTimeout = false;

    private SocketAddress $localAddress;

    private SocketAddress $remoteAddress;

    private ?TlsInfo $tlsInfo;

    private ?Future $idleRead = null;

    public function __construct(
        Socket $socket,
        private readonly float $connectDuration,
        private readonly ?float $tlsHandshakeDuration,
        private readonly float $timeoutGracePeriod = 2,
    ) {
        $this->socket = $socket;
        $this->localAddress = $socket->getLocalAddress();
        $this->remoteAddress = $socket->getRemoteAddress();
        $this->tlsInfo = $socket->getTlsInfo();
        $this->lastUsedAt = now();
        $this->watchIdleConnection();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function onClose(\Closure $onClose): void
    {
        if (!$this->socket || $this->socket->isClosed()) {
            EventLoop::queue($onClose);
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): void
    {
        $this->socket?->close();
        $this->free();
    }

    public function isClosed(): bool
    {
        return $this->socket?->isClosed() ?? true;
    }

    public function isIdle(): bool
    {
        return !$this->busy;
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

    public function getTlsHandshakeDuration(): ?float
    {
        return $this->tlsHandshakeDuration;
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function getStream(Request $request): ?Stream
    {
        if ($this->busy || ($this->requestCounter && !$this->hasStreamFor($request))) {
            return null;
        }

        $this->busy = true;

        events()->connectionAcquired($request, $this, ++$this->streamCounter);

        return HttpStream::fromConnection($this, $this->request(...), $this->release(...));
    }

    private function free(): void
    {
        $this->socket = null;
        $this->idleRead = null;

        $this->lastUsedAt = 0;

        if ($this->timeoutWatcher !== null) {
            EventLoop::cancel($this->timeoutWatcher);
        }

        if ($this->onClose !== null) {
            $onClose = $this->onClose;
            $this->onClose = null;

            foreach ($onClose as $callback) {
                EventLoop::queue($callback);
            }
        }
    }

    private function hasStreamFor(Request $request): bool
    {
        return !$this->busy
            && $this->socket
            && !$this->socket->isClosed()
            && ($this->getRemainingTime() > 0 || $request->isIdempotent());
    }

    private function readChunk(float $timeout): ?string
    {
        $cancellation = $timeout > 0 ? new TimeoutCancellation($timeout) : null;

        if ($this->idleRead) {
            $future = $this->idleRead;
            $this->idleRead = null;
            return $future->await($cancellation);
        }

        return $this->socket?->read($cancellation);
    }

    private function request(Request $request, Cancellation $cancellation, Stream $stream): Response
    {
        ++$this->requestCounter;

        if ($this->socket instanceof ResourceStream) {
            $this->socket->reference();
        }

        if ($this->timeoutWatcher !== null) {
            EventLoop::cancel($this->timeoutWatcher);
            $this->timeoutWatcher = null;
        }

        RequestNormalizer::normalizeRequest($request);

        $protocolVersion = $this->determineProtocolVersion($request);

        $request->setProtocolVersions([$protocolVersion]);

        if ($request->getTransferTimeout() > 0) {
            $timeoutCancellation = new TimeoutCancellation($request->getTransferTimeout());
            $combinedCancellation = new CompositeCancellation($cancellation, $timeoutCancellation);
        } else {
            $combinedCancellation = $cancellation;
        }

        $cancellationId = $combinedCancellation->subscribe($this->close(...));

        $responseDeferred = new DeferredFuture();

        EventLoop::queue(function () use (
            $responseDeferred,
            $request,
            $stream,
            $protocolVersion,
            $combinedCancellation,
        ): void {
            try {
                $this->writeRequest($request, $stream, $protocolVersion, $combinedCancellation);
            } catch (\Throwable $exception) {
                if (!$responseDeferred->isComplete()) {
                    $responseDeferred->error($exception);
                }
            }
        });

        EventLoop::queue(function () use (
            $responseDeferred,
            $request,
            $stream,
            $cancellation,
            $combinedCancellation,
            $cancellationId,
        ): void {
            try {
                $response = $this->readResponse($request, $cancellation, $combinedCancellation, $stream);
                if (!$responseDeferred->isComplete()) {
                    $responseDeferred->complete($response);
                }
            } catch (\Throwable $exception) {
                $this->socket?->close();

                if (!$responseDeferred->isComplete()) {
                    $responseDeferred->error($exception);
                }
            } finally {
                $combinedCancellation->unsubscribe($cancellationId);
            }
        });

        return $responseDeferred->getFuture()->await($cancellation);
    }

    private function release(): void
    {
        $this->busy = false;
    }

    /**
     * @throws CancelledException
     * @throws HttpException
     * @throws ParseException
     * @throws SocketException
     */
    private function readResponse(
        Request $request,
        Cancellation $originalCancellation,
        Cancellation $readingCancellation,
        Stream $stream,
    ): Response {
        $bodyEmitter = new Queue();
        $trailersDeferred = new DeferredFuture;
        $trailersDeferred->getFuture()->ignore();

        $trailers = [];
        $trailersCallback = static function (array $headers) use (&$trailers): void {
            $trailers = $headers;
        };

        $bodyDeferredCancellation = new DeferredCancellation;
        $bodyCancellation = new CompositeCancellation(
            $readingCancellation,
            $bodyDeferredCancellation->getCancellation(),
        );

        $parser = new Http1Parser(
            $request,
            $stream,
            $bodyEmitter->pushAsync(...),
            $bodyCancellation,
            $trailersCallback,
        );

        $start = now();
        $inactivityTimeout = $request->getInactivityTimeout();

        try {
            if ($this->socket === null) {
                throw new SocketException('Socket closed prior to response completion');
            }

            while (null !== $chunk = $this->readChunk($inactivityTimeout)) {
                parseChunk:
                $response = $parser->parse($chunk);
                if ($response === null) {
                    if ($this->socket === null) {
                        throw new SocketException('Socket closed prior to response completion');
                    }

                    continue;
                }

                $this->lastUsedAt = now();

                $status = $response->getStatus();

                if ($status === Http\HttpStatus::SWITCHING_PROTOCOLS) {
                    $connection = Http\parseHeaderTokens($response, 'connection');
                    if ($connection === null || !\in_array('upgrade', $connection, true)) {
                        throw new HttpException('Switching protocols response missing "Connection: upgrade" header');
                    }

                    if (!$response->hasHeader('upgrade')) {
                        throw new HttpException('Switching protocols response missing "Upgrade" header');
                    }

                    $trailersDeferred->complete($trailers);

                    return $this->handleUpgradeResponse($request, $response, $parser->getBuffer());
                }

                if ($status < 200) { // 1XX responses (excluding 101, handled above)
                    $onInformationalResponse = $request->getInformationalResponseHandler();

                    if ($onInformationalResponse !== null) {
                        $onInformationalResponse($response);
                    }

                    $chunk = $parser->getBuffer();

                    $parser = new Http1Parser(
                        $request,
                        $stream,
                        $bodyEmitter->pushAsync(...),
                        $bodyCancellation,
                        $trailersCallback,
                    );

                    goto parseChunk;
                }

                if ($status < 300 && $request->getMethod() === 'CONNECT') {
                    $trailersDeferred->complete($trailers);

                    return $this->handleUpgradeResponse($request, $response, $parser->getBuffer());
                }

                $response->setTrailers($trailersDeferred->getFuture());
                $response->setBody(new ResponseBodyStream(
                    new ReadableIterableStream($bodyEmitter->pipe()),
                    $bodyDeferredCancellation,
                ));

                [$requestTimeout, $explicitTimeout, $priorTimeout] = $this->determineKeepAliveTimeout($response);

                // Read body async
                EventLoop::queue(function () use (
                    $parser,
                    $request,
                    $requestTimeout,
                    $explicitTimeout,
                    $priorTimeout,
                    $inactivityTimeout,
                    $bodyEmitter,
                    $trailersDeferred,
                    $originalCancellation,
                    $readingCancellation,
                    $bodyCancellation,
                    $stream,
                    &$trailers,
                ) {
                    $closeId = $bodyCancellation->subscribe($this->close(...));

                    try {
                        // Required, otherwise responses without body hang
                        if (!$parser->isComplete()) {
                            // Directly parse again in case we already have the full body but aborted parsing
                            // to complete future with headers.
                            $chunk = null;

                            try {
                                do {
                                    $parser->parse($chunk);
                                    if ($parser->isComplete()) {
                                        break;
                                    }

                                    if ($this->socket === null) {
                                        throw new SocketException('Socket closed prior to response completion');
                                    }
                                } while (null !== $chunk = $this->readChunk($inactivityTimeout));
                            } catch (CancelledException $e) {
                                $this->close();
                                $originalCancellation->throwIfRequested();

                                throw new TimeoutException(
                                    'Inactivity timeout exceeded, more than ' . $inactivityTimeout
                                        . ' seconds elapsed from last data received',
                                    previous: $e,
                                );
                            }

                            $originalCancellation->throwIfRequested();

                            if ($readingCancellation->isRequested()) {
                                throw new TimeoutException('Allowed transfer timeout exceeded, took longer than ' . $request->getTransferTimeout() . ' s');
                            }

                            $bodyCancellation->throwIfRequested();

                            // Ignore check if neither content-length nor chunked encoding are given.
                            if (!$parser->isComplete() && $parser->getState() !== Http1Parser::BODY_IDENTITY_EOF) {
                                throw new SocketException('Socket disconnected prior to response completion');
                            }
                        }

                        $this->explicitTimeout = $explicitTimeout ?: $this->explicitTimeout;
                        $this->priorTimeout = $priorTimeout ?? $this->priorTimeout;

                        if ($requestTimeout > 0 && $parser->getState() !== Http1Parser::BODY_IDENTITY_EOF) {
                            $this->timeoutWatcher = EventLoop::delay($requestTimeout, $this->close(...));
                            EventLoop::unreference($this->timeoutWatcher);
                            $this->watchIdleConnection();
                        } else {
                            $this->close();
                        }

                        $this->busy = false;

                        $bodyEmitter->complete();
                        $trailersDeferred->complete($trailers);
                    } catch (\Throwable $e) {
                        $this->close();

                        $e = $this->wrapException($e);

                        $trailersDeferred->error($e);

                        if (!$e instanceof CancelledException) {
                            $e = new StreamException(
                                'HTTP response did not complete: ' . $e->getMessage(),
                                previous: $e,
                            );
                        }

                        $bodyEmitter->error($e);
                    } finally {
                        $bodyCancellation->unsubscribe($closeId);
                    }
                });

                return $response;
            }

            $originalCancellation->throwIfRequested();
            $readingCancellation->throwIfRequested();

            throw new SocketException(\sprintf(
                "Receiving the response headers for '%s' failed, because the socket to '%s' @ '%s' closed early with %d bytes received within %0.3f seconds",
                (string) $request->getUri()->withUserInfo(''),
                $request->getUri()->withUserInfo('')->getAuthority(),
                $this->socket?->getRemoteAddress()?->toString() ?? '???',
                \strlen($parser->getBuffer()),
                now() - $start
            ));
        } catch (HttpException $e) {
            $this->close();
            throw $e;
        } catch (CancelledException $e) {
            $this->close();

            // Throw original cancellation if it was requested.
            $originalCancellation->throwIfRequested();

            if ($readingCancellation->isRequested()) {
                throw new TimeoutException('Allowed transfer timeout exceeded, took longer than ' . $request->getTransferTimeout() . ' s', 0, $e);
            }

            throw new TimeoutException('Inactivity timeout exceeded, more than ' . $inactivityTimeout . ' seconds elapsed from last data received', 0, $e);
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

        try {
            $onUpgrade($socket, $request, $response);
        } catch (\Throwable $exception) {
            $socket->close();

            throw new HttpException('Upgrade handler threw an exception', 0, $exception);
        }

        return $response;
    }

    /**
     * @return float Approximate number of milliseconds remaining until the connection is closed.
     */
    private function getRemainingTime(): float
    {
        $timestamp = $this->lastUsedAt + ($this->explicitTimeout ? $this->priorTimeout * 1000 : $this->timeoutGracePeriod);
        return \max(0, $timestamp - now());
    }

    /**
     * @return array{int, bool, int|null}
     */
    private function determineKeepAliveTimeout(Response $response): array
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('connection') ?? '';
        $responseConnHeader = $response->getHeader('connection') ?? '';

        if (!\strcasecmp($requestConnHeader, 'close')) {
            return [0, false, null];
        }

        if ($response->getProtocolVersion() === '1.0') {
            return [0, false, null];
        }

        if (!\strcasecmp($responseConnHeader, 'close')) {
            return [0, false, null];
        }

        $params = Http\parseMultipleHeaderFields($response, 'keep-alive')[0] ?? null;

        $timeout = (int) ($params['timeout'] ?? $this->priorTimeout);
        $timeout = \min(\max(0, $timeout), self::MAX_KEEP_ALIVE_TIMEOUT);

        return [$timeout, isset($params['timeout']), $timeout];
    }

    /**
     * @return '1.0'|'1.1'
     */
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
        Stream $stream,
        string $protocolVersion,
        Cancellation $cancellation,
    ): void {
        try {
            $socket = $this->socket;
            if ($socket === null) {
                throw new SocketException('Socket closed before request started');
            }

            events()->requestHeaderStart($request, $stream);
            $rawHeaders = $this->generateRawHeader($request, $protocolVersion);
            $socket->write($rawHeaders);
            events()->requestHeaderEnd($request, $stream);

            if ($request->getMethod() === 'CONNECT') {
                events()->requestBodyStart($request, $stream);
                events()->requestBodyEnd($request, $stream);
                return;
            }

            $chunking = $request->getHeader("transfer-encoding") === "chunked";
            $remainingBytes = $request->getHeader("content-length");

            if ($remainingBytes !== null) {
                $remainingBytes = (int) $remainingBytes;
            }

            if ($chunking && $protocolVersion === "1.0") {
                throw new InvalidRequestException($request, "Can't send chunked bodies over HTTP/1.0");
            }

            events()->requestBodyStart($request, $stream);

            // We always buffer the last chunk to make sure we don't write $contentLength bytes if the body is too long.
            $buffer = "";
            $body = $request->getBody()->getContent();
            while (null !== $chunk = $body->read($cancellation)) {
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

                $socket->write($buffer);
                events()->requestBodyProgress($request, $stream);
                $buffer = $chunk;
            }

            $cancellation->throwIfRequested();

            // Flush last buffered chunk.
            $socket->write($chunking ? $buffer . "0\r\n\r\n" : $buffer);
            events()->requestBodyProgress($request, $stream);

            if (!$chunking && $remainingBytes !== null && $remainingBytes > 0) {
                throw new InvalidRequestException(
                    $request,
                    "Body contained fewer bytes than specified in Content-Length, aborting request"
                );
            }

            events()->requestBodyEnd($request, $stream);
        } catch (StreamException $exception) {
            throw new SocketException('Socket disconnected prior to response completion', 0, $exception);
        }
    }

    /**
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
            $header .= Rfc7230::formatHeaderPairs($request->getHeaderPairs());
        } catch (InvalidHeaderException $e) {
            throw new HttpException($e->getMessage());
        }

        return $header . "\r\n";
    }

    private function watchIdleConnection(): void
    {
        if ($this->socket instanceof ResourceStream) {
            $this->socket->unreference();
        }

        $this->idleRead = async(function (): ?string {
            $chunk = null;
            try {
                $chunk = $this->socket?->read();
            } catch (\Throwable) {
                // Close connection below.
            }

            if ($chunk === null) {
                $this->close();
            }

            return $chunk;
        });
    }

    public function getConnectDuration(): float
    {
        return $this->connectDuration;
    }

    private function wrapException(\Throwable $exception): \Throwable
    {
        if ($exception instanceof HttpException || $exception instanceof CancelledException) {
            return $exception;
        }

        return new HttpException($exception->getMessage(), previous: $exception);
    }
}
