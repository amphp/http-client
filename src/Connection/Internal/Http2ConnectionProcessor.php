<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection\Internal;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\ResourceStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\Client\Connection\HttpStream;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\EventInvoker;
use Amp\Http\Client\Internal\Phase;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\Http2\Http2ConnectionException;
use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2Processor;
use Amp\Http\Http2\Http2StreamException;
use Amp\Http\HttpStatus;
use Amp\Http\InvalidHeaderException;
use Amp\Pipeline\Queue;
use Amp\Socket\InternetAddress;
use Amp\Socket\Socket;
use League\Uri;
use Revolt\EventLoop;
use function Amp\async;
use function Amp\Http\Client\events;
use function Amp\Http\Client\Internal\normalizeRequestPathWithQuery;

/** @internal */
final class Http2ConnectionProcessor implements Http2Processor
{
    private const PREFACE = "PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n";
    private const DEFAULT_MAX_FRAME_SIZE = 1 << 14;
    private const DEFAULT_WINDOW_SIZE = (1 << 16) - 1;
    private const MINIMUM_WINDOW = 512 * 1024;
    private const WINDOW_INCREMENT = 1024 * 1024;
    // Seconds to wait for pong (PING with ACK) frame before closing the connection.
    private const PONG_TIMEOUT = 5;

    /** @var string 64-bit for ping. */
    private string $counter = "aaaaaaaa";

    /** @var Http2Stream[] */
    private array $streams = [];

    private int $serverWindow = self::DEFAULT_WINDOW_SIZE;

    private int $clientWindow = self::DEFAULT_WINDOW_SIZE;

    private int $initialWindowSize = self::DEFAULT_WINDOW_SIZE;

    /** @var int<1, max> */
    private int $frameSizeLimit = self::DEFAULT_MAX_FRAME_SIZE;

    /** @var int Previous stream ID. */
    private int $streamId = -1;

    /** @var int Maximum number of streams that may be opened. Initially unlimited. */
    private int $concurrentStreamLimit = 2147483647;

    /** @var int Currently open or reserved streams. Initially unlimited. */
    private int $remainingStreams = 2147483647;

    private int $reservedStreams = 0;

    private readonly HPack $hpack;

    /** @var DeferredFuture<int>|null */
    private ?DeferredFuture $settings = null;

    private bool $initializeStarted = false;

    private bool $initialized = false;

    private ?string $pongWatcher = null;

    /** @var DeferredFuture<bool>|null */
    private ?DeferredFuture $pongDeferred = null;

    /** @var list<\Closure():void>|null */
    private ?array $onClose = [];

    private bool $hasTimeout = false;

    private bool $hasWriteError = false;

    private ?int $shutdown = null;

    /** @var Queue<string> */
    private readonly Queue $frameQueue;

    public function __construct(
        private readonly Socket $socket,
    ) {
        $this->hpack = new HPack();
        $this->frameQueue = new Queue();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Returns once the connection has been initialized. A stream cannot be obtained from the
     * connection until this method returned.
     */
    public function initialize(Cancellation $cancellation): void
    {
        if ($this->initializeStarted) {
            throw new \Error('Connection may only be initialized once');
        }

        $this->initializeStarted = true;

        if ($this->socket->isClosed()) {
            throw new SocketException('The socket closed before the connection could be initialized');
        }

        $this->settings = new DeferredFuture;
        $future = $this->settings->getFuture();

        EventLoop::queue($this->runReadFiber(...));
        EventLoop::queue($this->runWriteFiber(...));

        try {
            $future->await($cancellation);
        } catch (CancelledException $cancelledException) {
            $exception = new SocketException('Connecting cancelled', 0, $cancelledException);
            $this->shutdown($exception);
            throw $exception;
        }
    }

    /**
     * @param \Closure():void $onClose
     */
    public function onClose(\Closure $onClose): void
    {
        if ($this->onClose === null) {
            EventLoop::queue($onClose);
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): void
    {
        if ($this->shutdown !== null) {
            return;
        }

        $exception = new SocketException(\sprintf(
            "Socket from '%s' to '%s' closed",
            $this->socket->getLocalAddress()->toString(),
            $this->socket->getRemoteAddress()->toString(),
        ));

        $this->shutdown($exception);
    }

    public function handlePong(string $data): void
    {
        $this->cancelPongWatcher(true);
        $this->hasTimeout = false;
    }

    public function handlePing(string $data): void
    {
        $this->writeFrame(Http2Parser::PING, Http2Parser::ACK, 0, $data)->ignore();
    }

    public function handleShutdown(int $lastId, int $error, string $message): void
    {
        $message = \sprintf(
            "Received GOAWAY frame on '%s' from '%s' with error code %d and message '%s'",
            (string) $this->socket->getLocalAddress(),
            (string) $this->socket->getRemoteAddress(),
            $error,
            $message,
        );

        $this->shutdown(new SocketException($message, $error), $lastId);
    }

    public function handleStreamWindowIncrement(int $streamId, int $windowSize): void
    {
        $stream = $this->streams[$streamId] ?? null;
        if (!$stream) {
            return;
        }

        if ($stream->clientWindow + $windowSize > 2147483647) {
            $this->handleStreamException(new Http2StreamException(
                "Current window size plus new window exceeds maximum size",
                $streamId,
                Http2Parser::FLOW_CONTROL_ERROR
            ));

            return;
        }

        $stream->clientWindow += $windowSize;

        $this->writeBufferedData($stream)->ignore();
    }

    public function handleConnectionWindowIncrement(int $windowSize): void
    {
        if ($this->clientWindow + $windowSize > 2147483647) {
            $this->handleConnectionException(new Http2ConnectionException(
                "Current window size plus new window exceeds maximum size",
                Http2Parser::FLOW_CONTROL_ERROR
            ));

            return;
        }

        $this->clientWindow += $windowSize;

        foreach ($this->streams as $stream) {
            if ($this->clientWindow <= 0) {
                return;
            }

            if ($stream->requestBodyBuffer === '' || $stream->clientWindow <= 0) {
                continue;
            }

            $this->writeBufferedData($stream)->ignore();
        }
    }

    public function handleHeaders(int $streamId, array $pseudo, array $headers, bool $streamEnded): void
    {
        foreach ($pseudo as $name => $value) {
            if (!isset(Http2Parser::KNOWN_RESPONSE_PSEUDO_HEADERS[$name])) {
                $this->handleStreamException(new Http2StreamException(
                    "Invalid pseudo header",
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR
                ));

                return;
            }
        }

        $stream = $this->streams[$streamId] ?? null;
        if (!$stream) {
            return;
        }

        $stream->enableInactivityWatcher();

        $this->hasTimeout = false;

        if (!$stream->responsePending) {
            if ($stream->expectedLength && $stream->received !== $stream->expectedLength) {
                $diff = $stream->expectedLength - $stream->received;
                $this->handleStreamException(new Http2StreamException(
                    "Content length mismatch: " . \abs($diff) . ' bytes ' . ($diff > 0 ? ' missing' : 'too much'),
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR
                ));

                return;
            }

            if (!empty($pseudo)) {
                $this->handleStreamException(new Http2StreamException(
                    "Trailers must not contain pseudo headers",
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR
                ));

                return;
            }

            try {
                // Constructor checks for any disallowed fields
                $parsedTrailers = new Trailers($headers);
            } catch (InvalidHeaderException $exception) {
                $this->handleStreamException(new Http2StreamException(
                    "Disallowed field names in trailer",
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR,
                    $exception
                ));

                return;
            }

            $trailers = $stream->trailers;
            $stream->trailers = null;

            \assert($trailers !== null);

            $trailers->complete($parsedTrailers);

            return;
        }

        events()->responseHeaderStart($stream->request, $stream->stream);

        $status = $pseudo[":status"] ?? null;

        if ($status === null) {
            $this->handleConnectionException(new Http2ConnectionException(
                "No status pseudo header in response",
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        if (!\preg_match("/^[1-5]\d\d$/", $status)) {
            $this->handleStreamException(new Http2StreamException(
                "Invalid response status code: " . $pseudo[':status'],
                $streamId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        if ($stream->response !== null) {
            $this->handleStreamException(new Http2StreamException(
                "Stream headers already received",
                $streamId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $status = (int) $status;

        if ($status === HttpStatus::SWITCHING_PROTOCOLS) {
            $this->handleConnectionException(new Http2ConnectionException(
                "Switching Protocols (101) is not part of HTTP/2",
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        if ($status < 200) {
            $response = new Response(
                '2',
                $status,
                HttpStatus::getReason($status),
                $headers,
                new ReadableBuffer(),
                $stream->request,
            );

            events()->responseHeaderEnd($stream->request, $stream->stream, $response);

            $onInformationalResponse = $stream->request->getInformationalResponseHandler();
            $preResponseResolution = $stream->preResponseResolution;
            if ($onInformationalResponse !== null) {
                $stream->preResponseResolution = async(function () use (
                    $preResponseResolution,
                    $onInformationalResponse,
                    $streamId,
                    $response
                ): void {
                    $preResponseResolution?->await();

                    try {
                        $onInformationalResponse($response);
                    } catch (\Throwable) {
                        $this->handleStreamException(new Http2StreamException(
                            'Informational response handler threw an exception',
                            $streamId,
                            Http2Parser::CANCEL
                        ));
                    }
                });
            }

            return;
        }

        \assert($stream->body !== null && $stream->trailers !== null);
        $trailers = $stream->trailers->getFuture();
        $iterator = $stream->body->iterate();

        $deferredCancellation = new DeferredCancellation;
        $cancellation = $deferredCancellation->getCancellation();
        $body = new ResponseBodyStream(new ReadableIterableStream($iterator), $deferredCancellation);

        $cancellationId = $cancellation->subscribe(
            fn (CancelledException $exception) => $this->releaseStream($streamId, $exception, false),
        );

        $trailers
            ->finally(static fn () => $cancellation->unsubscribe($cancellationId))
            ->ignore();

        $stream->response = $response = new Response(
            '2',
            $status,
            HttpStatus::getReason($status),
            $headers,
            $body,
            $stream->request,
            $trailers,
        );

        events()->responseHeaderEnd($stream->request, $stream->stream, $response);

        \assert($stream->preResponseResolution === null);

        $stream->preResponseResolution = Future::complete();

        \assert($stream->pendingResponse !== null);

        $stream->responsePending = false;
        EventLoop::queue(static function () use ($response, $stream): void {
            try {
                $stream->requestHeaderCompletion->getFuture()->await();
                $stream->preResponseResolution?->await();
                $stream->pendingResponse?->complete($response);
            } catch (\Throwable $e) {
                $stream->pendingResponse?->error($e);
            }

            $stream->preResponseResolution = null;
            $stream->pendingResponse = null;
        });

        $this->increaseConnectionWindow();
        $this->increaseStreamWindow($stream);

        if (isset($headers["content-length"])) {
            if (\count($headers['content-length']) !== 1) {
                $this->handleStreamException(new Http2StreamException(
                    "Multiple content-length header values",
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR
                ));

                return;
            }

            $contentLength = $headers["content-length"][0];
            if (!\preg_match('/^(0|[1-9][0-9]*)$/', $contentLength)) {
                $this->handleStreamException(new Http2StreamException(
                    "Invalid content-length header value",
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR
                ));

                return;
            }

            if ($stream->request->getMethod() !== 'HEAD') {
                $stream->expectedLength = (int) $contentLength;
            }
        }
    }

    public function handlePushPromise(int $streamId, int $pushId, array $pseudo, array $headers): void
    {
        if ($pushId % 2 === 1) {
            $this->handleConnectionException(new Http2ConnectionException(
                "Invalid server initiated stream",
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        foreach ($pseudo as $name => $value) {
            if (!isset(Http2Parser::KNOWN_REQUEST_PSEUDO_HEADERS[$name])) {
                $this->handleStreamException(new Http2StreamException(
                    "Invalid pseudo header",
                    $pushId,
                    Http2Parser::PROTOCOL_ERROR
                ));

                return;
            }
        }

        if (!isset($pseudo[":method"], $pseudo[":path"], $pseudo[":scheme"], $pseudo[":authority"])
            || isset($headers["connection"])
            || $pseudo[":path"] === ''
            || (isset($headers["te"]) && \implode($headers["te"]) !== "trailers")
        ) {
            $this->handleStreamException(new Http2StreamException(
                "Invalid header values",
                $pushId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $method = $pseudo[":method"];
        $target = $pseudo[":path"];
        $scheme = $pseudo[":scheme"];
        $host = $pseudo[":authority"];
        $query = null;

        if ($method !== 'GET' && $method !== 'HEAD') {
            $this->handleStreamException(new Http2StreamException(
                "Pushed request method must be a safe method",
                $pushId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        if (!\preg_match("#^([A-Z\d.\-]+|\[[\d:]+])(?::([1-9]\d*))?$#i", $host, $matches)) {
            $this->handleStreamException(new Http2StreamException(
                "Invalid pushed authority (host) name",
                $pushId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $address = $this->socket->getRemoteAddress();

        $host = $matches[1];
        $port = isset($matches[2]) ? (int) $matches[2] : match (true) {
            $address instanceof InternetAddress => $address->getPort(),
            default => null,
        };

        if (!isset($this->streams[$streamId])) {
            $this->handleStreamException(new Http2StreamException(
                "Parent stream {$streamId} is no longer open",
                $pushId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $parentStream = $this->streams[$streamId];
        $parentStream->enableInactivityWatcher();

        if (\strcasecmp($host, $parentStream->request->getUri()->getHost()) !== 0) {
            $this->handleStreamException(new Http2StreamException(
                "Authority does not match original request authority",
                $pushId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        if ($position = \strpos($target, "#")) {
            $target = \substr($target, 0, $position);
        }

        if ($position = \strpos($target, "?")) {
            $query = \substr($target, $position + 1);
            $target = \substr($target, 0, $position);
        }

        try {
            $uri = Uri\Http::fromComponents([
                "scheme" => $scheme,
                "host" => $host,
                "port" => $port,
                "path" => $target,
                "query" => $query,
            ]);
        } catch (\Exception $exception) {
            $this->handleConnectionException(new Http2ConnectionException(
                "Invalid push URI: " . $exception->getMessage(),
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $request = new Request($uri, $method);
        $request->setHeaders($headers);
        $request->setProtocolVersions(['2']);
        $request->setPushHandler($parentStream->request->getPushHandler());
        $request->setHeaderSizeLimit($parentStream->request->getHeaderSizeLimit());
        $request->setBodySizeLimit($parentStream->request->getBodySizeLimit());
        $request->setInactivityTimeout($parentStream->request->getInactivityTimeout());
        $request->setTransferTimeout($parentStream->request->getTransferTimeout());

        foreach ($parentStream->request->getEventListeners() as $eventListener) {
            $request->addEventListener($eventListener);
        }

        $deferredCancellation = new DeferredCancellation();

        $cancellation = new CompositeCancellation(
            $parentStream->cancellation,
            $deferredCancellation->getCancellation(),
        );

        $stream = new Http2Stream(
            $pushId,
            $request,
            HttpStream::fromStream(
                $parentStream->stream,
                static function () {
                    throw new \Error('Calling Stream::request() on a pushed request is forbidden');
                },
                static function () {
                    // nothing to do
                }
            ),
            $cancellation,
            $this->createStreamTransferWatcher($pushId, $request->getTransferTimeout()),
            $this->createStreamInactivityWatcher($pushId, $request->getInactivityTimeout()),
            self::DEFAULT_WINDOW_SIZE,
            0,
        );

        events()->requestStart($request);
        events()->push($request);
        events()->requestHeaderStart($request, $stream->stream);
        events()->requestHeaderEnd($request, $stream->stream);
        events()->requestBodyStart($request, $stream->stream);
        events()->requestBodyEnd($request, $stream->stream);

        \assert($stream->pendingResponse !== null);

        $stream->pendingResponse->getFuture()
            ->map(function (Response $response) use ($request) {
                $trailers = $response->getTrailers();
                $trailers->map(fn () => events()->requestEnd($request, $response))->ignore();
                $trailers->catch(fn (\Throwable $exception) => events()->requestFailed($request, $exception))->ignore();
            })
            ->catch(fn (\Throwable $exception) => events()->requestFailed($request, $exception))
            ->ignore();

        $stream->dependency = $streamId;

        $this->streams[$pushId] = $stream;

        $stream->requestBodyCompletion->complete();

        if ($parentStream->request->getPushHandler() === null) {
            $stream->pendingResponse?->getFuture()->ignore();
            $this->handleStreamException(new Http2StreamException(
                "Push promise refused",
                $pushId,
                Http2Parser::CANCEL
            ));

            return;
        }

        EventLoop::queue(function () use ($pushId, $deferredCancellation, $stream, $cancellation): void {
            $cancellationId = $cancellation->subscribe(
                fn (CancelledException $exception) => $this->releaseStream($pushId, $exception, false),
            );

            $onPush = $stream->request->getPushHandler();

            try {
                \assert($onPush !== null);
                \assert($stream->pendingResponse !== null);

                $future = $stream->pendingResponse->getFuture()
                    ->finally(static fn () => $cancellation->unsubscribe($cancellationId));

                $onPush($stream->request, $future);
            } catch (HttpException|StreamException|CancelledException $exception) {
                $deferredCancellation->cancel($exception);
            } catch (\Throwable $exception) {
                $deferredCancellation->cancel($exception);
                throw $exception;
            }
        });
    }

    public function handlePriority(int $streamId, int $parentId, int $weight): void
    {
        $stream = $this->streams[$streamId] ?? null;
        if (!$stream) {
            return;
        }

        $stream->dependency = $parentId;
        $stream->weight = $weight;
    }

    public function handleStreamReset(int $streamId, int $errorCode): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $message = match ($errorCode) {
            Http2Parser::GRACEFUL_SHUTDOWN => 'Graceful shutdown',
            Http2Parser::PROTOCOL_ERROR => 'Protocol error',
            Http2Parser::INTERNAL_ERROR => 'Internal error',
            Http2Parser::FLOW_CONTROL_ERROR => 'Flow control error',
            Http2Parser::SETTINGS_TIMEOUT => 'Settings timeout',
            Http2Parser::STREAM_CLOSED => 'Stream closed',
            Http2Parser::FRAME_SIZE_ERROR => 'Frame size error',
            Http2Parser::REFUSED_STREAM => 'Stream refused',
            Http2Parser::CANCEL => 'Stream cancelled',
            Http2Parser::COMPRESSION_ERROR => 'Compression error',
            Http2Parser::CONNECT_ERROR => 'Connect error',
            Http2Parser::ENHANCE_YOUR_CALM => 'Enhance your calm',
            Http2Parser::INADEQUATE_SECURITY => 'Inadequate security',
            Http2Parser::HTTP_1_1_REQUIRED => 'HTTP/1.1 required',
            default => 'Unknown error' . $errorCode
        };

        $this->handleStreamException(new Http2StreamException("Stream closed by server: $message", $streamId, $errorCode));
    }

    public function handleStreamException(Http2StreamException $exception): void
    {
        $id = $exception->getStreamId();
        $code = $exception->getCode();

        $exception = new SocketException($exception->getMessage(), $code, $exception);

        $this->releaseStream($id, $exception, $code === Http2Parser::REFUSED_STREAM);
    }

    public function handleConnectionException(Http2ConnectionException $exception): void
    {
        $this->shutdown(new SocketException($exception->getMessage(), $exception->getCode(), $exception));
    }

    public function handleData(int $streamId, string $data): void
    {
        $length = \strlen($data);

        $this->serverWindow -= $length;

        $this->increaseConnectionWindow();

        $stream = $this->streams[$streamId] ?? null;
        if (!$stream) {
            return;
        }

        $stream->disableInactivityWatcher();

        if (!$stream->body) {
            $this->handleStreamException(new Http2StreamException(
                "Stream headers not complete or body already complete",
                $streamId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $stream->serverWindow -= $length;
        $stream->received += $length;
        $stream->bufferSize += $length;

        if ($stream->request->getBodySizeLimit() > 0 && $stream->received >= $stream->request->getBodySizeLimit()) {
            $this->handleStreamException(new Http2StreamException(
                "Body size limit exceeded",
                $streamId,
                Http2Parser::CANCEL
            ));

            return;
        }

        if ($stream->expectedLength !== null && $stream->received > $stream->expectedLength) {
            $this->handleStreamException(new Http2StreamException(
                "Body size exceeded content-length in header",
                $streamId,
                Http2Parser::CANCEL
            ));

            return;
        }

        $response = $stream->response;
        \assert($response !== null);

        if (!$stream->bodyStarted) {
            $stream->bodyStarted = true;
            events()->responseBodyStart($stream->request, $stream->stream, $response);
        }

        events()->responseBodyProgress($stream->request, $stream->stream, $response);

        $stream->body?->pushAsync($data)->map(function () use ($streamId, $length): void {
            $stream = $this->streams[$streamId] ?? null;
            // Stream may have closed while waiting for body data to be consumed.
            if (!$stream) {
                return;
            }

            $stream->bufferSize -= $length;
            if ($stream->bufferSize === 0) {
                $stream->enableInactivityWatcher();
            }

            $this->increaseStreamWindow($stream);
        })->ignore();
    }

    public function handleSettings(array $settings): void
    {
        foreach ($settings as $setting => $value) {
            $this->applySetting($setting, $value);
        }

        $this->writeFrame(Http2Parser::SETTINGS, Http2Parser::ACK)->ignore();

        if ($this->settings) {
            $this->initialized = true;
            $this->settings->complete($this->remainingStreams);
            $this->settings = null;
        }
    }

    public function handleStreamEnd(int $streamId): void
    {
        $stream = $this->streams[$streamId] ?? null;
        if (!$stream) {
            return;
        }

        if ($stream->expectedLength !== null && $stream->received !== $stream->expectedLength) {
            $this->handleStreamException(new Http2StreamException(
                "Body length does not match content-length header",
                $streamId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        \assert($stream->body !== null);

        $stream->body->complete();
        $stream->body = null;

        $response = $stream->response;
        \assert($response !== null);

        if (EventInvoker::getPhase($stream->request) === Phase::ResponseHeaders) {
            events()->responseBodyStart($stream->request, $stream->stream, $response);
        }

        \assert($stream->response !== null);
        events()->responseBodyEnd($stream->request, $stream->stream, $response);

        // Trailers may have been received in handleHeaders(); if not, resolve with an empty set of trailers.
        if ($stream->trailers !== null) {
            $trailers = $stream->trailers;
            $stream->trailers = null;
            $trailers->complete(new Trailers([]));
        }

        $this->releaseStream($streamId, null, false);
    }

    public function isIdle(): bool
    {
        return empty($this->streams) && $this->reservedStreams === 0;
    }

    public function reserveStream(): void
    {
        if ($this->shutdown !== null || $this->hasWriteError || $this->hasTimeout) {
            throw new \Error("Can't reserve stream after shutdown started");
        }

        --$this->remainingStreams;
        ++$this->reservedStreams;
    }

    public function unreserveStream(): void
    {
        ++$this->remainingStreams;
        --$this->reservedStreams;

        \assert($this->remainingStreams <= $this->concurrentStreamLimit);
    }

    public function getRemainingStreams(): int
    {
        if ($this->shutdown !== null || $this->hasWriteError || $this->hasTimeout) {
            return 0;
        }

        return $this->remainingStreams;
    }

    public function request(Request $request, Cancellation $cancellation, Stream $stream): Response
    {
        try {
            if ($this->shutdown !== null) {
                throw new SocketException(\sprintf(
                    "Connection from '%s' to '%s' has already been shut down",
                    $this->socket->getLocalAddress()->toString(),
                    $this->socket->getRemoteAddress()->toString()
                ));
            }

            if ($this->hasTimeout && !$this->ping()->await()) {
                throw new SocketException(\sprintf(
                    "Socket to '%s' missed responding to PINGs",
                    $this->socket->getRemoteAddress()->toString()
                ));
            }

            RequestNormalizer::normalizeRequest($request);

            if ($request->getMethod() === 'CONNECT') {
                throw new HttpException("CONNECT requests are currently not supported on HTTP/2");
            }

            // Remove defunct HTTP/1.x headers.
            $request->removeHeader('host');
            $request->removeHeader('connection');
            $request->removeHeader('keep-alive');
            $request->removeHeader('transfer-encoding');
            $request->removeHeader('upgrade');

            $request->setProtocolVersions(['2']);

            if ($this->socket->isClosed()) {
                throw new SocketException(\sprintf(
                    "Socket to '%s' closed before the request could be sent",
                    $this->socket->getRemoteAddress()->toString()
                ));
            }
        } catch (\Throwable $exception) {
            throw $this->wrapException($exception, "Request initialization failed");
        }

        if ($this->socket instanceof ResourceStream) {
            $this->socket->reference();
        }

        // Assign a stream ID just before sending the first frame so another request cannot send a frame with
        // a higher ID prior to the initial frame of this stream.
        $streamId = $this->streamId += 2; // Client streams should be odd-numbered, starting at 1.

        $this->streams[$streamId] = $http2stream = new Http2Stream(
            id: $streamId,
            request: $request,
            stream: $stream,
            cancellation: $cancellation,
            transferWatcher: $this->createStreamTransferWatcher($streamId, $request->getTransferTimeout()),
            inactivityWatcher: $this->createStreamInactivityWatcher($streamId, $request->getInactivityTimeout()),
            serverWindow: self::DEFAULT_WINDOW_SIZE,
            clientWindow: $this->initialWindowSize,
        );

        $cancellation = $http2stream->cancellation; // Use CompositeCancellation from Http2Stream.

        $cancellationId = $cancellation->subscribe(
            fn (CancelledException $exception) => $this->releaseStream($streamId, $exception, false),
        );

        \assert($http2stream->pendingResponse !== null);
        $responseFuture = $http2stream->pendingResponse->getFuture();

        \assert($http2stream->trailers !== null);
        $http2stream->trailers->getFuture()
            ->finally(static fn () => $cancellation->unsubscribe($cancellationId))
            ->ignore();

        async(function () use ($request, $stream, $http2stream, $cancellation): void {
            events()->requestHeaderStart($request, $stream);

            $body = $request->getBody()->getContent();
            $chunk = $body->read($cancellation);

            $headers = $this->hpack->encode($this->generateHeaders($request));

            $flag = Http2Parser::END_HEADERS;
            if ($chunk === null) {
                $http2stream->ended = true;
                $flag |= Http2Parser::END_STREAM;
            }

            if (\strlen($headers) > $this->frameSizeLimit) {
                $split = \str_split($headers, $this->frameSizeLimit);
                \assert($split !== false);

                $firstChunk = \array_shift($split);
                $lastChunk = \array_pop($split);

                $this->writeFrame(Http2Parser::HEADERS, stream: $http2stream->id, data: $firstChunk)->ignore();

                foreach ($split as $headerChunk) {
                    $this->writeFrame(Http2Parser::CONTINUATION, stream: $http2stream->id, data: $headerChunk)->ignore();
                }

                $this->writeFrame(Http2Parser::CONTINUATION, $flag, $http2stream->id, $lastChunk)->await();
            } else {
                $this->writeFrame(Http2Parser::HEADERS, $flag, $http2stream->id, $headers)->await();
            }

            $http2stream->requestHeaderCompletion->complete();

            events()->requestHeaderEnd($request, $stream);

            events()->requestBodyStart($request, $stream);

            if ($chunk === null) {
                $http2stream->requestBodyCompletion->complete();
            } else {
                $writeFuture = Future::complete();
                do {
                    // Wait for prior write to complete if we've buffered too much of the request body.
                    if (\strlen($http2stream->requestBodyBuffer) >= self::DEFAULT_MAX_FRAME_SIZE) {
                        $writeFuture->await($cancellation);
                    }

                    $writeFuture = $this->writeData($http2stream, $chunk);
                    events()->requestBodyProgress($request, $stream);

                    $chunk = $body->read($cancellation);
                } while ($chunk !== null);

                $http2stream->requestBodyCompletion->complete();

                $writeFuture->await($cancellation);
                $this->writeBufferedData($http2stream)->await($cancellation);
            }

            events()->requestBodyEnd($request, $stream);
        })->catch(function (\Throwable $exception) use ($http2stream, $cancellation, $cancellationId): void {
            $cancellation->unsubscribe($cancellationId);

            $exception = $this->wrapException(
                $exception,
                "Failed to write request (stream {$http2stream->id}) to socket",
            );

            if (!$http2stream->requestHeaderCompletion->isComplete()) {
                $http2stream->requestHeaderCompletion->error($exception);
            }

            if (!$http2stream->requestBodyCompletion->isComplete()) {
                $http2stream->requestBodyCompletion->error($exception);
            }

            $this->releaseStream($http2stream->id, $exception, false);
        });

        return $responseFuture->await();
    }

    public function isClosed(): bool
    {
        return $this->shutdown !== null || $this->socket->isClosed();
    }

    private function runReadFiber(): void
    {
        $parser = new Http2Parser($this, $this->hpack);

        try {
            $this->frameQueue->push(Http2Parser::PREFACE);

            $this->writeFrame(
                type: Http2Parser::SETTINGS,
                data: \pack(
                    "nNnNnNnN",
                    Http2Parser::ENABLE_PUSH,
                    1,
                    Http2Parser::MAX_CONCURRENT_STREAMS,
                    256,
                    Http2Parser::INITIAL_WINDOW_SIZE,
                    self::DEFAULT_WINDOW_SIZE,
                    Http2Parser::MAX_FRAME_SIZE,
                    self::DEFAULT_MAX_FRAME_SIZE
                )
            )->await();

            while (null !== $chunk = $this->socket->read()) {
                $parser->push($chunk);
            }
        } catch (\Throwable $exception) {
            $this->shutdown(new SocketException(
                "The HTTP/2 connection from '" . $this->socket->getLocalAddress() . "' to '" . $this->socket->getRemoteAddress() .
                "' closed due to an exception: " . $exception->getMessage(),
                Http2Parser::INTERNAL_ERROR,
                $exception,
            ));
        } finally {
            $parser->cancel();

            $this->cancelPongWatcher(false);

            if ($this->onClose !== null) {
                $onClose = $this->onClose;
                $this->onClose = null;

                foreach ($onClose as $callback) {
                    EventLoop::queue($callback);
                }
            }

            $this->frameQueue->complete();

            $this->shutdown();
        }
    }

    private function writeFrame(
        int $type,
        int $flags = Http2Parser::NO_FLAG,
        int $stream = 0,
        string $data = ''
    ): Future {
        if ($this->frameQueue->isComplete()) {
            return Future::complete();
        }

        return $this->frameQueue->pushAsync(Http2Parser::compileFrame($data, $type, $flags, $stream));
    }

    private function applySetting(int $setting, int $value): void
    {
        switch ($setting) {
            case Http2Parser::INITIAL_WINDOW_SIZE:
                if ($value < 0 || $value > 2147483647) { // (1 << 31) - 1
                    $this->handleConnectionException(new Http2ConnectionException(
                        "Invalid window size: {$value}",
                        Http2Parser::FLOW_CONTROL_ERROR
                    ));

                    return;
                }

                $priorWindowSize = $this->initialWindowSize;
                $this->initialWindowSize = $value;
                $difference = $this->initialWindowSize - $priorWindowSize;

                foreach ($this->streams as $stream) {
                    $stream->clientWindow += $difference;
                }

                // Settings ACK should be sent before HEADER or DATA frames.
                if ($difference > 0) {
                    EventLoop::queue(function (): void {
                        foreach ($this->streams as $stream) {
                            if ($this->clientWindow <= 0) {
                                return;
                            }

                            if ($stream->requestBodyBuffer === '' || $stream->clientWindow <= 0) {
                                continue;
                            }

                            try {
                                $this->writeBufferedData($stream)->await();
                            } catch (\Throwable $exception) {
                                $this->shutdown(new SocketException(
                                    'Failed to write to socket',
                                    Http2Parser::CONNECT_ERROR,
                                    $exception
                                ));
                            }
                        }
                    });
                }

                return;

            case Http2Parser::MAX_FRAME_SIZE:
                if ($value < 1 << 14 || $value >= 1 << 24) {
                    $this->handleConnectionException(new Http2ConnectionException(
                        "Invalid maximum frame size: {$value}",
                        Http2Parser::PROTOCOL_ERROR
                    ));

                    return;
                }

                $this->frameSizeLimit = $value;
                return;

            case Http2Parser::MAX_CONCURRENT_STREAMS:
                if ($value < 0 || $value > 2147483647) { // (1 << 31) - 1
                    $this->handleConnectionException(new Http2ConnectionException(
                        "Invalid concurrent streams value: {$value}",
                        Http2Parser::PROTOCOL_ERROR
                    ));

                    return;
                }

                $priorUsedStreams = $this->concurrentStreamLimit - $this->remainingStreams;

                $this->concurrentStreamLimit = $value;
                $this->remainingStreams = $this->concurrentStreamLimit - $priorUsedStreams;

                \assert($this->remainingStreams <= $this->concurrentStreamLimit);

                return;

            case Http2Parser::HEADER_TABLE_SIZE: // TODO Respect this setting from the server
            case Http2Parser::MAX_HEADER_LIST_SIZE: // TODO Respect this setting from the server
            case Http2Parser::ENABLE_PUSH: // No action needed.
            default: // Unknown setting, ignore (6.5.2).
                return;
        }
    }

    private function writeBufferedData(Http2Stream $stream): Future
    {
        if ($stream->ended) {
            return Future::complete();
        }

        $windowSize = \min($this->clientWindow, $stream->clientWindow);
        $length = \strlen($stream->requestBodyBuffer);

        if ($length <= $windowSize) {
            $stream->windowSizeIncrease?->complete();
            $stream->windowSizeIncrease = null;

            $this->clientWindow -= $length;
            $stream->clientWindow -= $length;

            if ($length > $this->frameSizeLimit) {
                $chunks = \str_split($stream->requestBodyBuffer, $this->frameSizeLimit);
                \assert($chunks !== false);

                $stream->requestBodyBuffer = \array_pop($chunks);

                foreach ($chunks as $chunk) {
                    $this->writeFrame(Http2Parser::DATA, Http2Parser::NO_FLAG, $stream->id, $chunk)->ignore();
                }
            }

            if ($stream->requestBodyCompletion->isComplete()) {
                $future = $this->writeFrame(
                    Http2Parser::DATA,
                    Http2Parser::END_STREAM,
                    $stream->id,
                    $stream->requestBodyBuffer
                );

                $stream->ended = true;
            } else {
                $future = $this->writeFrame(
                    Http2Parser::DATA,
                    Http2Parser::NO_FLAG,
                    $stream->id,
                    $stream->requestBodyBuffer
                );
            }

            $stream->requestBodyBuffer = "";
            $stream->enableInactivityWatcher();

            return $future;
        }

        if ($windowSize > 0) {
            // Read next body chunk if less than 8192 bytes will remain in the buffer
            if ($length - 8192 < $windowSize && $stream->windowSizeIncrease) {
                $stream->windowSizeIncrease->complete();
                $stream->windowSizeIncrease = null;
            }

            $data = $stream->requestBodyBuffer;
            $end = $windowSize - $this->frameSizeLimit;

            $stream->clientWindow -= $windowSize;
            $this->clientWindow -= $windowSize;

            for ($off = 0; $off < $end; $off += $this->frameSizeLimit) {
                $this->writeFrame(
                    Http2Parser::DATA,
                    Http2Parser::NO_FLAG,
                    $stream->id,
                    \substr($data, $off, $this->frameSizeLimit)
                )->ignore();
            }

            $future = $this->writeFrame(
                Http2Parser::DATA,
                Http2Parser::NO_FLAG,
                $stream->id,
                \substr($data, $off, $windowSize - $off)
            );

            $stream->requestBodyBuffer = \substr($data, $windowSize);
            $stream->enableInactivityWatcher();

            return $future;
        }

        if ($stream->windowSizeIncrease === null) {
            $stream->windowSizeIncrease = new DeferredFuture;
        }

        return $stream->windowSizeIncrease->getFuture();
    }

    private function releaseStream(int $streamId, ?\Throwable $exception, bool $unprocessed): void
    {
        $stream = $this->streams[$streamId] ?? null;
        if (!$stream) {
            return;
        }

        unset($this->streams[$streamId]);

        if ($streamId & 1) { // Client-initiated stream.
            $this->remainingStreams++;
            $this->reservedStreams--;

            \assert($this->remainingStreams <= $this->concurrentStreamLimit);
        }

        if ($stream->responsePending || $stream->body || $stream->trailers) {
            $exception ??= new SocketException(
                \sprintf("Stream %d closed unexpectedly", $streamId),
                Http2Parser::INTERNAL_ERROR
            );

            $exception = $this->wrapException($exception);

            $stream->responsePending = false;
            $stream->pendingResponse?->error($exception);
            $stream->pendingResponse = null;

            $stream->trailers?->error($exception);
            $stream->trailers = null;

            if (!$exception instanceof CancelledException) {
                $exception = new StreamException(
                    'HTTP response did not complete: ' . $exception->getMessage(),
                    previous: $exception,
                );
            }

            $stream->body?->error($exception);
            $stream->body = null;

            $this->writeFrame(
                Http2Parser::RST_STREAM,
                Http2Parser::NO_FLAG,
                $streamId,
                \pack("N", Http2Parser::CANCEL)
            )->ignore();

            if ($unprocessed) {
                events()->requestRejected($stream->request);
            }
        }

        $stream->cancel();

        if ($this->streams) {
            return;
        }

        if ($this->shutdown !== null) {
            $this->socket->close();
            return;
        }

        if ($this->socket instanceof ResourceStream) {
            $this->socket->unreference();
        }
    }

    /**
     * @return Future<bool> Resolved with true if a pong is received within the timeout, false if none is received.
     */
    private function ping(): Future
    {
        if ($this->onClose === null) {
            return Future::complete(false);
        }

        if ($this->pongDeferred !== null) {
            return $this->pongDeferred->getFuture();
        }

        $this->pongDeferred = $deferred = new DeferredFuture;
        $this->pongWatcher = EventLoop::delay(self::PONG_TIMEOUT, fn () => $this->cancelPongWatcher(false));

        $this->writeFrame(Http2Parser::PING, data: $this->counter++)->ignore();

        return $deferred->getFuture();
    }

    private function cancelPongWatcher(bool $receivedPong): void
    {
        if ($this->pongWatcher !== null) {
            EventLoop::cancel($this->pongWatcher);
            $this->pongWatcher = null;
        }

        $this->pongDeferred?->complete($receivedPong);
        $this->pongDeferred = null;
    }

    /**
     * @param HttpException|null $reason Shutdown reason.
     * @param int|null $lastId ID of last processed frame if available.
     */
    private function shutdown(?HttpException $reason = null, ?int $lastId = null): void
    {
        if ($this->shutdown !== null) {
            return;
        }

        if ($this->settings !== null) {
            $message = "Connection closed before HTTP/2 settings could be received";
            $this->settings->error(new SocketException($message, 0, $reason));
            $this->settings = null;
        }

        $previous = $reason?->getPrevious();
        $previous = $previous instanceof Http2ConnectionException ? $previous : null;

        $code = $previous?->getCode() ?? Http2Parser::GRACEFUL_SHUTDOWN;

        $this->shutdown = $code;

        if ($lastId === null) {
            $message = match ($code) {
                Http2Parser::PROTOCOL_ERROR,
                Http2Parser::FLOW_CONTROL_ERROR,
                Http2Parser::FRAME_SIZE_ERROR,
                Http2Parser::COMPRESSION_ERROR,
                Http2Parser::SETTINGS_TIMEOUT,
                Http2Parser::ENHANCE_YOUR_CALM => $previous?->getMessage(),
                default => null,
            };

            $this->writeFrame(Http2Parser::GOAWAY, data: \pack('NN', 0, $code) . $message)->ignore();

            foreach ($this->streams as $id => $stream) {
                $reason ??= $this->makeDefaultShutdownReason();
                $this->releaseStream($id, $reason, unprocessed: false);
            }

            return;
        }

        foreach ($this->streams as $id => $stream) {
            if ($id <= $lastId) {
                continue;
            }

            $reason ??= $this->makeDefaultShutdownReason();
            $this->releaseStream($id, $reason, unprocessed: true);
        }
    }

    private function makeDefaultShutdownReason(): SocketException
    {
        return new SocketException(
            "The HTTP/2 connection from '" . $this->socket->getLocalAddress() . "' to '"
            . $this->socket->getRemoteAddress() . "' closed unexpectedly",
            Http2Parser::INTERNAL_ERROR,
        );
    }

    /**
     * @psalm-return list<list{string, string}>
     *
     * @throws InvalidRequestException
     */
    private function generateHeaders(Request $request): array
    {
        $uri = $request->getUri();
        $path = normalizeRequestPathWithQuery($request);

        $authority = $uri->getHost();
        if ($port = $uri->getPort()) {
            $authority .= ':' . $port;
        }

        $headers = [
            [":authority", $authority],
            [":path", $path],
            [":scheme", $uri->getScheme()],
            [":method", $request->getMethod()],
        ];

        foreach ($request->getHeaders() as $field => $values) {
            foreach ($values as $value) {
                if ($field === 'te' && $value !== 'trailers') {
                    continue;
                }

                $headers[] = [$field, $value];
            }
        }

        return $headers;
    }

    private function writeData(Http2Stream $stream, string $data): Future
    {
        $stream->requestBodyBuffer .= $data;

        return $this->writeBufferedData($stream);
    }

    private function increaseConnectionWindow(): void
    {
        $increase = 0;

        while ($this->serverWindow <= self::MINIMUM_WINDOW) {
            $this->serverWindow += self::WINDOW_INCREMENT;
            $increase += self::WINDOW_INCREMENT;
        }

        if ($increase > 0) {
            $this->writeFrame(Http2Parser::WINDOW_UPDATE, data: \pack("N", self::WINDOW_INCREMENT))->ignore();
        }
    }

    private function increaseStreamWindow(Http2Stream $stream): void
    {
        $minWindow = \min($stream->request->getBodySizeLimit(), self::MINIMUM_WINDOW);
        $increase = 0;

        while (($stream->serverWindow + $stream->bufferSize) <= $minWindow) {
            $stream->serverWindow += self::WINDOW_INCREMENT;
            $increase += self::WINDOW_INCREMENT;
        }

        if ($increase > 0) {
            $this->writeFrame(
                Http2Parser::WINDOW_UPDATE,
                Http2Parser::NO_FLAG,
                $stream->id,
                \pack("N", self::WINDOW_INCREMENT)
            )->ignore();
        }
    }

    private function createStreamInactivityWatcher(int $streamId, float $timeout): ?string
    {
        return $this->createStreamTimeoutWatcher(
            $streamId,
            $timeout,
            "Inactivity timeout exceeded, more than {$timeout} seconds elapsed from last data received",
        );
    }

    private function createStreamTransferWatcher(int $streamId, float $timeout): ?string
    {
        return $this->createStreamTimeoutWatcher(
            $streamId,
            $timeout,
            "Allowed transfer timeout exceeded, took longer than {$timeout} s",
        );
    }

    private function createStreamTimeoutWatcher(int $streamId, float $timeout, string $message): ?string
    {
        if ($timeout <= 0) {
            return null;
        }

        $watcher = EventLoop::delay($timeout, function () use ($streamId, $timeout, $message): void {
            \assert(isset($this->streams[$streamId]), 'Stream watcher invoked after stream closed');
            $this->releaseStream($streamId, new TimeoutException($message), false);
        });

        EventLoop::unreference($watcher);

        return $watcher;
    }

    private function runWriteFiber(): void
    {
        try {
            $iterator = $this->frameQueue->iterate();

            while ($iterator->continue()) {
                if (!$this->socket->isWritable()) {
                    $this->hasWriteError = true;
                    $iterator->dispose();
                    return;
                }

                $frame = $iterator->getValue();
                $this->socket->write($frame);
            }
        } catch (\Throwable $exception) {
            $this->hasWriteError = true;

            $this->shutdown(new SocketException(
                "The HTTP/2 connection closed unexpectedly: " . $exception->getMessage(),
                Http2Parser::INTERNAL_ERROR,
                $exception,
            ));
        }
    }

    private function wrapException(\Throwable $exception, ?string $prefix = null): \Throwable
    {
        if ($exception instanceof HttpException || $exception instanceof CancelledException) {
            return $exception;
        }

        $message = $exception->getMessage();
        if ($prefix !== null) {
            $message = "{$prefix}: {$message}";
        }

        return new HttpException($message, 0, $exception);
    }
}
