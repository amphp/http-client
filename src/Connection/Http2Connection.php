<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\CombinedCancellationToken;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Failure;
use Amp\Http\Client\Connection\Internal\Http2Stream;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\Trailers;
use Amp\Http\HPack;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Status;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use League\Uri;
use function Amp\asyncCall;
use function Amp\call;

final class Http2Connection implements Connection
{
    private const PROTOCOL_VERSIONS = ['2'];

    public const PREFACE = "PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n";
    public const DEFAULT_MAX_FRAME_SIZE = 1 << 14;
    public const DEFAULT_WINDOW_SIZE = (1 << 16) - 1;

    public const MAX_INCREMENT = (1 << 16) - 1;

    private const HEADER_NAME_REGEX = '/^[\x21-\x40\x5b-\x7e]+$/';

    public const NOFLAG = "\x00";
    public const ACK = "\x01";
    public const END_STREAM = "\x01";
    public const END_HEADERS = "\x04";
    public const PADDED = "\x08";
    public const PRIORITY_FLAG = "\x20";

    public const DATA = "\x00";
    public const HEADERS = "\x01";
    public const PRIORITY = "\x02";
    public const RST_STREAM = "\x03";
    public const SETTINGS = "\x04";
    public const PUSH_PROMISE = "\x05";
    public const PING = "\x06";
    public const GOAWAY = "\x07";
    public const WINDOW_UPDATE = "\x08";
    public const CONTINUATION = "\x09";

    // Settings
    public const HEADER_TABLE_SIZE = 0x1; // 1 << 12
    public const ENABLE_PUSH = 0x2; // 1
    public const MAX_CONCURRENT_STREAMS = 0x3; // INF
    public const INITIAL_WINDOW_SIZE = 0x4; // 1 << 16 - 1
    public const MAX_FRAME_SIZE = 0x5; // 1 << 14
    public const MAX_HEADER_LIST_SIZE = 0x6; // INF

    // Error codes
    public const GRACEFUL_SHUTDOWN = 0x0;
    public const PROTOCOL_ERROR = 0x1;
    public const INTERNAL_ERROR = 0x2;
    public const FLOW_CONTROL_ERROR = 0x3;
    public const SETTINGS_TIMEOUT = 0x4;
    public const STREAM_CLOSED = 0x5;
    public const FRAME_SIZE_ERROR = 0x6;
    public const REFUSED_STREAM = 0x7;
    public const CANCEL = 0x8;
    public const COMPRESSION_ERROR = 0x9;
    public const CONNECT_ERROR = 0xa;
    public const ENHANCE_YOUR_CALM = 0xb;
    public const INADEQUATE_SECURITY = 0xc;
    public const HTTP_1_1_REQUIRED = 0xd;

    private const KNOWN_RESPONSE_PSEUDO_HEADERS = [
        ":status" => 1,
    ];

    private const KNOWN_REQUEST_PSEUDO_HEADERS = [
        ":method" => 1,
        ":authority" => 1,
        ":path" => 1,
        ":scheme" => 1,
    ];

    public const DEFAULT_MAX_HEADER_SIZE = 1 << 20;
    public const DEFAULT_MAX_BODY_SIZE = 1 << 30;

    /** @var Socket */
    private $socket;

    /** @var callable[]|null */
    private $onClose = [];

    /** @var Http2Stream[] */
    private $streams = [];

    /** @var int */
    private $serverWindow = self::DEFAULT_WINDOW_SIZE;

    /** @var int */
    private $clientWindow = self::DEFAULT_WINDOW_SIZE;

    /** @var int */
    private $initialWindowSize = self::DEFAULT_WINDOW_SIZE;

    /** @var int */
    private $maxFrameSize = self::DEFAULT_MAX_FRAME_SIZE;

    /** @var int Previous stream ID. */
    private $streamId = -1;

    /** @var Deferred[] */
    private $pendingRequests = [];

    /** @var Emitter[] */
    private $bodyEmitters = [];

    /** @var Deferred[] */
    private $trailerDeferreds = [];

    /** @var int Maximum number of streams that may be opened. Initially unlimited. */
    private $maxStreams = \PHP_INT_MAX;

    /** @var int Currently open or reserved streams. Initially unlimited. */
    private $remainingStreams = \PHP_INT_MAX;

    /** @var HPack */
    private $table;

    /** @var Deferred|null */
    private $settingsDeferred;

    /** @var bool */
    private $initialized = false;

    public function __construct(Socket $socket)
    {
        $this->table = new HPack;

        $this->socket = $socket;

        if ($this->socket->isClosed()) {
            $this->onClose = null;
        }
    }

    /**
     * Returns a promise that is resolved once the connection has been initialized. A stream cannot be obtained from the
     * connection until the promise returned by this method resolves.
     *
     * @return Promise
     */
    public function initialize(): Promise
    {
        if ($this->initialized) {
            throw new \Error('Connection may only be initialized once');
        }

        $this->initialized = true;

        if ($this->socket->isClosed()) {
            return new Failure(new SocketException('The socket closed before the connection could be initialized'));
        }

        $this->settingsDeferred = new Deferred;
        $promise = $this->settingsDeferred->promise();

        Promise\rethrow(new Coroutine($this->run()));

        return $promise;
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function getStream(Request $request): Stream
    {
        if (!$this->initialized || $this->settingsDeferred !== null) {
            throw new \Error('The promise returned from ' . __CLASS__ . '::initialize() must resolve before using the connection');
        }

        if ($this->remainingStreams <= 0) {
            throw new SocketException('All available streams have been used');
        }

        --$this->remainingStreams;

        return new HttpStream(
            $this,
            \Closure::fromCallable([$this, 'request']),
            \Closure::fromCallable([$this, 'release'])
        );
    }

    private function request(Request $request, CancellationToken $token): Promise
    {
        $request = clone $request;

        // Remove defunct HTTP/1.x headers.
        $request->removeHeader('host');
        $request->removeHeader('connection');

        $id = $this->streamId += 2; // Client streams should be odd-numbered, starting at 1.

        $this->streams[$id] = $stream = new Http2Stream(
            self::DEFAULT_WINDOW_SIZE,
            $this->initialWindowSize,
            $request->getHeaderSizeLimit(),
            $request->getBodySizeLimit()
        );

        $stream->request = $request;
        $stream->cancellationToken = $token;

        if ($request->getTransferTimeout() > 0) {
            // Cancellation token combined with timeout token should not be stored in $stream->cancellationToken,
            // otherwise the timeout applies to the body transfer and pushes.
            $token = new CombinedCancellationToken($token, new TimeoutCancellationToken($request->getTransferTimeout()));
        }

        return call(function () use ($id, $request, $token): \Generator {
            $this->pendingRequests[$id] = $deferred = new Deferred;

            $cancellationId = $token->subscribe(function (CancelledException $exception) use ($id): void {
                if (!isset($this->streams[$id])) {
                    return;
                }

                $this->writeFrame(\pack("N", self::CANCEL), self::RST_STREAM, self::NOFLAG, $id);
                $this->releaseStream($id, $exception);
            });

            try {
                $this->socket->reference();

                $uri = $request->getUri();

                $path = $uri->getPath();
                if ($path === '') {
                    $path = '/';
                }

                $query = $uri->getQuery();
                if ($query !== '') {
                    $path .= '?' . $query;
                }

                $body = $request->getBody();

                $headers = yield $request->getBody()->getHeaders();
                foreach ($headers as $name => $header) {
                    if (!$request->hasHeader($name)) {
                        $request->setHeaders([$name => $header]);
                    }
                }

                $headers = \array_merge([
                    ":authority" => [$uri->getAuthority()],
                    ":path" => [$path],
                    ":scheme" => [$uri->getScheme()],
                    ":method" => [$request->getMethod()],
                ], $request->getHeaders());

                $headers = $this->table->encode($headers);

                $stream = $body->createBodyStream();

                $chunk = yield $stream->read();

                if (!isset($this->streams[$id]) || $token->isRequested()) {
                    return yield $deferred->promise();
                }

                $flag = self::END_HEADERS | ($chunk === null ? self::END_STREAM : "\0");

                if (\strlen($headers) > $this->maxFrameSize) {
                    $split = \str_split($headers, $this->maxFrameSize);
                    $headers = \array_shift($split);
                    yield $this->writeFrame($headers, self::HEADERS, self::NOFLAG, $id);

                    $headers = \array_pop($split);
                    foreach ($split as $msgPart) {
                        yield $this->writeFrame($msgPart, self::CONTINUATION, self::NOFLAG, $id);
                    }
                    yield $this->writeFrame($headers, self::CONTINUATION, $flag, $id);
                } else {
                    yield $this->writeFrame($headers, self::HEADERS, $flag, $id);
                }

                if ($chunk === null) {
                    return yield $deferred->promise();
                }

                $buffer = $chunk;
                while (null !== $chunk = yield $stream->read()) {
                    if (!isset($this->streams[$id]) || $token->isRequested()) {
                        return yield $deferred->promise();
                    }

                    yield $this->writeData($buffer, $id);
                    $buffer = $chunk;
                }

                if (!isset($this->streams[$id]) || $token->isRequested()) {
                    return yield $deferred->promise();
                }

                $this->streams[$id]->state |= Http2Stream::LOCAL_CLOSED;

                yield $this->writeData($buffer, $id);

                return yield $deferred->promise();
            } catch (\Throwable $exception) {
                if (isset($this->streams[$id])) {
                    $this->releaseStream($id, $exception);
                }
                throw $exception;
            } finally {
                $token->unsubscribe($cancellationId);
            }
        });
    }

    private function release(): void
    {
        ++$this->remainingStreams;
    }

    public function isBusy(): bool
    {
        return $this->remainingStreams <= 0 || $this->socket->isClosed();
    }

    public function onClose(callable $onClose): void
    {
        if ($this->onClose === null) {
            asyncCall($onClose, $this);
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): Promise
    {
        return $this->shutdown();
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket instanceof EncryptableSocket ? $this->socket->getTlsInfo() : null;
    }

    private function run(): \Generator
    {
        try {
            // Write initial preface
            yield $this->socket->write(self::PREFACE);

            yield $this->writeFrame(
                \pack(
                    "nNnNnNnNnN",
                    self::ENABLE_PUSH,
                    1,
                    self::MAX_CONCURRENT_STREAMS,
                    256,
                    self::INITIAL_WINDOW_SIZE,
                    self::DEFAULT_WINDOW_SIZE,
                    self::MAX_HEADER_LIST_SIZE,
                    self::DEFAULT_MAX_HEADER_SIZE,
                    self::MAX_FRAME_SIZE,
                    self::DEFAULT_MAX_FRAME_SIZE
                ),
                self::SETTINGS,
                self::NOFLAG
            );

            $parser = $this->parser();

            while (null !== $chunk = yield $this->socket->read()) {
                $promise = $parser->send($chunk);

                \assert($promise === null || $promise instanceof Promise);

                while ($promise instanceof Promise) {
                    yield $promise; // Wait for promise to resolve before resuming parser and reading more data.
                    $promise = $parser->send(null);
                    \assert($promise === null || $promise instanceof Promise);
                }
            }
        } catch (\Throwable $exception) {
            if ($this->settingsDeferred !== null) {
                $deferred = $this->settingsDeferred;
                $this->settingsDeferred = null;
                $deferred->fail($exception);
            }
        } finally {
            $this->shutdown(null, $exception ?? null);
        }
    }

    private function writeFrame(string $data, string $type, string $flags, int $stream = 0): Promise
    {
        $data = \substr(\pack("N", \strlen($data)), 1, 3) . $type . $flags . \pack("N", $stream) . $data;
        return $this->socket->write($data);
    }

    private function writeData(string $data, int $stream): Promise
    {
        \assert(isset($this->streams[$stream]), "The stream was closed");

        $this->streams[$stream]->buffer .= $data;

        return $this->writeBufferedData($stream);
    }

    private function writeBufferedData(int $id): Promise
    {
        $stream = $this->streams[$id];
        $delta = \min($this->clientWindow, $stream->clientWindow);
        $length = \strlen($stream->buffer);

        if ($delta >= $length) {
            $this->clientWindow -= $length;

            if ($length > $this->maxFrameSize) {
                $split = \str_split($stream->buffer, $this->maxFrameSize);
                $stream->buffer = \array_pop($split);
                foreach ($split as $part) {
                    $this->writeFrame($part, self::DATA, self::NOFLAG, $id);
                }
            }

            if ($stream->state & Http2Stream::LOCAL_CLOSED) {
                $promise = $this->writeFrame($stream->buffer, self::DATA, self::END_STREAM, $id);
            } else {
                $promise = $this->writeFrame($stream->buffer, self::DATA, self::NOFLAG, $id);
            }

            $stream->clientWindow -= $length;
            $stream->buffer = "";

            if ($stream->deferred) {
                $deferred = $stream->deferred;
                $stream->deferred = null;
                $deferred->resolve();
            }

            return $promise;
        }

        if ($delta > 0) {
            $data = $stream->buffer;
            $end = $delta - $this->maxFrameSize;

            $stream->clientWindow -= $delta;
            $this->clientWindow -= $delta;

            for ($off = 0; $off < $end; $off += $this->maxFrameSize) {
                $this->writeFrame(\substr($data, $off, $this->maxFrameSize), self::DATA, self::NOFLAG, $id);
            }

            $this->writeFrame(\substr($data, $off, $delta - $off), self::DATA, self::NOFLAG, $id);

            $stream->buffer = \substr($data, $delta);
        }

        if ($stream->deferred === null) {
            $stream->deferred = new Deferred;
        }

        return $stream->deferred->promise();
    }

    private function releaseStream(int $id, \Throwable $exception = null): void
    {
        \assert(isset($this->streams[$id]), "Tried to release a non-existent stream");

        if (isset($this->bodyEmitters[$id])) {
            $emitter = $this->bodyEmitters[$id];
            unset($this->bodyEmitters[$id]);
            $emitter->fail($exception ?? new SocketException("Server disconnected", self::CANCEL));
        }

        if (isset($this->trailerDeferreds[$id])) {
            $deferred = $this->trailerDeferreds[$id];
            unset($this->trailerDeferreds[$id]);
            $deferred->fail($exception ?? new SocketException("Server disconnected", self::CANCEL));
        }

        if (isset($this->pendingRequests[$id])) {
            $deferred = $this->pendingRequests[$id];
            unset($this->pendingRequests[$id]);
            $deferred->fail($exception ?? new SocketException("Server disconnected", self::CANCEL));
        }

        unset($this->streams[$id]);

        if ($id & 1) { // Client-initiated stream.
            $this->remainingStreams++;
        }

        if (empty($this->pendingRequests) && empty($this->bodyEmitters) && empty($this->trailerDeferreds)) {
            $this->socket->unreference();
        }
    }

    /**
     * @return \Generator
     */
    private function parser(): \Generator
    {
        $maxHeaderSize = self::DEFAULT_MAX_FRAME_SIZE; // Should be configurable?

        $frameCount = 0;
        $bytesReceived = 0;
        $continuation = false;

        $buffer = yield;

        while (true) {
            while (\strlen($buffer) < 9) {
                $buffer .= yield;
            }

            $length = \unpack("N", "\0" . \substr($buffer, 0, 3))[1];
            $frameCount++;
            $bytesReceived += $length;

            try {
                if ($length > self::DEFAULT_MAX_FRAME_SIZE) { // Do we want to allow increasing max frame size?
                    throw new Http2ConnectionException("Max frame size exceeded", self::FRAME_SIZE_ERROR);
                }

                $type = $buffer[3];
                $flags = $buffer[4];
                $id = \unpack("N", \substr($buffer, 5, 4))[1];

                // If the highest bit is 1, ignore it.
                if ($id & 0x80000000) {
                    $id &= 0x7fffffff;
                }

                $buffer = \substr($buffer, 9);

                // Fail if expecting a continuation frame and anything else is received.
                if ($continuation && $type !== self::CONTINUATION) {
                    throw new Http2ConnectionException("Expected continuation frame", self::PROTOCOL_ERROR);
                }

                switch ($type) {
                    case self::DATA:
                        $padding = 0;

                        if (($flags & self::PADDED) !== "\0") {
                            if ($buffer === "") {
                                $buffer = yield;
                            }
                            $padding = \ord($buffer);
                            $buffer = \substr($buffer, 1);
                            $length--;

                            if ($padding > $length) {
                                throw new Http2ConnectionException("Padding greater than length", self::PROTOCOL_ERROR);
                            }
                        }

                        if ($id === 0) {
                            throw new Http2ConnectionException("Invalid stream ID", self::PROTOCOL_ERROR);
                        }

                        if (!isset($this->streams[$id])) {
                            throw new Http2StreamException("Stream ID not found", $id, self::CANCEL);
                        }

                        $stream = $this->streams[$id];

                        if ($stream->headers !== null) {
                            throw new Http2StreamException("Stream headers not complete", $id, self::PROTOCOL_ERROR);
                        }

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            throw new Http2StreamException("Stream remote closed", $id, self::PROTOCOL_ERROR);
                        }

                        $this->serverWindow -= $length;
                        $stream->serverWindow -= $length;
                        $stream->received += $length;

                        if ($stream->received >= $stream->maxBodySize && ($flags & self::END_STREAM) === "\0") {
                            throw new Http2StreamException("Max body size exceeded", $id, self::CANCEL);
                        }

                        while (\strlen($buffer) < $length) {
                            /* it is fine to just .= the $body as $length < 2^14 */
                            $buffer .= yield;
                        }

                        $body = \substr($buffer, 0, $length - $padding);
                        $buffer = \substr($buffer, $length);

                        if ($this->serverWindow <= 0) {
                            $this->serverWindow += self::MAX_INCREMENT;
                            $this->writeFrame(\pack("N", self::MAX_INCREMENT), self::WINDOW_UPDATE, self::NOFLAG);
                        }

                        // Stream may close while reading body chunk.
                        if (!isset($this->bodyEmitters[$id])) {
                            continue 2;
                        }

                        if ($body !== "") {
                            if (\is_int($stream->expectedLength)) {
                                $stream->expectedLength -= \strlen($body);
                            }

                            $promise = $this->bodyEmitters[$id]->emit($body);

                            if ($stream->serverWindow <= 0) {
                                $promise->onResolve(function (?\Throwable $exception) use ($id): void {
                                    if ($exception || !isset($this->streams[$id])) {
                                        return;
                                    }

                                    $stream = $this->streams[$id];

                                    if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                                        return;
                                    }

                                    $increment = \min($stream->maxBodySize - $stream->received, self::MAX_INCREMENT);
                                    $stream->serverWindow += $increment;

                                    $this->writeFrame(\pack("N", $increment), self::WINDOW_UPDATE, self::NOFLAG, $id);
                                });
                            }
                        }

                        if (($flags & self::END_STREAM) !== "\0") {
                            $stream->state |= Http2Stream::REMOTE_CLOSED;

                            if ($stream->expectedLength) {
                                throw new Http2StreamException("Body length does not match content-length header", $id, self::PROTOCOL_ERROR);
                            }

                            if (!isset($this->bodyEmitters[$id], $this->trailerDeferreds[$id])) {
                                continue 2; // Stream closed after emitting body fragment.
                            }

                            $deferred = $this->trailerDeferreds[$id];
                            $emitter = $this->bodyEmitters[$id];

                            unset($this->bodyEmitters[$id], $this->trailerDeferreds[$id]);

                            $emitter->complete();
                            $deferred->resolve(new Trailers([]));

                            $this->releaseStream($id);
                        }

                        continue 2;

                    case self::PUSH_PROMISE:
                        if (!isset($this->streams[$id])) {
                            throw new Http2StreamException("Stream closed", $id, self::CANCEL);
                        }

                        $parent = $this->streams[$id];

                        while (\strlen($buffer) < 4) {
                            $buffer .= yield;
                        }

                        $pushedId = \unpack("N", $buffer)[1];

                        // If the highest bit is 1, ignore it.
                        if ($pushedId & 0x80000000) {
                            $pushedId &= 0x7fffffff;
                        }

                        $buffer = \substr($buffer, 4);
                        $length -= 4;

                        if ($parent->request->getPushCallable() === null || isset($this->streams[$pushedId])) {
                            throw new Http2StreamException("Push promise refused", $pushedId, self::CANCEL);
                        }

                        $this->streams[$pushedId] = $stream = new Http2Stream(
                            self::DEFAULT_WINDOW_SIZE,
                            0,
                            $parent->request->getHeaderSizeLimit(),
                            $parent->request->getBodySizeLimit()
                        );

                        $stream->parent = $parent; // Set parent stream on new stream.
                        $stream->dependency = $id;
                        $stream->weight = $parent->weight;
                        $stream->cancellationToken = $parent->cancellationToken;

                        $id = $pushedId; // Switch ID to pushed stream for parsing headers.

                        // no break to fall through to parsing remainder of PUSH_PROMISE frame as HEADERS frame.

                    case self::HEADERS:
                        if (!isset($this->streams[$id])) {
                            throw new Http2ConnectionException("Headers already started on stream", self::PROTOCOL_ERROR);
                        }

                        $stream = $this->streams[$id];

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            throw new Http2StreamException("Stream remote closed", $id, self::STREAM_CLOSED);
                        }

                        if (($flags & self::PADDED) !== "\0") {
                            if ($buffer === "") {
                                $buffer = yield;
                            }
                            $padding = \ord($buffer);
                            $buffer = \substr($buffer, 1);
                            $length--;
                        } else {
                            $padding = 0;
                        }

                        if (($flags & self::PRIORITY_FLAG) !== "\0") {
                            while (\strlen($buffer) < 5) {
                                $buffer .= yield;
                            }

                            $parent = \unpack("N", $buffer)[1];

                            if ($exclusive = $parent & 0x80000000) {
                                $parent &= 0x7fffffff;
                            }

                            if ($id === 0 || $parent === $id) {
                                throw new Http2ConnectionException("Invalid dependency ID", self::PROTOCOL_ERROR);
                            }

                            $stream->dependency = $parent;
                            $stream->weight = \ord($buffer[4]);

                            $buffer = \substr($buffer, 5);
                            $length -= 5;
                        }

                        if ($padding >= $length) {
                            throw new Http2ConnectionException("Padding greater than length", self::PROTOCOL_ERROR);
                        }

                        if ($length > $maxHeaderSize) {
                            throw new Http2StreamException("Headers exceed maximum length", $id, self::ENHANCE_YOUR_CALM);
                        }

                        while (\strlen($buffer) < $length) {
                            $buffer .= yield;
                        }

                        $stream->headers = \substr($buffer, 0, $length - $padding);
                        $buffer = \substr($buffer, $length);

                        if (($flags & self::END_STREAM) !== "\0") {
                            $stream->state |= Http2Stream::REMOTE_CLOSED;
                        }

                        if (($flags & self::END_HEADERS) !== "\0") {
                            goto parse_headers;
                        }

                        $continuation = true;

                        continue 2;

                    case self::PRIORITY:
                        if ($length !== 5) {
                            throw new Http2ConnectionException("Invalid frame size", self::PROTOCOL_ERROR);
                        }

                        while (\strlen($buffer) < 5) {
                            $buffer .= yield;
                        }

                        $parent = \unpack("N", $buffer)[1];
                        if ($exclusive = $parent & 0x80000000) {
                            $parent &= 0x7fffffff;
                        }

                        $weight = \ord($buffer[4]);
                        $buffer = \substr($buffer, 5);

                        if ($id === 0 || $parent === $id) {
                            throw new Http2ConnectionException("Invalid dependency ID", self::PROTOCOL_ERROR);
                        }

                        if (!isset($this->streams[$id])) {
                            throw new Http2ConnectionException("Stream not found", self::PROTOCOL_ERROR);
                        }

                        $stream = $this->streams[$id];

                        if ($stream->headers !== null) {
                            throw new Http2ConnectionException("Headers not complete", self::PROTOCOL_ERROR);
                        }

                        $stream->dependency = $parent;
                        $stream->weight = $weight;

                        continue 2;

                    case self::RST_STREAM:
                        if ($length !== 4) {
                            throw new Http2ConnectionException("Invalid frame size", self::PROTOCOL_ERROR);
                        }

                        if ($id === 0) {
                            throw new Http2ConnectionException("Invalid stream ID", self::PROTOCOL_ERROR);
                        }

                        while (\strlen($buffer) < 4) {
                            $buffer .= yield;
                        }

                        $error = \unpack("N", $buffer)[1];

                        if (isset($this->streams[$id])) {
                            $this->releaseStream($id, new SocketException("Server ended stream", $error));
                        }

                        $buffer = \substr($buffer, 4);
                        continue 2;

                    case self::SETTINGS:
                        if ($id !== 0) {
                            throw new Http2ConnectionException("Non-zero stream ID with settings frame", self::PROTOCOL_ERROR);
                        }

                        if (($flags & self::ACK) !== "\0") {
                            if ($length) {
                                throw new Http2ConnectionException("Invalid frame size", self::PROTOCOL_ERROR);
                            }

                            // Got ACK
                            continue 2;
                        }

                        if ($length % 6 !== 0) {
                            throw new Http2ConnectionException("Invalid frame size", self::PROTOCOL_ERROR);
                        }

                        if ($length > 60) {
                            // Even with room for a few future options, sending that a big SETTINGS frame is just about
                            // wasting our processing time. I hereby declare this a protocol error.
                            throw new Http2ConnectionException("Settings frame too big", self::PROTOCOL_ERROR);
                        }

                        while (\strlen($buffer) < $length) {
                            $buffer .= yield;
                        }

                        while ($length > 0) {
                            $this->updateSetting($buffer);
                            $buffer = \substr($buffer, 6);
                            $length -= 6;
                        }

                        $this->writeFrame("", self::SETTINGS, self::ACK);

                        if ($this->settingsDeferred) {
                            $deferred = $this->settingsDeferred;
                            $this->settingsDeferred = null;
                            $deferred->resolve($this->remainingStreams);
                        }

                        continue 2;

                    case self::PING:
                        if ($length !== 8) {
                            throw new Http2ConnectionException("Invalid frame size", self::PROTOCOL_ERROR);
                        }

                        if ($id !== 0) {
                            throw new Http2ConnectionException("Non-zero stream ID with ping frame", self::PROTOCOL_ERROR);
                        }

                        while (\strlen($buffer) < 8) {
                            $buffer .= yield;
                        }

                        $data = \substr($buffer, 0, 8);

                        if (($flags & self::ACK) === "\0") {
                            $this->writeFrame($data, self::PING, self::ACK);
                        }

                        $buffer = \substr($buffer, 8);

                        continue 2;

                    case self::GOAWAY:
                        if ($id !== 0) {
                            throw new Http2ConnectionException("Non-zero stream ID with goaway frame", self::PROTOCOL_ERROR);
                        }

                        $lastId = \unpack("N", $buffer)[1];
                        // If the highest bit is 1, ignore it.
                        if ($lastId & 0x80000000) {
                            $lastId &= 0x7fffffff;
                        }
                        $error = \unpack("N", \substr($buffer, 4, 4))[1];

                        $buffer = \substr($buffer, 8);
                        $length -= 8;

                        while (\strlen($buffer) < $length) {
                            $buffer .= yield;
                        }

                        $message = \sprintf(
                            "Received GOAWAY frame from %s with error code %d",
                            $this->socket->getRemoteAddress(),
                            $error
                        );

                        $this->shutdown($lastId, new Http2ConnectionException($message, $error));

                        return;

                    case self::WINDOW_UPDATE:
                        if ($length !== 4) {
                            throw new Http2ConnectionException("Invalid frame size", self::FRAME_SIZE_ERROR);
                        }

                        while (\strlen($buffer) < 4) {
                            $buffer .= yield;
                        }

                        if ($buffer === "\0\0\0\0") {
                            if ($id) {
                                throw new Http2StreamException("Invalid window update value", $id, self::PROTOCOL_ERROR);
                            }
                            throw new Http2ConnectionException("Invalid window update value", self::PROTOCOL_ERROR);
                        }

                        $windowSize = \unpack("N", $buffer)[1];
                        $buffer = \substr($buffer, 4);

                        if ($id) {
                            if (!isset($this->streams[$id])) {
                                continue 2;
                            }

                            $stream = $this->streams[$id];

                            if ($stream->clientWindow + $windowSize > (2 << 30) - 1) {
                                throw new Http2StreamException("Current window size plus new window exceeds maximum size", $id, self::FLOW_CONTROL_ERROR);
                            }

                            $stream->clientWindow += $windowSize;
                        } else {
                            if ($this->clientWindow + $windowSize > (2 << 30) - 1) {
                                throw new Http2ConnectionException("Current window size plus new window exceeds maximum size", self::FLOW_CONTROL_ERROR);
                            }

                            $this->clientWindow += $windowSize;
                        }

                        Loop::defer(\Closure::fromCallable([$this, 'sendBufferedData']));

                        continue 2;

                    case self::CONTINUATION:
                        if (!isset($this->streams[$id])) {
                            throw new Http2ConnectionException("Invalid stream ID", self::PROTOCOL_ERROR);
                        }

                        $continuation = true;

                        $stream = $this->streams[$id];

                        if ($stream->headers === null) {
                            throw new Http2ConnectionException("No headers received before continuation frame", self::PROTOCOL_ERROR);
                        }

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            $continuation = false;
                            throw new Http2StreamException("Stream remote closed", $id, self::ENHANCE_YOUR_CALM);
                        }

                        if ($length > $maxHeaderSize - \strlen($stream->headers)) {
                            $continuation = false;
                            throw new Http2StreamException("Headers exceed maximum length", $id, self::ENHANCE_YOUR_CALM);
                        }

                        while (\strlen($buffer) < $length) {
                            $buffer .= yield;
                        }

                        $stream->headers .= \substr($buffer, 0, $length);
                        $buffer = \substr($buffer, $length);

                        if (($flags & self::END_STREAM) !== "\0") {
                            $stream->state |= Http2Stream::REMOTE_CLOSED;
                        }

                        if (($flags & self::END_HEADERS) !== "\0") {
                            $continuation = false;
                            goto parse_headers;
                        }

                        continue 2;

                    default: // Ignore and discard unknown frame per spec.
                        while (\strlen($buffer) < $length) {
                            $buffer .= yield;
                        }

                        $buffer = \substr($buffer, $length);

                        continue 2;
                }

                parse_headers: {
                    $decoded = $this->table->decode($stream->headers, $maxHeaderSize);
                    $stream->headers = null;

                    if ($decoded === null) {
                        throw new Http2ConnectionException("Compression error in headers", self::COMPRESSION_ERROR);
                    }

                    $headers = [];
                    $pseudo = [];
                    $knownHeaders = $stream->request === null
                        ? self::KNOWN_REQUEST_PSEUDO_HEADERS
                        : self::KNOWN_RESPONSE_PSEUDO_HEADERS;

                    foreach ($decoded as list($name, $value)) {
                        if (!\preg_match(self::HEADER_NAME_REGEX, $name)) {
                            throw new Http2StreamException("Invalid header field name", $id, self::PROTOCOL_ERROR);
                        }

                        if ($name[0] === ':') {
                            if (!empty($headers) || !isset($knownHeaders[$name]) || isset($pseudo[$name])) {
                                throw new Http2ConnectionException("Unknown or invalid pseudo headers", self::PROTOCOL_ERROR);
                            }

                            $pseudo[$name] = $value;
                            continue;
                        }

                        $headers[$name][] = $value;
                    }

                    try {
                        if ($stream->request === null) {
                            // Request originated from push promise.

                            if (!isset($pseudo[":method"], $pseudo[":path"], $pseudo[":scheme"], $pseudo[":authority"])
                                || isset($headers["connection"])
                                || $pseudo[":path"] === ''
                                || (isset($headers["te"]) && \implode($headers["te"]) !== "trailers")
                            ) {
                                throw new Http2StreamException("Invalid header values", $id, self::PROTOCOL_ERROR);
                            }

                            $method = $pseudo[":method"];
                            $target = $pseudo[":path"];
                            $scheme = $pseudo[":scheme"];
                            $host = $pseudo[":authority"];
                            $query = null;

                            if ($method !== 'GET') {
                                throw new Http2ConnectionException("Push promises method must be GET", self::PROTOCOL_ERROR);
                            }

                            if (!\preg_match("#^([A-Z\d\.\-]+|\[[\d:]+\])(?::([1-9]\d*))?$#i", $host, $matches)) {
                                throw new Http2StreamException("Invalid authority (host) name", $id, self::PROTOCOL_ERROR);
                            }

                            $host = $matches[1];
                            $port = isset($matches[2]) ? (int) $matches[2] : $this->socket->getLocalAddress()->getPort();

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
                                    "host"   => $host,
                                    "port"   => $port,
                                    "path"   => $target,
                                    "query"  => $query,
                                ]);
                            } catch (Uri\UriException $exception) {
                                throw new Http2ConnectionException("Invalid push promise URI", self::PROTOCOL_ERROR);
                            }

                            $stream->request = new Request($uri, $method);
                            $stream->request->setHeaders($headers);
                            $stream->request->setProtocolVersions(['2']);
                            $stream->request->onPush($stream->parent->request->getPushCallable());

                            $this->pendingRequests[$id] = $deferred = new Deferred;

                            asyncCall(function () use ($id, $stream, $deferred): \Generator {
                                $tokenSource = new CancellationTokenSource;
                                $cancellationToken = new CombinedCancellationToken($stream->cancellationToken, $tokenSource->getToken());

                                $cancellationId = $cancellationToken->subscribe(function (CancelledException $exception) use ($id): void {
                                    if (!isset($this->streams[$id])) {
                                        return;
                                    }

                                    $this->writeFrame(\pack("N", self::CANCEL), self::RST_STREAM, self::NOFLAG, $id);
                                    $this->releaseStream($id, $exception);
                                });

                                $onPush = $stream->request->getPushCallable();

                                try {
                                    yield call($onPush, clone $stream->request, $deferred->promise(), $tokenSource);
                                } catch (\Throwable $exception) {
                                    $tokenSource->cancel($exception);
                                    throw $exception;
                                } finally {
                                    $cancellationToken->unsubscribe($cancellationId);
                                }
                            });

                            continue;
                        }

                        if (isset($this->trailerDeferreds[$id]) && $stream->state & Http2Stream::RESERVED) {
                            if (($flags & self::END_STREAM) === "\0" || $stream->expectedLength) {
                                throw new Http2StreamException("Stream not ended before receiving trailers", $id, self::PROTOCOL_ERROR);
                            }

                            // Trailers must not contain pseudo-headers.
                            if (!empty($pseudo)) {
                                throw new Http2StreamException("Trailers must not contain pseudo headers", $id, self::PROTOCOL_ERROR);
                            }

                            try {
                                // Trailers constructor checks for any disallowed fields.
                                $headers = new Trailers($headers);
                            } catch (InvalidHeaderException $exception) {
                                throw new Http2StreamException("Disallowed trailer field name", $id, self::PROTOCOL_ERROR, $exception);
                            }

                            $deferred = $this->trailerDeferreds[$id];
                            $emitter = $this->bodyEmitters[$id];

                            unset($this->bodyEmitters[$id], $this->trailerDeferreds[$id]);

                            $emitter->complete();
                            $deferred->resolve($headers);

                            continue;
                        }

                        if (!isset($pseudo[":status"])) {
                            throw new Http2ConnectionException("No status psuedo header in response", self::PROTOCOL_ERROR);
                        }

                        if (!\preg_match("/^[1-9]\d\d$/", $pseudo[":status"])) {
                            throw new Http2StreamException("Invalid response status code", $id, self::PROTOCOL_ERROR);
                        }

                        $status = (int) $pseudo[":status"];

                        if ($stream->state & Http2Stream::RESERVED) {
                            throw new Http2StreamException("Stream already reserved", $id, self::PROTOCOL_ERROR);
                        }

                        $stream->state |= Http2Stream::RESERVED;

                        if (!isset($this->pendingRequests[$id])) {
                            throw new Http2StreamException("Stream already used", $id, self::INTERNAL_ERROR);
                        }

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            $response = new Response(
                                '2',
                                $status,
                                Status::getReason($status),
                                $headers,
                                new InMemoryStream,
                                $stream->request
                            );

                            $this->releaseStream($id); // Response has no body, release stream immediately.
                        } else {
                            $this->trailerDeferreds[$id] = new Deferred;
                            $this->bodyEmitters[$id] = new Emitter;

                            if ($this->serverWindow <= $stream->maxBodySize >> 1) {
                                $increment = $stream->maxBodySize - $this->serverWindow;
                                $this->serverWindow = $stream->maxBodySize;
                                $this->writeFrame(\pack("N", $increment), self::WINDOW_UPDATE, self::NOFLAG);
                            }

                            if (isset($headers["content-length"])) {
                                $contentLength = \implode($headers["content-length"]);
                                if (!\preg_match('/^(0|[1-9][0-9]*)$/', $contentLength)) {
                                    throw new Http2StreamException("Invalid content-length header value", $id, self::PROTOCOL_ERROR);
                                }

                                $stream->expectedLength = (int) $contentLength;
                            }

                            $tokenSource = new CancellationTokenSource;
                            $cancellationToken = new CombinedCancellationToken($stream->cancellationToken, $tokenSource->getToken());

                            $cancellationToken->subscribe(function (CancelledException $exception) use ($id): void {
                                if (!isset($this->streams[$id])) {
                                    return;
                                }

                                $this->writeFrame(\pack("N", self::CANCEL), self::RST_STREAM, self::NOFLAG, $id);
                                $this->releaseStream($id, $exception);
                            });

                            $response = new Response(
                                '2',
                                $status,
                                Status::getReason($status),
                                $headers,
                                new ResponseBodyStream(new IteratorStream($this->bodyEmitters[$id]->iterate()), $tokenSource),
                                $stream->request,
                                $this->trailerDeferreds[$id]->promise()
                            );

                            $tokenSource = $cancellationToken = null; // Remove reference to cancellation token.
                        }

                        $deferred = $this->pendingRequests[$id];
                        unset($this->pendingRequests[$id]);
                        $deferred->resolve($response);
                    } finally {
                        $deferred = $response = null; // Remove reference to response from parser.
                    }
                }
            } catch (Http2StreamException $exception) {
                $id = $exception->getStreamId();
                $code = $exception->getCode();

                $this->writeFrame(\pack("N", $code), self::RST_STREAM, self::NOFLAG, $id);

                if (isset($this->streams[$id])) {
                    $this->releaseStream($id, $exception);
                }

                // consume whole frame to be able to continue this connection
                $length -= \strlen($buffer);
                while ($length > 0) {
                    $buffer = yield;
                    $length -= \strlen($buffer);
                }
                $buffer = \substr($buffer, \strlen($buffer) + $length);
            } catch (Http2ConnectionException $exception) {
                $this->shutdown(null, $exception);
                throw $exception;
            }
        }
    }

    /**
     * @param int|null   $lastId ID of last processed frame. Null to use the last opened frame ID or 0 if no frames have
     *                           been opened.
     * @param \Throwable $reason
     *
     * @return Promise
     */
    private function shutdown(?int $lastId = null, ?\Throwable $reason = null): Promise
    {
        if ($this->onClose === null) {
            return new Success;
        }

        $code = $reason ? $reason->getCode() : self::GRACEFUL_SHUTDOWN;
        $lastId = $lastId ?? ($id ?? 0);
        $promise = $this->writeFrame(\pack("NN", $lastId, $code), self::GOAWAY, self::NOFLAG);

        $this->socket->close();

        if (!empty($this->streams)) {
            $exception = new SocketException("Connection closed", $reason->getCode(), $reason);
            foreach ($this->streams as $id => $stream) {
                $this->releaseStream($id, $exception);
            }
        }

        if ($this->onClose !== null) {
            $onClose = $this->onClose;
            $this->onClose = null;

            foreach ($onClose as $callback) {
                asyncCall($callback, $this);
            }
        }

        return $promise;
    }


    /**
     * @param string $buffer Entire settings frame payload. Only the first 6 bytes are examined.
     *
     * @throws Http2ConnectionException Thrown if the setting is invalid.
     */
    private function updateSetting(string $buffer): void
    {
        $unpacked = \unpack("nsetting/Nvalue", $buffer);

        if ($unpacked["value"] < 0) {
            throw new Http2ConnectionException("Invalid settings value", self::PROTOCOL_ERROR);
        }

        switch ($unpacked["setting"]) {
            case self::INITIAL_WINDOW_SIZE:
                if ($unpacked["value"] >= 1 << 31) {
                    throw new Http2ConnectionException("Invalid window size", self::FLOW_CONTROL_ERROR);
                }

                $priorWindowSize = $this->initialWindowSize;
                $this->initialWindowSize = $unpacked["value"];
                $difference = $this->initialWindowSize - $priorWindowSize;

                foreach ($this->streams as $stream) {
                    $stream->clientWindow += $difference;
                }

                // Settings ACK should be sent before HEADER or DATA frames.
                Loop::defer(\Closure::fromCallable([$this, 'sendBufferedData']));
                return;

            case self::ENABLE_PUSH:
                return; // No action needed.

            case self::MAX_FRAME_SIZE:
                if ($unpacked["value"] < 1 << 14 || $unpacked["value"] >= 1 << 24) {
                    throw new Http2ConnectionException("Invalid max frame size", self::PROTOCOL_ERROR);
                }

                $this->maxFrameSize = $unpacked["value"];
                return;

            case self::MAX_CONCURRENT_STREAMS:
                if ($unpacked["value"] >= 1 << 31) {
                    throw new Http2ConnectionException("Invalid concurrent streams value", self::PROTOCOL_ERROR);
                }

                $priorUsedStreams = $this->maxStreams - $this->remainingStreams;

                $this->maxStreams = $unpacked["value"];
                $this->remainingStreams = $this->maxStreams - $priorUsedStreams;
                return;

            case self::HEADER_TABLE_SIZE:
            case self::MAX_HEADER_LIST_SIZE:
                return; // @TODO Respect these settings from the server.

            default:
                return; // Unknown setting, ignore (6.5.2).
        }
    }

    private function sendBufferedData(): void
    {
        foreach ($this->streams as $id => $stream) {
            if ($this->clientWindow <= 0) {
                return;
            }

            if (!\strlen($stream->buffer) || $stream->clientWindow <= 0) {
                continue;
            }

            $this->writeBufferedData($id);
        }
    }
}
