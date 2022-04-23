<?php /** @noinspection PhpUnusedPrivateFieldInspection */

namespace Amp\Http\Client\Connection\Internal;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableIterableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\Client\Connection\Http2ConnectionException as ClientHttp2ConnectionException;
use Amp\Http\Client\Connection\Http2StreamException as ClientHttp2StreamException;
use Amp\Http\Client\Connection\HttpStream;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\HttpException;
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
use Amp\Http\InvalidHeaderException;
use Amp\Http\Status;
use Amp\Pipeline\Queue;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\InternetAddress;
use Amp\TimeoutCancellation;
use League\Uri;
use Revolt\EventLoop;
use function Amp\async;
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

    private EncryptableSocket $socket;

    /** @var Http2Stream[] */
    private array $streams = [];

    private int $serverWindow = self::DEFAULT_WINDOW_SIZE;

    private int $clientWindow = self::DEFAULT_WINDOW_SIZE;

    private int $initialWindowSize = self::DEFAULT_WINDOW_SIZE;

    private int $frameSizeLimit = self::DEFAULT_MAX_FRAME_SIZE;

    /** @var int Previous stream ID. */
    private int $streamId = -1;

    /** @var int Maximum number of streams that may be opened. Initially unlimited. */
    private int $concurrentStreamLimit = 2147483647;

    /** @var int Currently open or reserved streams. Initially unlimited. */
    private int $remainingStreams = 2147483647;

    private HPack $hpack;

    private ?DeferredFuture $settings = null;

    private bool $initializeStarted = false;

    private bool $initialized = false;

    private ?string $pongWatcher = null;

    private ?DeferredFuture $pongDeferred = null;

    private ?string $idleWatcher = null;

    private int $idlePings = 0;

    /** @var callable[]|null */
    private ?array $onClose = [];

    private bool $hasTimeout = false;

    private bool $hasWriteError = false;

    private int|null $shutdown = null;

    private Queue $frameQueue;

    public function __construct(EncryptableSocket $socket)
    {
        $this->socket = $socket;
        $this->hpack = new HPack;
        $this->frameQueue = new Queue();
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Returns once the connection has been initialized. A stream cannot be obtained from the
     * connection until the promise returned by this method resolves.
     */
    public function initialize(): void
    {
        if ($this->initializeStarted) {
            throw new \Error('Connection may only be initialized once');
        }

        $this->initializeStarted = true;

        if ($this->socket->isClosed()) {
            throw new UnprocessedRequestException(
                new SocketException('The socket closed before the connection could be initialized')
            );
        }

        $this->settings = new DeferredFuture;
        $future = $this->settings->getFuture();

        EventLoop::queue($this->runReadFiber(...));
        EventLoop::queue($this->runWriteFiber(...));

        $future->await();
    }

    public function onClose(callable $onClose): void
    {
        if ($this->onClose === null) {
            EventLoop::defer(fn () => $onClose($this));
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): void
    {
        $exception = new SocketException('Socket from \'' . $this->socket->getLocalAddress() . '\' to \'' .
            $this->socket->getRemoteAddress() . '\' closed');

        $this->shutdown($exception);

        $this->socket->close();

        if ($this->onClose !== null) {
            $onClose = $this->onClose;
            $this->onClose = null;

            foreach ($onClose as $callback) {
                EventLoop::defer(fn () => $callback($this));
            }
        }
    }

    public function handlePong(string $data): void
    {
        if ($this->pongDeferred === null) {
            return;
        }

        if ($this->pongWatcher !== null) {
            EventLoop::cancel($this->pongWatcher);
            $this->pongWatcher = null;
        }

        $this->hasTimeout = false;

        $deferred = $this->pongDeferred;
        $this->pongDeferred = null;
        $deferred->complete(true);
    }

    public function handlePing(string $data): void
    {
        $this->writeFrame(Http2Parser::PING, Http2Parser::ACK, 0, $data)->ignore();
    }

    public function handleShutdown(int $lastId, int $error): void
    {
        $message = \sprintf(
            "Received GOAWAY frame on '%s' from '%s' with error code %d",
            (string) $this->socket->getLocalAddress(),
            (string) $this->socket->getRemoteAddress(),
            $error
        );

        /**
         * @psalm-suppress DeprecatedClass
         * @noinspection PhpDeprecationInspection
         */
        $this->shutdown(new ClientHttp2ConnectionException($message, $error), $lastId);
    }

    public function handleStreamWindowIncrement(int $streamId, int $windowSize): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $stream = $this->streams[$streamId];

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

            $this->writeBufferedData($stream);
        }
    }

    public function handleHeaders(int $streamId, array $pseudo, array $headers, bool $streamEnded): void
    {
        foreach ($pseudo as $name => $value) {
            if (!isset(Http2Parser::KNOWN_RESPONSE_PSEUDO_HEADERS[$name])) {
                throw new Http2StreamException(
                    "Invalid pseudo header",
                    $streamId,
                    Http2Parser::PROTOCOL_ERROR
                );
            }
        }

        if (!isset($this->streams[$streamId])) {
            return;
        }

        $stream = $this->streams[$streamId];
        $stream->enableInactivityWatcher();

        $this->hasTimeout = false;

        if ($stream->trailers) {
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
            EventLoop::queue(function () use ($trailers, $stream, $streamId, $parsedTrailers): void {
                try {
                    foreach ($stream->request->getEventListeners() as $eventListener) {
                        $eventListener->completeReceivingResponse($stream->request, $stream->stream);
                    }

                    $trailers->complete($parsedTrailers);
                } catch (\Throwable $e) {
                    $trailers->error($e);
                    $this->handleStreamException(new Http2StreamException(
                        "Event listener error",
                        $streamId,
                        Http2Parser::CANCEL
                    ));
                }
            });

            $this->setupPingIfIdle();

            return;
        }

        if (!isset($pseudo[":status"])) {
            $this->handleConnectionException(new Http2ConnectionException(
                "No status pseudo header in response",
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        if (!\preg_match("/^[1-5]\d\d$/", $pseudo[":status"])) {
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

        $status = (int) $pseudo[":status"];

        if ($status === Status::SWITCHING_PROTOCOLS) {
            $this->handleConnectionException(new Http2ConnectionException(
                "Switching Protocols (101) is not part of HTTP/2",
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $response = new Response(
            '2',
            $status,
            Status::getReason($status),
            $headers,
            new ReadableBuffer,
            $stream->request
        );

        if ($status < 200) {
            $onInformationalResponse = $stream->request->getInformationalResponseHandler();
            $preResponseResolution = $stream->preResponseResolution;
            if ($onInformationalResponse !== null) {
                $stream->preResponseResolution = async(function () use (
                    $preResponseResolution,
                    $onInformationalResponse,
                    $response,
                    $stream,
                    $streamId
                ): void {
                    $preResponseResolution->await();

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

        \assert($stream->preResponseResolution === null);

        $stream->preResponseResolution = async(function () use ($stream, $streamId): void {
            try {
                foreach ($stream->request->getEventListeners() as $eventListener) {
                    $eventListener->startReceivingResponse($stream->request, $stream->stream);
                }
            } catch (\Throwable $e) {
                $this->handleStreamException(new Http2StreamException(
                    "Event listener error",
                    $streamId,
                    Http2Parser::CANCEL
                ));
            }
        });

        $stream->body = new Queue();
        $stream->trailers = new DeferredFuture;
        $stream->trailers->getFuture()->ignore();

        $bodyCancellation = new DeferredCancellation;
        $cancellationToken = new CompositeCancellation(
            $stream->cancellationToken,
            $bodyCancellation->getCancellation()
        );

        $response->setBody(
            new ResponseBodyStream(
                new ReadableIterableStream($stream->body->pipe()),
                $bodyCancellation
            )
        );
        $response->setTrailers($stream->trailers->getFuture());

        \assert($stream->pendingResponse !== null);

        $stream->responsePending = false;
        EventLoop::queue(static function () use ($response, $stream): void {
            try {
                $stream->requestBodyCompletion->getFuture()->await();
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

            $stream->expectedLength = (int) $contentLength;
        }

        $cancellationToken->subscribe(function (CancelledException $exception) use ($streamId): void {
            if (!isset($this->streams[$streamId])) {
                return;
            }

            if (!$this->streams[$streamId]->originalCancellation->isRequested()) {
                $this->hasTimeout = true;
                async(fn () => $this->ping())->ignore(); // async ping, if other requests occur, they wait for it

                $transferTimeout = $this->streams[$streamId]->request->getTransferTimeout();

                $exception = new TimeoutException(
                    'Allowed transfer timeout exceeded, took longer than ' . $transferTimeout . ' s',
                    0,
                    $exception
                );
            }

            $this->releaseStream($streamId, $exception);
        });
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
                throw new Http2StreamException(
                    "Invalid pseudo header",
                    $pushId,
                    Http2Parser::PROTOCOL_ERROR
                );
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
            $uri = Uri\Http::createFromComponents([
                "scheme" => $scheme,
                "host" => $host,
                "port" => $port,
                "path" => $target,
                "query" => $query,
            ]);
        } catch (\Exception $exception) {
            $this->handleConnectionException(new Http2ConnectionException(
                "Invalid push URI",
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

        $tokenSource = new DeferredCancellation();

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
            $parentStream->cancellationToken,
            $tokenSource->getCancellation(),
            $this->createStreamInactivityWatcher($pushId, $request->getInactivityTimeout()),
            self::DEFAULT_WINDOW_SIZE,
            0
        );

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

        EventLoop::queue(function () use ($pushId, $tokenSource, $stream): void {
            $cancellationToken = new CompositeCancellation(
                $stream->cancellationToken,
                $tokenSource->getCancellation()
            );

            $cancellationId = $cancellationToken->subscribe(function (CancelledException $exception) use (
                $pushId
            ): void {
                if (isset($this->streams[$pushId])) {
                    $this->releaseStream($pushId, $exception);
                }
            });

            $onPush = $stream->request->getPushHandler();

            try {
                \assert($onPush !== null);
                \assert($stream->pendingResponse !== null);

                $future = $stream->pendingResponse->getFuture()
                    ->finally(static fn () => $cancellationToken->unsubscribe($cancellationId));

                $onPush($stream->request, $future);
            } catch (HttpException | StreamException | CancelledException $exception) {
                $tokenSource->cancel($exception);
            } catch (\Throwable $exception) {
                $tokenSource->cancel($exception);
                throw $exception;
            }
        });
    }

    public function handlePriority(int $streamId, int $parentId, int $weight): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $stream = $this->streams[$streamId];

        $stream->dependency = $parentId;
        $stream->weight = $weight;
    }

    public function handleStreamReset(int $streamId, int $errorCode): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $this->handleStreamException(new Http2StreamException("Stream closed by server", $streamId, $errorCode));
    }

    public function handleStreamException(Http2StreamException $exception): void
    {
        $id = $exception->getStreamId();
        $code = $exception->getCode();

        /**
         * @psalm-suppress DeprecatedClass
         * @psalm-suppress InvalidScalarArgument
         * @noinspection PhpDeprecationInspection
         */
        $exception = new ClientHttp2StreamException($exception->getMessage(), $id, $code, $exception);

        if ($code === Http2Parser::REFUSED_STREAM) {
            $exception = new UnprocessedRequestException($exception);
        }

        if (isset($this->streams[$id])) {
            $this->releaseStream($id, $exception);
        }
    }

    public function handleConnectionException(Http2ConnectionException $exception): void
    {
        /**
         * @psalm-suppress DeprecatedClass
         * @psalm-suppress InvalidScalarArgument
         * @noinspection PhpDeprecationInspection
         */
        $this->shutdown(
            new ClientHttp2ConnectionException($exception->getMessage(), $exception->getCode(), $exception)
        );

        $this->close();
    }

    public function handleData(int $streamId, string $data): void
    {
        $length = \strlen($data);

        $this->serverWindow -= $length;

        $this->increaseConnectionWindow();

        if (!isset($this->streams[$streamId])) {
            return;
        }

        $stream = $this->streams[$streamId];
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

        $stream->body->pushAsync($data)->map(function () use ($stream, $streamId, $length): void {
            // Stream may have closed while waiting for body data to be consumed.
            if (!isset($this->streams[$streamId])) {
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
            $deferred = $this->settings;
            $this->settings = null;
            $this->initialized = true;
            $deferred->complete($this->remainingStreams);
        }
    }

    public function handleStreamEnd(int $streamId): void
    {
        if (!isset($this->streams[$streamId])) {
            return;
        }

        $stream = $this->streams[$streamId];

        if ($stream->expectedLength !== null && $stream->received !== $stream->expectedLength) {
            $this->handleStreamException(new Http2StreamException(
                "Body length does not match content-length header",
                $streamId,
                Http2Parser::PROTOCOL_ERROR
            ));

            return;
        }

        $body = $stream->body;
        $stream->body = null;

        \assert($body !== null);

        $trailers = $stream->trailers;
        $stream->trailers = null;

        \assert($trailers !== null);

        $body->complete();

        EventLoop::queue(function () use ($trailers, $stream, $streamId): void {
            try {
                foreach ($stream->request->getEventListeners() as $eventListener) {
                    $eventListener->completeReceivingResponse($stream->request, $stream->stream);
                }

                $trailers->complete(new Trailers([]));
            } catch (\Throwable $e) {
                $trailers->error($e);
                $this->handleStreamException(new Http2StreamException(
                    "Event listener error",
                    $streamId,
                    Http2Parser::CANCEL
                ));
            }
        });

        $this->setupPingIfIdle();

        // Stream might be cancelled right after body completion
        if (isset($this->streams[$streamId])) {
            $this->releaseStream($streamId);
        }
    }

    public function reserveStream(): void
    {
        if ($this->shutdown !== null || $this->hasWriteError || $this->hasTimeout) {
            throw new \Error("Can't reserve stream after shutdown started");
        }

        --$this->remainingStreams;
    }

    public function unreserveStream(): void
    {
        ++$this->remainingStreams;

        \assert($this->remainingStreams <= $this->concurrentStreamLimit);
    }

    public function getRemainingStreams(): int
    {
        if ($this->shutdown !== null || $this->hasWriteError || $this->hasTimeout) {
            return 0;
        }

        return $this->remainingStreams;
    }

    public function request(Request $request, Cancellation $cancellationToken, Stream $stream): Response
    {
        if ($this->shutdown !== null) {
            $exception = new UnprocessedRequestException(new SocketException(\sprintf(
                "Connection from '%s' to '%s' has already been shut down",
                (string) $this->socket->getLocalAddress(),
                (string) $this->socket->getRemoteAddress()
            )));

            foreach ($request->getEventListeners() as $eventListener) {
                $eventListener->abort($request, $exception);
            }

            throw $exception;
        }

        if ($this->hasTimeout && !$this->ping()) {
            $exception = new UnprocessedRequestException(
                new SocketException(\sprintf(
                    "Socket to '%s' missed responding to PINGs",
                    (string) $this->socket->getRemoteAddress()
                ))
            );

            foreach ($request->getEventListeners() as $eventListener) {
                $eventListener->abort($request, $exception);
            }

            throw $exception;
        }

        $this->idlePings = 0;
        $this->cancelIdleWatcher();

        RequestNormalizer::normalizeRequest($request);

        // Remove defunct HTTP/1.x headers.
        $request->removeHeader('host');
        $request->removeHeader('connection');
        $request->removeHeader('keep-alive');
        $request->removeHeader('transfer-encoding');
        $request->removeHeader('upgrade');

        $request->setProtocolVersions(['2']);

        if ($request->getMethod() === 'CONNECT') {
            $exception = new HttpException("CONNECT requests are currently not supported on HTTP/2");

            foreach ($request->getEventListeners() as $eventListener) {
                $eventListener->abort($request, $exception);
            }

            throw $exception;
        }

        if ($this->socket->isClosed()) {
            $exception = new UnprocessedRequestException(
                new SocketException(\sprintf(
                    "Socket to '%s' closed before the request could be sent",
                    (string) $this->socket->getRemoteAddress()
                ))
            );

            foreach ($request->getEventListeners() as $eventListener) {
                $eventListener->abort($request, $exception);
            }

            throw $exception;
        }

        $originalCancellation = $cancellationToken;
        if ($request->getTransferTimeout() > 0) {
            $cancellationToken = new CompositeCancellation(
                $cancellationToken,
                new TimeoutCancellation($request->getTransferTimeout())
            );
        }

        try {
            $headers = $this->generateHeaders($request);
            $body = $request->getBody()->createBodyStream();

            foreach ($request->getEventListeners() as $eventListener) {
                $eventListener->startSendingRequest($request, $stream);
            }

            $chunk = $body->read();

            $streamId = $this->streamId += 2; // Client streams should be odd-numbered, starting at 1.

            $this->streams[$streamId] = $http2stream = new Http2Stream(
                $streamId,
                $request,
                $stream,
                $cancellationToken,
                $originalCancellation,
                $this->createStreamInactivityWatcher($streamId, $request->getInactivityTimeout()),
                self::DEFAULT_WINDOW_SIZE,
                $this->initialWindowSize
            );

            $this->socket->reference();

            $transferTimeout = $request->getTransferTimeout();
            $cancellationId = $cancellationToken->subscribe(function (CancelledException $exception) use (
                $streamId,
                $originalCancellation,
                $transferTimeout
            ): void {
                if (!isset($this->streams[$streamId])) {
                    return;
                }

                if (!$originalCancellation->isRequested()) {
                    $exception = new TimeoutException(
                        'Allowed transfer timeout exceeded, took longer than ' . $transferTimeout . ' s',
                        0,
                        $exception
                    );
                }

                $this->releaseStream($streamId, $exception);

                if (!$originalCancellation->isRequested()) {
                    $this->hasTimeout = true;
                    $this->ping(); // async ping, if other requests occur, they wait for it
                }
            });

            if (!isset($this->streams[$streamId])) {
                foreach ($request->getEventListeners() as $eventListener) {
                    $eventListener->completeSendingRequest($request, $stream);
                }

                \assert($http2stream->pendingResponse !== null);

                return $http2stream->pendingResponse->getFuture()->await();
            }

            $flag = Http2Parser::END_HEADERS | ($chunk === null ? Http2Parser::END_STREAM : Http2Parser::NO_FLAG);

            $headers = $this->hpack->encode($headers);
            if (\strlen($headers) > $this->frameSizeLimit) {
                $split = \str_split($headers, $this->frameSizeLimit);

                $firstChunk = \array_shift($split);
                $lastChunk = \array_pop($split);

                $this->writeFrame(Http2Parser::HEADERS, Http2Parser::NO_FLAG, $streamId, $firstChunk)->ignore();

                foreach ($split as $headerChunk) {
                    $this->writeFrame(Http2Parser::CONTINUATION, Http2Parser::NO_FLAG, $streamId, $headerChunk)->ignore();
                }

                $this->writeFrame(Http2Parser::CONTINUATION, $flag, $streamId, $lastChunk)->ignore();
            } else {
                $this->writeFrame(Http2Parser::HEADERS, $flag, $streamId, $headers)->ignore();
            }

            if ($chunk === null) {
                foreach ($request->getEventListeners() as $eventListener) {
                    $eventListener->completeSendingRequest($request, $stream);
                }

                $http2stream->requestBodyCompletion->complete(null);

                \assert($http2stream->pendingResponse !== null);

                return $http2stream->pendingResponse->getFuture()->await();
            }

            $buffer = $chunk;
            while (null !== $chunk = $body->read()) {
                if (!isset($this->streams[$streamId])) {
                    foreach ($request->getEventListeners() as $eventListener) {
                        $eventListener->completeSendingRequest($request, $stream);
                    }

                    \assert($http2stream->pendingResponse !== null);

                    return $http2stream->pendingResponse->getFuture()->await();
                }

                $this->writeData($http2stream, $buffer)->await();

                $buffer = $chunk;
            }

            if (!isset($this->streams[$streamId])) {
                foreach ($request->getEventListeners() as $eventListener) {
                    $eventListener->completeSendingRequest($request, $stream);
                }

                \assert($http2stream->pendingResponse !== null);

                return $http2stream->pendingResponse->getFuture()->await();
            }

            \assert($http2stream->pendingResponse !== null);

            $responseFuture = $http2stream->pendingResponse->getFuture();

            $http2stream->requestBodyCompletion->complete();

            $this->writeData($http2stream, $buffer)->await();

            foreach ($request->getEventListeners() as $eventListener) {
                $eventListener->completeSendingRequest($request, $stream);
            }

            /** @var Response $response */
            $response = $responseFuture->await();

            $response->getTrailers()
                ->finally(static fn () => $cancellationToken->unsubscribe($cancellationId))
                ->ignore();

            return $response;
        } catch (\Throwable $exception) {
            if (isset($cancellationId)) {
                $cancellationToken->unsubscribe($cancellationId);
            }

            if (isset($streamId) && isset($this->streams[$streamId])) {
                \assert(isset($http2stream));

                if (!$http2stream->requestBodyCompletion->isComplete()) {
                    $http2stream->requestBodyCompletion->error($exception);
                }

                $this->releaseStream($streamId, $exception);
            }

            if ($exception instanceof StreamException) {
                $message = 'Failed to write request (stream ' . ($streamId ?? 'not assigned') . ') to socket: ' .
                    $exception->getMessage();
                $exception = new SocketException($message, 0, $exception);
            }

            throw $exception;
        }
    }

    public function isClosed(): bool
    {
        return $this->onClose === null;
    }

    private function runReadFiber(): void
    {
        try {
            $this->frameQueue->pushAsync(Http2Parser::PREFACE)->ignore();

            $this->writeFrame(
                Http2Parser::SETTINGS,
                0,
                0,
                \pack(
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
            )->ignore();
        } catch (\Throwable $e) {
            /**
             * @psalm-suppress DeprecatedClass
             * @noinspection PhpDeprecationInspection
             */
            $this->shutdown(new ClientHttp2ConnectionException(
                "The HTTP/2 connection closed" . ($this->shutdown !== null ? ' unexpectedly' : ''),
                $this->shutdown ?? Http2Parser::GRACEFUL_SHUTDOWN
            ), 0);

            $this->close();

            return;
        }

        $parser = (new Http2Parser($this))->parse();

        try {
            while (null !== $chunk = $this->socket->read()) {
                $parser->send($chunk);

                if (!$parser->valid()) {
                    break;
                }
            }

            /**
             * @psalm-suppress DeprecatedClass
             * @noinspection PhpDeprecationInspection
             */
            $this->shutdown(new ClientHttp2ConnectionException(
                "The HTTP/2 connection from '" . $this->socket->getLocalAddress() . "' to '" . $this->socket->getRemoteAddress() .
                    "' closed" . ($this->shutdown === null ? ' unexpectedly' : ''),
                $this->shutdown ?? Http2Parser::INTERNAL_ERROR
            ));

            $this->close();
        } catch (\Throwable $exception) {
            /**
             * @psalm-suppress DeprecatedClass
             * @noinspection PhpDeprecationInspection
             */
            $this->shutdown(new ClientHttp2ConnectionException(
                "The HTTP/2 connection from '" . $this->socket->getLocalAddress() . "' to '" . $this->socket->getRemoteAddress() .
                    "' closed unexpectedly: " . $exception->getMessage(),
                Http2Parser::INTERNAL_ERROR,
                $exception
            ));

            $this->close();
        }
    }

    private function writeFrame(
        int $type,
        int $flags = Http2Parser::NO_FLAG,
        int $stream = 0,
        string $data = ''
    ): Future {
        \assert(Http2Parser::logDebugFrame('send', $type, $flags, $stream, \strlen($data)));

        return $this->frameQueue->pushAsync(\pack("NcN", (\strlen($data) << 8) | ($type & 0xff), $flags, $stream) . $data);
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
        if ($stream->requestBodyCompletion->isComplete() && $stream->requestBodyBuffer === '') {
            return Future::complete();
        }

        $windowSize = \min($this->clientWindow, $stream->clientWindow);
        $length = \strlen($stream->requestBodyBuffer);

        if ($length <= $windowSize) {
            if ($stream->windowSizeIncrease) {
                $deferred = $stream->windowSizeIncrease;
                $stream->windowSizeIncrease = null;
                $deferred->complete();
            }

            $this->clientWindow -= $length;
            $stream->clientWindow -= $length;

            if ($length > $this->frameSizeLimit) {
                $chunks = \str_split($stream->requestBodyBuffer, $this->frameSizeLimit);
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
                $deferred = $stream->windowSizeIncrease;
                $stream->windowSizeIncrease = null;
                $deferred->complete(null);
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

    private function releaseStream(int $streamId, ?\Throwable $exception = null): void
    {
        \assert(isset($this->streams[$streamId]));

        $stream = $this->streams[$streamId];

        unset($this->streams[$streamId]);

        if ($streamId & 1) { // Client-initiated stream.
            $this->remainingStreams++;

            \assert($this->remainingStreams <= $this->concurrentStreamLimit);
        }

        if ($stream->responsePending || $stream->body || $stream->trailers) {
            /**
             * @psalm-suppress DeprecatedClass
             * @noinspection PhpDeprecationInspection
             */
            $exception = $exception ?? new ClientHttp2StreamException(
                \sprintf("Stream %d closed unexpectedly", $streamId),
                $streamId,
                Http2Parser::INTERNAL_ERROR
            );

            if (!$exception instanceof HttpException && !$exception instanceof CancelledException) {
                $exception = new HttpException($exception->getMessage(), 0, $exception);
            }

            if ($stream->pendingResponse) {
                $stream->responsePending = false;
                $stream->pendingResponse->error($exception);
                $stream->pendingResponse = null;
            }

            if ($stream->body) {
                $stream->body->error($exception);
                $stream->body = null;
            }

            if ($stream->trailers) {
                $stream->trailers->error($exception);
                $stream->trailers = null;
            }

            $this->writeFrame(
                Http2Parser::RST_STREAM,
                Http2Parser::NO_FLAG,
                $streamId,
                \pack("N", Http2Parser::CANCEL)
            )->ignore();

            $request = $stream->request;
            $listeners = $request->getEventListeners();
            if ($listeners) {
                EventLoop::queue(static function () use ($request, $listeners, $exception): void {
                    foreach ($listeners as $eventListener) {
                        $eventListener->abort($request, $exception);
                    }
                });
            }
        }

        if (!$this->streams && !$this->socket->isClosed()) {
            $this->socket->unreference();
        }

        if (!$this->streams && $this->shutdown !== null) {
            $this->close();
        }
    }

    private function setupPingIfIdle(): void
    {
        if ($this->idleWatcher !== null) {
            return;
        }

        $this->idleWatcher = EventLoop::defer(function ($watcher): void {
            \assert($this->idleWatcher === null || $this->idleWatcher === $watcher);

            $this->idleWatcher = null;
            if (!empty($this->streams)) {
                return;
            }

            $this->idleWatcher = EventLoop::delay(300000, function ($watcher): void {
                \assert($this->idleWatcher === null || $this->idleWatcher === $watcher);
                \assert(empty($this->streams));

                $this->idleWatcher = null;

                try {
                    // Connection idle for 10 minutes
                    if ($this->idlePings >= 1) {
                        $this->shutdown(new HttpException('Too many pending pings'));
                        $this->close();
                        return;
                    }

                    if ($this->ping()) {
                        $this->setupPingIfIdle();
                    }
                } catch (\Throwable $exception) {
                    $this->close();
                }
            });

            EventLoop::unreference($this->idleWatcher);
        });

        EventLoop::unreference($this->idleWatcher);
    }

    private function cancelIdleWatcher(): void
    {
        if ($this->idleWatcher !== null) {
            EventLoop::cancel($this->idleWatcher);
            $this->idleWatcher = null;
        }
    }

    /**
     * @return bool Fulfilled with true if a pong is received within the timeout, false if none is received.
     */
    private function ping(): bool
    {
        if ($this->onClose === null) {
            return false;
        }

        if ($this->pongDeferred !== null) {
            return $this->pongDeferred->getFuture()->await();
        }

        $this->pongDeferred = new DeferredFuture;
        $this->idlePings++;

        $future = $this->pongDeferred->getFuture();
        $this->pongWatcher = EventLoop::delay(self::PONG_TIMEOUT, function (): void {
            $this->hasTimeout = false;

            $deferred = $this->pongDeferred;
            $this->pongDeferred = null;

            \assert($deferred !== null);

            $deferred->complete(false);

            // Shutdown connection to stop new requests, but keep it open, as other responses might still arrive
            $this->shutdown(new HttpException('PONG timeout of ' . self::PONG_TIMEOUT . 'ms reached'), \max(0, $this->streamId));
        });

        $this->writeFrame(Http2Parser::PING, 0, 0, $this->counter++)->ignore();

        return $future->await();
    }

    /**
     * @param HttpException $reason Shutdown reason.
     * @param int|null      $lastId ID of last processed frame. Null to use the last opened frame ID or 0 if no
     *                              streams have been opened.
     */
    private function shutdown(HttpException $reason, ?int $lastId = null): void
    {
        $code = (int) $reason->getCode();
        $this->shutdown = $code;

        if ($this->settings !== null) {
            $settings = $this->settings;
            $this->settings = null;

            $message = "Connection closed before HTTP/2 settings could be received";
            $settings->error(new UnprocessedRequestException(new SocketException($message, 0, $reason)));
        }

        if ($this->streams) {
            $reason = $lastId !== null ? new UnprocessedRequestException($reason) : $reason;
            foreach ($this->streams as $id => $stream) {
                if ($lastId !== null && $id <= $lastId) {
                    continue;
                }

                $this->releaseStream($id, $reason);
            }
        }
    }

    /**
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
            $this->writeFrame(Http2Parser::WINDOW_UPDATE, 0, 0, \pack("N", self::WINDOW_INCREMENT))->ignore();
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
        if ($timeout <= 0) {
            return null;
        }

        $watcher = EventLoop::delay($timeout, function () use ($streamId, $timeout): void {
            if (!isset($this->streams[$streamId])) {
                return;
            }

            $this->releaseStream(
                $streamId,
                new TimeoutException("Inactivity timeout exceeded, more than {$timeout} seconds elapsed from last data received")
            );
        });

        EventLoop::unreference($watcher);

        return $watcher;
    }

    private function runWriteFiber(): void
    {
        try {
            foreach ($this->frameQueue->iterate() as $frame) {
                $this->socket->write($frame);
            }
        } catch (\Throwable $exception) {
            $this->hasWriteError = true;

            /**
             * @psalm-suppress DeprecatedClass
             * @noinspection PhpDeprecationInspection
             */
            $this->shutdown(new ClientHttp2ConnectionException(
                "The HTTP/2 connection closed unexpectedly: " . $exception->getMessage(),
                Http2Parser::INTERNAL_ERROR,
                $exception
            ), \max(0, $this->streamId));
        }
    }
}
