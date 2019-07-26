<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Http\Client\Connection\Internal\Http2Stream;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\HPack;
use Amp\Http\Status;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Success;
use function Amp\call;

final class Http2Connection implements Connection
{
    private const PREFACE = "PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n";
    private const DEFAULT_MAX_FRAME_SIZE = 1 << 14;
    private const DEFAULT_WINDOW_SIZE = (1 << 16) - 1;

    private const MAX_INCREMENT = (1 << 31) - 1;

    private const HEADER_NAME_REGEX = '/^[\x21-\x40\x5b-\x7e]+$/';

    private const NOFLAG = "\x00";
    private const ACK = "\x01";
    private const END_STREAM = "\x01";
    private const END_HEADERS = "\x04";
    private const PADDED = "\x08";
    private const PRIORITY_FLAG = "\x20";

    private const DATA = "\x00";
    private const HEADERS = "\x01";
    private const PRIORITY = "\x02";
    private const RST_STREAM = "\x03";
    private const SETTINGS = "\x04";
    private const PUSH_PROMISE = "\x05";
    private const PING = "\x06";
    private const GOAWAY = "\x07";
    private const WINDOW_UPDATE = "\x08";
    private const CONTINUATION = "\x09";

    // Settings
    private const HEADER_TABLE_SIZE = 0x1; // 1 << 12
    private const ENABLE_PUSH = 0x2; // 1
    private const MAX_CONCURRENT_STREAMS = 0x3; // INF
    private const INITIAL_WINDOW_SIZE = 0x4; // 1 << 16 - 1
    private const MAX_FRAME_SIZE = 0x5; // 1 << 14
    private const MAX_HEADER_LIST_SIZE = 0x6; // INF

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

    /** @var Request[] Request objects indexed by stream IDs. */
    private $requests = [];

    /** @var Deferred[] */
    private $pendingRequests = [];

    /** @var Emitter[] */
    private $bodyEmitters = [];

    /** @var int Number of streams that may be opened. */
    private $remainingStreams = 1;

    /** @var HPack */
    private $table;

    /** @var Deferred|null */
    private $settingsDeferred;

    public function __construct(Socket $socket)
    {
        $this->table = new HPack;

        $this->socket = $socket;

        if ($this->socket->isClosed()) {
            $this->onClose = null;
            return;
        }

        $this->settingsDeferred = new Deferred;

        Promise\rethrow(new Coroutine($this->run()));
    }

    public function request(Request $request, ?CancellationToken $token = null): Promise
    {
        return call(function () use ($request, $token) {
            \assert($this->remainingStreams > 0);

            if ($this->settingsDeferred) {
                yield $this->settingsDeferred->promise();
            }

            // Remove defunct HTTP/1.x headers.
            $request = $request->withoutHeader('host')
                ->withoutHeader('connection');

            $id = $this->streamId += 2; // Client streams should be odd-numbered, starting at 1.
            $this->streams[$id] = new Http2Stream(
                self::DEFAULT_WINDOW_SIZE,
                $this->initialWindowSize,
                self::DEFAULT_MAX_HEADER_SIZE, // $request->getMaxHeaderSize()
                self::DEFAULT_MAX_BODY_SIZE // $request->getMaxBodySize()
            );
            --$this->remainingStreams;

            try {
                $this->requests[$id] = $request;
                $this->pendingRequests[$id] = $deferred = new Deferred;

                $this->socket->reference();

                $uri = $request->getUri();

                $path = $uri->getPath();
                if ($path === '') {
                    $path = '/';
                }

                $headers = [
                    ":authority" => [$uri->getAuthority()],
                    ":path" => [$path],
                    ":scheme" => [$uri->getScheme()],
                    ":method" => [$request->getMethod()],
                ];

                $headers = \array_merge($headers, $request->getHeaders());
                $headers = $this->table->encode($headers);

                if (\strlen($headers) > $this->maxFrameSize) {
                    $split = \str_split($headers, $this->maxFrameSize);
                    $headers = \array_shift($split);
                    yield $this->writeFrame($headers, self::HEADERS, self::NOFLAG, $id);

                    $headers = \array_pop($split);
                    foreach ($split as $msgPart) {
                        yield $this->writeFrame($msgPart, self::CONTINUATION, self::NOFLAG, $id);
                    }
                    yield $this->writeFrame($headers, self::CONTINUATION, self::END_HEADERS | self::END_STREAM, $id);
                } else {
                    yield $this->writeFrame($headers, self::HEADERS, self::END_HEADERS | self::END_STREAM, $id);
                }

                // yield $this->writeData("", $id, true);

                // Write body if needed. Will need to remove END_STREAM from header frame if there's a body.

                return yield $deferred->promise();
            } catch (StreamException $exception) {
                throw new SocketException('Socket disconnected prior to response completion');
            } finally {
                unset($this->requests[$id], $this->pendingRequests[$id]);
            }
        });
    }

    public function isBusy(): bool
    {
        return $this->remainingStreams > 0 || $this->socket->isClosed();
    }

    public function onClose(callable $onClose): void
    {
        if ($this->socket->isClosed()) {
            Promise\rethrow(call($onClose, $this));
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): Promise
    {
        $this->socket->close();

        if ($this->onClose !== null) {
            $onClose = $this->onClose;
            $this->onClose = null;

            foreach ($onClose as $callback) {
                Promise\rethrow(call($callback, $this));
            }
        }

        return new Success;
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

            // Settings frame disabling push promises
            yield $this->writeFrame(
                \pack(
                    "nNnNnNnNnN",
                    self::ENABLE_PUSH,
                    0,
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
           // ?
        } finally {
            $this->close();
        }
    }

    private function writeFrame(string $data, string $type, string $flags, int $stream = 0): Promise
    {
        $data = \substr(\pack("N", \strlen($data)), 1, 3) . $type . $flags . \pack("N", $stream) . $data;
        return $this->socket->write($data);
    }

    private function writeData(string $data, int $stream, bool $last): Promise
    {
        \assert(isset($this->streams[$stream]), "The stream was closed");

        $this->streams[$stream]->buffer .= $data;
        if ($last) {
            $this->streams[$stream]->state |= Http2Stream::LOCAL_CLOSED;
        }

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
                $stream->clientWindow -= $length;
                $stream->buffer = "";
            }

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

            $promise = $this->writeFrame(\substr($data, $off, $delta - $off), self::DATA, self::NOFLAG, $id);

            $stream->buffer = \substr($data, $delta);

            return $promise;
        }

        if ($stream->deferred === null) {
            $stream->deferred = new Deferred;
        }

        return $stream->deferred->promise();
    }

    private function releaseStream(int $id)
    {
        \assert(isset($this->streams[$id]), "Tried to release a non-existent stream");

        if (isset($this->bodyEmitters[$id])) {
            $this->bodyEmitters[$id]->complete();
        }

        unset($this->streams[$id], $this->bodyEmitters[$id]);
        if ($id & 1) { // Client-initiated stream.
            $this->remainingStreams++;
        }

        if (empty($this->pendingRequests)) {
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

        $buffer = yield;

        try {
            while (true) {
                while (\strlen($buffer) < 9) {
                    $buffer .= yield;
                }

                $length = \unpack("N", "\0" . \substr($buffer, 0, 3))[1];
                $frameCount++;
                $bytesReceived += $length;

                if ($length > self::DEFAULT_MAX_FRAME_SIZE) { // Do we want to allow increasing max frame size?
                    $error = self::FRAME_SIZE_ERROR;
                    goto connection_error;
                }

                $type = $buffer[3];
                $flags = $buffer[4];
                $id = \unpack("N", \substr($buffer, 5, 4))[1];

                // the highest bit must be zero... but RFC does not specify what should happen when it is set to 1?
                /*if ($id < 0) {
                    $id = ~$id;
                }*/

                $buffer = \substr($buffer, 9);

                switch ($type) {
                    case self::DATA:
                        if ($id === 0) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        if (!isset($this->streams[$id])) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        $stream = $this->streams[$id];

                        if ($stream->headers !== null) {
                            $error = self::PROTOCOL_ERROR;
                            goto stream_error;
                        }

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            $error = self::PROTOCOL_ERROR;
                            goto stream_error;
                        }

                        if (($flags & self::PADDED) !== "\0") {
                            if ($buffer === "") {
                                $buffer = yield;
                            }
                            $padding = \ord($buffer);
                            $buffer = \substr($buffer, 1);
                            $length--;

                            if ($padding > $length) {
                                $error = self::PROTOCOL_ERROR;
                                goto connection_error;
                            }
                        } else {
                            $padding = 0;
                        }

                        $this->serverWindow -= $length;
                        $stream->serverWindow -= $length;
                        $stream->received += $length;

                        if ($stream->received >= $stream->maxBodySize && ($flags & self::END_STREAM) === "\0") {
                            $error = self::CANCEL;
                            goto stream_error;
                        }

                        if ($stream->serverWindow <= 0 && ($increment = $stream->maxBodySize - $stream->received)) {
                            if ($increment > self::MAX_INCREMENT) {
                                $increment = self::MAX_INCREMENT;
                            }

                            $stream->serverWindow += $increment;

                            $this->writeFrame(\pack("N", $increment), self::WINDOW_UPDATE, self::NOFLAG, $id);
                        }

                        if ($this->serverWindow <= 0) {
                            $increment = \max($stream->serverWindow, $stream->maxBodySize);
                            $this->serverWindow += $increment;

                            $this->writeFrame(\pack("N", $increment), self::WINDOW_UPDATE, self::NOFLAG);
                        }

                        while (\strlen($buffer) < $length) {
                            /* it is fine to just .= the $body as $length < 2^14 */
                            $buffer .= yield;
                        }

                        $body = \substr($buffer, 0, $length - $padding);
                        $buffer = \substr($buffer, $length);
                        if ($body !== "") {
                            yield $this->bodyEmitters[$id]->emit($body);
                        }

                        if (($flags & self::END_STREAM) !== "\0") {
                            $stream->state |= Http2Stream::REMOTE_CLOSED;

                            if (!isset($this->bodyEmitters[$id])) {
                                continue 2; // Stream closed after emitting body fragment.
                            }

                            $emitter = $this->bodyEmitters[$id];
                            unset($this->bodyEmitters[$id]);
                            $emitter->complete();

                            $this->releaseStream($id);
                        }

                        continue 2;

                    case self::HEADERS:
                        if (!isset($this->streams[$id], $this->requests[$id])) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        $stream = $this->streams[$id];

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            $error = self::STREAM_CLOSED;
                            goto stream_error;
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

                            /* Not needed until priority is handled.
                            $dependency = unpack("N", $buffer)[1];
                            if ($dependency < 0) {
                                $dependency = ~$dependency;
                                $exclusive = true;
                            } else {
                                $exclusive = false;
                            }

                            $weight = $buffer[4];
                            */

                            $buffer = \substr($buffer, 5);
                            $length -= 5;
                        }

                        if ($padding >= $length) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        if ($length > $maxHeaderSize) {
                            $error = self::ENHANCE_YOUR_CALM;
                            goto stream_error;
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

                        continue 2;

                    case self::PRIORITY:
                        if ($length != 5) {
                            $error = self::FRAME_SIZE_ERROR;
                            goto connection_error;
                        }

                        if ($id === 0) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        while (\strlen($buffer) < 5) {
                            $buffer .= yield;
                        }

                        /* @TODO PRIORITY frames values not used.
                         *
                         * $dependency = unpack("N", $buffer);
                         *
                         * if ($dependency < 0) {
                         *     $dependency = ~$dependency;
                         *     $exclusive = true;
                         * } else {
                         *     $exclusive = false;
                         * }
                         *
                         * if ($dependency == 0) {
                         *     $error = self::PROTOCOL_ERROR;
                         *     goto connection_error;
                         * }
                         *
                         * $weight = $buffer[4];
                         */

                        $buffer = \substr($buffer, 5);
                        continue 2;

                    case self::RST_STREAM:
                        if ($length !== 4) {
                            $error = self::FRAME_SIZE_ERROR;
                            goto connection_error;
                        }

                        if ($id === 0) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        while (\strlen($buffer) < 4) {
                            $buffer .= yield;
                        }

                        $error = \unpack("N", $buffer)[1];

                        if (isset($this->bodyEmitters[$id])) {
                            $exception = new SocketException("Server ended stream", self::STREAM_CLOSED);
                            $emitter = $this->bodyEmitters[$id];
                            unset($this->bodyEmitters[$id]);
                            $emitter->fail($exception);
                        }

                        if (isset($this->streams[$id])) {
                            $this->releaseStream($id);
                        }

                        $buffer = \substr($buffer, 4);
                        continue 2;

                    case self::SETTINGS:
                        if ($id !== 0) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        if (($flags & self::ACK) !== "\0") {
                            if ($length) {
                                $error = self::PROTOCOL_ERROR;
                                goto connection_error;
                            }

                            // Got ACK
                            continue 2;
                        }

                        if ($length % 6 !== 0) {
                            $error = self::FRAME_SIZE_ERROR;
                            goto connection_error;
                        }

                        if ($length > 60) {
                            // Even with room for a few future options, sending that a big SETTINGS frame is just about
                            // wasting our processing time. I hereby declare this a protocol error.
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        while ($length > 0) {
                            while (\strlen($buffer) < 6) {
                                $buffer .= yield;
                            }

                            if ($error = $this->updateSetting($buffer)) {
                                goto connection_error;
                            }

                            $buffer = \substr($buffer, 6);
                            $length -= 6;
                        }

                        $this->writeFrame("", self::SETTINGS, self::ACK);

                        if ($this->settingsDeferred) {
                            $deferred = $this->settingsDeferred;
                            $this->settingsDeferred = null;
                            $deferred->resolve();
                        }

                        continue 2;

                    // PUSH_PROMISE sent by client is a PROTOCOL_ERROR (just like undefined frame types)

                    case self::PING:
                        if ($length !== 8) {
                            $error = self::FRAME_SIZE_ERROR;
                            goto connection_error;
                        }

                        if ($id !== 0) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
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
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        $lastId = \unpack("N", $buffer)[1];
                        // the highest bit must be zero... but RFC does not specify what should happen when it is set to 1?
                        if ($lastId < 0) {
                            $lastId = ~$lastId;
                        }
                        $error = \unpack("N", \substr($buffer, 4, 4))[1];

                        $buffer = \substr($buffer, 8);
                        $length -= 8;

                        while (\strlen($buffer) < $length) {
                            $buffer .= yield;
                        }

                        if ($error !== 0) {
                            // @TODO Log error, since the server says we made a boo-boo.
                        }

                        $this->close();

                        return;

                    case self::WINDOW_UPDATE:
                        if ($length !== 4) {
                            $error = self::FRAME_SIZE_ERROR;
                            goto connection_error;
                        }

                        while (\strlen($buffer) < 4) {
                            $buffer .= yield;
                        }

                        if ($buffer === "\0\0\0\0") {
                            $error = self::PROTOCOL_ERROR;
                            if ($id) {
                                goto stream_error;
                            }
                            goto connection_error;
                        }

                        $windowSize = \unpack("N", $buffer)[1];
                        $buffer = \substr($buffer, 4);

                        if ($id) {
                            // May receive a WINDOW_UPDATE frame for a closed stream.
                            if (isset($this->streams[$id])) {
                                $this->streams[$id]->clientWindow += $windowSize;

                                if ($this->streams[$id]->buffer !== "") {
                                    $this->writeBufferedData($id);
                                }
                            }

                            continue 2;
                        }

                        $this->clientWindow += $windowSize;

                        foreach ($this->streams as $id => $stream) {
                            if ($stream->buffer === "") {
                                continue;
                            }

                            $this->writeBufferedData($id);

                            if ($this->clientWindow === 0) {
                                break;
                            }
                        }

                        continue 2;

                    case self::CONTINUATION:
                        if (!isset($this->streams[$id], $this->requests[$id])) {
                            $error = self::PROTOCOL_ERROR;
                            goto connection_error;
                        }

                        $stream = $this->streams[$id];

                        if ($stream->headers === null) {
                            $error = self::PROTOCOL_ERROR;
                            goto stream_error;
                        }

                        if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                            $error = self::STREAM_CLOSED;
                            goto stream_error;
                        }

                        if ($length > $maxHeaderSize - \strlen($stream->headers)) {
                            $error = self::ENHANCE_YOUR_CALM;
                            goto stream_error;
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
                            goto parse_headers;
                        }

                        continue 2;

                    default:
                        $error = self::PROTOCOL_ERROR;
                        goto connection_error;
                }

                parse_headers: {
                    $decoded = $this->table->decode($stream->headers, $maxHeaderSize);
                    $stream->headers = null;

                    if ($decoded === null) {
                        $error = self::COMPRESSION_ERROR;
                        goto stream_error;
                    }

                    $headers = [];
                    foreach ($decoded as list($name, $value)) {
                        if (!\preg_match(self::HEADER_NAME_REGEX, $name)) {
                            $error = self::PROTOCOL_ERROR;
                            goto stream_error;
                        }

                        $headers[$name][] = $value;
                    }

                    if ($stream->state & Http2Stream::RESERVED) {
                        $error = self::PROTOCOL_ERROR;
                        goto stream_error;
                    }

                    $stream->state |= Http2Stream::RESERVED;

                    if (!isset($headers[":status"][0])) {
                        $error = self::PROTOCOL_ERROR;
                        goto stream_error;
                    }

                    $status = $headers[":status"][0];

                    if ($stream->state & Http2Stream::REMOTE_CLOSED) {
                        $this->pendingRequests[$id]->resolve(new Response(
                            "2.0",
                            $status,
                            Status::getReason($status),
                            $headers,
                            new InMemoryStream,
                            $this->requests[$id]
                        ));

                        continue;
                    }

                    $this->bodyEmitters[$id] = new Emitter;

                    if ($this->serverWindow <= $stream->maxBodySize >> 1) {
                        $increment = $stream->maxBodySize - $this->serverWindow;
                        $this->serverWindow = $stream->maxBodySize;
                        $this->writeFrame(\pack("N", $increment), self::WINDOW_UPDATE, self::NOFLAG);
                    }

                    $this->pendingRequests[$id]->resolve(new Response(
                        "2.0",
                        $status,
                        Status::getReason($status),
                        $headers,
                        new IteratorStream($this->bodyEmitters[$id]->iterate()),
                        $this->requests[$id]
                    ));

                    continue;
                }

                stream_error: {
                    $this->writeFrame(\pack("N", $error), self::RST_STREAM, self::NOFLAG, $id);

                    if (isset($this->bodyEmitters[$id])) {
                        $exception = new SocketException("Stream error", self::CANCEL);
                        $this->bodyEmitters[$id]->fail($exception);
                    }

                    if (isset($this->streams[$id])) {
                        $this->releaseStream($id);
                    }

                    // consume whole frame to be able to continue this connection
                    $length -= \strlen($buffer);
                    while ($length > 0) {
                        $buffer = yield;
                        $length -= \strlen($buffer);
                    }
                    $buffer = \substr($buffer, \strlen($buffer) + $length);

                    continue;
                }
            }
        } finally {
            if (!empty($this->bodyEmitters)) {
                $exception = new SocketException("Server disconnected", self::CANCEL);

                foreach ($this->bodyEmitters as $id => $emitter) {
                    unset($this->bodyEmitters[$id]);
                    $emitter->fail($exception);
                }
            }
        }

        connection_error: {
            $this->shutdown(null, $error ?? self::PROTOCOL_ERROR);
        }
    }

    /**
     * @param int|null $lastId ID of last processed frame. Null to use the last opened frame ID or 0 if no frames have
     *                         been opened.
     *
     * @return Promise
     */
    public function shutdown(int $lastId = null, int $reason = self::GRACEFUL_SHUTDOWN): Promise
    {
        return call(function () use ($lastId, $reason) {
            $promises = [];
            foreach ($this->streams as $id => $stream) {
                if ($lastId && $id > $lastId) {
                    break;
                }

                if ($stream->pendingResponse) {
                    $promises[] = $stream->pendingResponse;
                }
            }

            $lastId = $lastId ?? ($id ?? 0);

            yield $this->writeFrame(\pack("NN", $lastId, $reason), self::GOAWAY, self::NOFLAG);

            yield $promises;

            $promises = [];
            foreach ($this->streams as $id => $stream) {
                if ($lastId && $id > $lastId) {
                    break;
                }

                if ($stream->pendingWrite) {
                    $promises[] = $stream->pendingWrite;
                }
            }

            yield $promises;
        });
    }


    /**
     * @param string $buffer Entire settings frame payload. Only the first 6 bytes are examined.
     *
     * @return int Error code, or 0 if the setting was valid or unknown.
     */
    private function updateSetting(string $buffer): int
    {
        $unpacked = \unpack("nsetting/Nvalue", $buffer);

        if ($unpacked["value"] < 0) {
            return self::PROTOCOL_ERROR;
        }

        switch ($unpacked["setting"]) {
            case self::INITIAL_WINDOW_SIZE:
                if ($unpacked["value"] >= 1 << 31) {
                    return self::FLOW_CONTROL_ERROR;
                }

                $this->initialWindowSize = $unpacked["value"];
                return 0;

            case self::ENABLE_PUSH:
                return self::PROTOCOL_ERROR; // Server should not enable push.

            case self::MAX_FRAME_SIZE:
                if ($unpacked["value"] < 1 << 14 || $unpacked["value"] >= 1 << 24) {
                    return self::PROTOCOL_ERROR;
                }

                $this->maxFrameSize = $unpacked["value"];
                return 0;

            case self::MAX_CONCURRENT_STREAMS:
                if ($unpacked["value"] >= 1 << 31) {
                    return self::FLOW_CONTROL_ERROR;
                }

                $this->remainingStreams = $unpacked["value"] - \count($this->streams);
                return 0;

            case self::HEADER_TABLE_SIZE:
            case self::MAX_HEADER_LIST_SIZE:
                return 0; // @TODO Respect these settings from the server.

            default:
                return 0; // Unknown setting, ignore (6.5.2).
        }
    }
}
