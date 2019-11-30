<?php

/** @noinspection PhpUnusedPrivateFieldInspection */

/** @noinspection PhpDocSignatureInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection\Internal;

use Amp\Http\Client\Connection\Http2ConnectionException;
use Amp\Http\Client\Connection\Http2StreamException;
use Amp\Http\HPack;

/** @internal */
final class Http2Parser
{
    private const DEFAULT_MAX_FRAME_SIZE = 1 << 14;

    private const HEADER_NAME_REGEX = '/^[\x21-\x40\x5b-\x7e]+$/';

    private const KNOWN_RESPONSE_PSEUDO_HEADERS = [
        ":status" => true,
    ];

    private const KNOWN_REQUEST_PSEUDO_HEADERS = [
        ":method" => true,
        ":authority" => true,
        ":path" => true,
        ":scheme" => true,
    ];

    // SETTINGS Flags - https://http2.github.io/http2-spec/#rfc.section.6.5
    public const ACK = 0x01;

    // HEADERS Flags - https://http2.github.io/http2-spec/#rfc.section.6.2
    public const END_STREAM = 0x01;
    public const END_HEADERS = 0x04;
    public const PADDED = 0x08;
    public const PRIORITY_FLAG = 0x20;

    // Frame Types - https://http2.github.io/http2-spec/#rfc.section.11.2
    public const DATA = 0x00;
    public const HEADERS = 0x01;
    public const PRIORITY = 0x02;
    public const RST_STREAM = 0x03;
    public const SETTINGS = 0x04;
    public const PUSH_PROMISE = 0x05;
    public const PING = 0x06;
    public const GOAWAY = 0x07;
    public const WINDOW_UPDATE = 0x08;
    public const CONTINUATION = 0x09;

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

    /** @var string */
    private $buffer = '';

    /** @var int */
    private $bufferOffset = 0;

    /** @var int */
    private $headerSizeLimit = self::DEFAULT_MAX_FRAME_SIZE; // Should be configurable?

    /** @var bool */
    private $continuationExpected = false;

    /** @var int */
    private $headerFrameType = 0;

    /** @var string */
    private $headerBuffer = '';

    /** @var int */
    private $headerStream = 0;

    /** @var HPack */
    private $hpack;

    /** @var Http2FrameProcessor */
    private $handler;

    public function __construct(Http2FrameProcessor $handler)
    {
        $this->hpack = new HPack;
        $this->handler = $handler;
    }

    public function parse(): \Generator
    {
        $this->buffer = yield;

        while (true) {
            $frameHeader = yield from $this->consume(9);

            [
                'length' => $frameLength,
                'type' => $frameType,
                'flags' => $frameFlags,
                'id' => $streamId,
            ] = \unpack('Nlength/ctype/cflags/Nid', "\0" . $frameHeader);

            $streamId &= 0x7fffffff;

            $frameBuffer = $frameLength === 0 ? '' : yield from $this->consume($frameLength);

            try {
                // Do we want to allow increasing the maximum frame size?
                if ($frameLength > self::DEFAULT_MAX_FRAME_SIZE) {
                    throw new Http2ConnectionException("Frame size limit exceeded", self::FRAME_SIZE_ERROR);
                }

                if ($this->continuationExpected && $frameType !== self::CONTINUATION) {
                    throw new Http2ConnectionException("Expected continuation frame", self::PROTOCOL_ERROR);
                }

                switch ($frameType) {
                    case self::DATA:
                        $this->parseDataFrame($frameBuffer, $frameLength, $frameFlags, $streamId);
                        break;

                    case self::PUSH_PROMISE:
                        $this->parsePushPromise($frameBuffer, $frameLength, $frameFlags, $streamId);
                        break;

                    case self::HEADERS:
                        $this->parseHeaders($frameBuffer, $frameLength, $frameFlags, $streamId);
                        break;

                    case self::PRIORITY:
                        $this->parsePriorityFrame($frameBuffer, $frameLength, $streamId);
                        break;

                    case self::RST_STREAM:
                        $this->parseStreamReset($frameBuffer, $frameLength, $streamId);
                        break;

                    case self::SETTINGS:
                        $this->parseSettings($frameBuffer, $frameLength, $frameFlags, $streamId);
                        break;

                    case self::PING:
                        $this->parsePing($frameBuffer, $frameLength, $frameFlags, $streamId);
                        break;

                    case self::GOAWAY:
                        $this->parseGoAway($frameBuffer, $frameLength, $streamId);
                        return;

                    case self::WINDOW_UPDATE:
                        $this->parseWindowUpdate($frameBuffer, $frameLength, $streamId);
                        break;

                    case self::CONTINUATION:
                        $this->parseContinuation($frameBuffer, $frameFlags, $streamId);
                        break;

                    default: // Ignore and discard unknown frame per spec
                        break;
                }
            } catch (Http2StreamException $exception) {
                $this->handler->handleStreamException($exception);
            } catch (Http2ConnectionException $exception) {
                $this->handler->handleconnectionException($exception);

                throw $exception;
            }
        }
    }

    private function consume(int $bytes): \Generator
    {
        $bufferEnd = $this->bufferOffset + $bytes;

        while (\strlen($this->buffer) < $bufferEnd) {
            $this->buffer .= yield;
        }

        $consumed = \substr($this->buffer, $this->bufferOffset, $bytes);

        if ($bufferEnd > 2048) {
            $this->buffer = \substr($this->buffer, $bufferEnd);
            $this->bufferOffset = 0;
        } else {
            $this->bufferOffset += $bytes;
        }

        return $consumed;
    }

    private function parseDataFrame(string $frameBuffer, int $frameLength, int $frameFlags, int $streamId): void
    {
        $isPadded = $frameFlags & self::PADDED;

        $headerLength = $isPadded ? 1 : 0;

        if ($frameLength < $headerLength) {
            $this->throwInvalidFrameSizeError();
        }

        $header = $headerLength === 0 ? '' : \substr($frameBuffer, 0, $headerLength);

        $padding = $isPadded ? \ord($header[0]) : 0;

        if ($streamId === 0) {
            $this->throwInvalidZeroStreamIdError();
        }

        if ($frameLength - $headerLength - $padding < 0) {
            $this->throwInvalidPaddingError();
        }

        $data = \substr($frameBuffer, $headerLength, $frameLength - $headerLength - $padding);

        $this->handler->handleData($streamId, $data);

        if ($frameFlags & self::END_STREAM) {
            $this->handler->handleStreamEnd($streamId);
        }
    }

    /** @see https://http2.github.io/http2-spec/#rfc.section.6.6 */
    private function parsePushPromise(string $frameBuffer, int $frameLength, int $frameFlags, int $streamId): void
    {
        $isPadded = $frameFlags & self::PADDED;

        $headerLength = $isPadded ? 5 : 4;

        if ($frameLength < $headerLength) {
            $this->throwInvalidFrameSizeError();
        }

        $header = \substr($frameBuffer, 0, $headerLength);

        $padding = $isPadded ? \ord($header[0]) : 0;

        $pushId = \unpack("N", $header)[1] & 0x7fffffff;

        if ($frameLength - $headerLength - $padding < 0) {
            $this->throwInvalidPaddingError();
        }

        $this->headerFrameType = self::PUSH_PROMISE;

        $this->pushHeaderBlockFragment(
            $pushId,
            \substr($frameBuffer, $headerLength, $frameLength - $headerLength - $padding)
        );

        if ($frameFlags & self::END_HEADERS) {
            $this->continuationExpected = false;

            [$pseudo, $headers] = $this->parseHeaderBuffer(self::KNOWN_REQUEST_PSEUDO_HEADERS);

            $this->handler->handlePushPromise($streamId, $pushId, $pseudo, $headers);
        } else {
            $this->continuationExpected = true;
        }
    }

    private function parseHeaderBuffer(array $knownHeaders): array
    {
        \assert($this->headerStream !== 0);
        \assert($this->headerBuffer !== '');

        $decoded = $this->hpack->decode($this->headerBuffer, $this->headerSizeLimit);

        if ($decoded === null) {
            throw new Http2ConnectionException("Compression error in headers", self::COMPRESSION_ERROR);
        }

        $headers = [];
        $pseudo = [];

        foreach ($decoded as [$name, $value]) {
            if (!\preg_match(self::HEADER_NAME_REGEX, $name)) {
                throw new Http2StreamException("Invalid header field name", $this->headerStream, self::PROTOCOL_ERROR);
            }

            if ($name[0] === ':') {
                if (!empty($headers) || !isset($knownHeaders[$name]) || isset($pseudo[$name])) {
                    throw new Http2ConnectionException(
                        "Unknown or invalid pseudo header",
                        self::PROTOCOL_ERROR
                    );
                }

                $pseudo[$name] = $value;
                continue;
            }

            $headers[$name][] = $value;
        }

        $this->headerBuffer = '';
        $this->headerStream = 0;

        return [$pseudo, $headers];
    }

    private function pushHeaderBlockFragment(int $streamId, string $buffer): void
    {
        if ($this->headerStream !== 0 && $this->headerStream !== $streamId) {
            throw new Http2ConnectionException(
                "Expected CONTINUATION frame for stream ID " . $this->headerStream,
                self::PROTOCOL_ERROR
            );
        }

        $this->headerStream = $streamId;
        $this->headerBuffer .= $buffer;
    }

    /** @see https://http2.github.io/http2-spec/#HEADERS */
    private function parseHeaders(string $frameBuffer, int $frameLength, int $frameFlags, int $streamId): void
    {
        $headerLength = 0;
        $isPadded = $frameFlags & self::PADDED;
        $isPriority = $frameFlags & self::PRIORITY_FLAG;

        if ($isPadded) {
            $headerLength++;
        }

        if ($isPriority) {
            $headerLength += 5;
        }

        if ($frameLength < $headerLength) {
            $this->throwInvalidFrameSizeError();
        }

        $header = \substr($frameBuffer, 0, $headerLength);

        $padding = $isPadded ? \ord($header[0]) : 0;

        if ($isPriority) {
            ['parent' => $parent, 'weight' => $weight] = \unpack("Nparent/cweight", $header, $isPadded ? 1 : 0);

            $parent &= 0x7fffffff;

            if ($parent === 0) {
                $this->throwInvalidZeroStreamIdError();
            }

            if ($parent === $streamId) {
                $this->throwInvalidRecursiveDependency($streamId);
            }

            $this->handler->handlePriority($streamId, $parent, $weight);
        }

        if ($frameLength - $headerLength - $padding < 0) {
            $this->throwInvalidPaddingError();
        }

        $this->headerFrameType = self::HEADERS;

        $this->pushHeaderBlockFragment(
            $streamId,
            \substr($frameBuffer, $headerLength, $frameLength - $headerLength - $padding)
        );

        if ($frameFlags & self::END_HEADERS) {
            $this->continuationExpected = false;

            $headersTooLarge = \strlen($this->headerBuffer) > $this->headerSizeLimit;

            [$pseudo, $headers] = $this->parseHeaderBuffer(self::KNOWN_RESPONSE_PSEUDO_HEADERS);

            // This must happen after the parsing, otherwise we loose the connection state and must close the whole
            // connection, which is not what we want here…
            if ($headersTooLarge) {
                throw new Http2StreamException(
                    "Headers exceed maximum configured size of {$this->headerSizeLimit} bytes",
                    $streamId,
                    self::ENHANCE_YOUR_CALM
                );
            }

            $this->handler->handleHeaders($streamId, $pseudo, $headers);
        } else {
            $this->continuationExpected = true;
        }

        if ($frameFlags & self::END_STREAM) {
            $this->handler->handleStreamEnd($streamId);
        }
    }

    private function parsePriorityFrame(string $frameBuffer, int $frameLength, int $streamId): void
    {
        if ($frameLength !== 5) {
            $this->throwInvalidFrameSizeError();
        }

        ['parent' => $parent, 'weight' => $weight] = \unpack("Nparent/cweight", $frameBuffer);

        if ($exclusive = ($parent & 0x80000000)) {
            $parent &= 0x7fffffff;
        }

        if ($parent === 0) {
            $this->throwInvalidZeroStreamIdError();
        }

        if ($parent === $streamId) {
            $this->throwInvalidRecursiveDependency($streamId);
        }

        $this->handler->handlePriority($streamId, $parent, $weight);
    }

    private function parseStreamReset(string $frameBuffer, int $frameLength, int $streamId): void
    {
        if ($frameLength !== 4) {
            $this->throwInvalidFrameSizeError();
        }

        if ($streamId === 0) {
            $this->throwInvalidZeroStreamIdError();
        }

        $errorCode = \unpack('N', $frameBuffer)[1];

        $this->handler->handleStreamReset($streamId, $errorCode);
    }

    private function parseSettings(string $frameBuffer, int $frameLength, int $frameFlags, int $streamId): void
    {
        if ($streamId !== 0) {
            $this->throwInvalidNonZeroStreamIdError();
        }

        if ($frameFlags & self::ACK) {
            if ($frameLength) {
                $this->throwInvalidFrameSizeError();
            }

            return; // Got ACK, nothing to do
        }

        if ($frameLength % 6 !== 0) {
            $this->throwInvalidFrameSizeError();
        }

        if ($frameLength > 60) {
            // Even with room for a few future options, sending that a big SETTINGS frame is just about
            // wasting our processing time. We declare this a protocol error.
            throw new Http2ConnectionException("Excessive SETTINGS frame", self::PROTOCOL_ERROR);
        }

        $settings = [];

        while ($frameLength > 0) {
            ['key' => $key, 'value' => $value] = \unpack("nkey/Nvalue", $frameBuffer);

            if ($value < 0) {
                throw new Http2ConnectionException(
                    "Invalid setting: {$value}",
                    self::PROTOCOL_ERROR
                );
            }

            $settings[$key] = $value;

            $frameBuffer = \substr($frameBuffer, 6);
            $frameLength -= 6;
        }

        $this->handler->handleSettings($settings);
    }

    /** @see https://http2.github.io/http2-spec/#rfc.section.6.7 */
    private function parsePing(string $frameBuffer, int $frameLength, int $frameFlags, int $streamId): void
    {
        if ($frameLength !== 8) {
            $this->throwInvalidFrameSizeError();
        }

        if ($streamId !== 0) {
            $this->throwInvalidNonZeroStreamIdError();
        }

        if ($frameFlags & self::ACK) {
            $this->handler->handlePong($frameBuffer);
        } else {
            $this->handler->handlePing($frameBuffer);
        }
    }

    /** @see https://http2.github.io/http2-spec/#rfc.section.6.8 */
    private function parseGoAway(string $frameBuffer, int $frameLength, int $streamId): void
    {
        if ($frameLength < 8) {
            $this->throwInvalidFrameSizeError();
        }

        if ($streamId !== 0) {
            $this->throwInvalidNonZeroStreamIdError();
        }

        ['last' => $lastId, 'error' => $error] = \unpack("Nlast/Nerror", $frameBuffer);

        $this->handler->handleShutdown($lastId & 0x7fffffff, $error);
    }

    /** @see https://http2.github.io/http2-spec/#rfc.section.6.9 */
    private function parseWindowUpdate(string $frameBuffer, int $frameLength, int $streamId): void
    {
        if ($frameLength !== 4) {
            $this->throwInvalidFrameSizeError();
        }

        $windowSize = \unpack('N', $frameBuffer)[1];

        if ($windowSize === 0) {
            if ($streamId) {
                throw new Http2StreamException(
                    "Invalid zero window update value",
                    $streamId,
                    self::PROTOCOL_ERROR
                );
            }

            throw new Http2ConnectionException("Invalid zero window update value", self::PROTOCOL_ERROR);
        }

        if ($streamId) {
            $this->handler->handleStreamWindowIncrement($streamId, $windowSize);
        } else {
            $this->handler->handleConnectionWindowIncrement($windowSize);
        }
    }

    /** @see https://http2.github.io/http2-spec/#rfc.section.6.10 */
    private function parseContinuation(string $frameBuffer, int $frameFlags, int $streamId): void
    {
        $this->pushHeaderBlockFragment($streamId, $frameBuffer);

        if ($frameFlags & self::END_HEADERS) {
            $this->continuationExpected = false;

            $isPush = $this->headerFrameType === self::PUSH_PROMISE;
            $knownHeaders = $isPush ? self::KNOWN_REQUEST_PSEUDO_HEADERS : self::KNOWN_RESPONSE_PSEUDO_HEADERS;

            $pushId = $this->headerStream;

            [$pseudo, $headers] = $this->parseHeaderBuffer($knownHeaders);

            if ($isPush) {
                $this->handler->handlePushPromise($streamId, $pushId, $pseudo, $headers);
            } else {
                $this->handler->handleHeaders($streamId, $pseudo, $headers);
            }
        }
    }

    private function throwInvalidFrameSizeError(): void
    {
        throw new Http2ConnectionException("Invalid frame length", self::PROTOCOL_ERROR);
    }

    private function throwInvalidRecursiveDependency(int $streamId): void
    {
        throw new Http2ConnectionException(
            "Invalid recursive dependency for stream {$streamId}",
            self::PROTOCOL_ERROR
        );
    }

    private function throwInvalidPaddingError(): void
    {
        throw new Http2ConnectionException("Padding greater than length", self::PROTOCOL_ERROR);
    }

    private function throwInvalidZeroStreamIdError(): void
    {
        throw new Http2ConnectionException("Invalid zero stream ID", self::PROTOCOL_ERROR);
    }

    private function throwInvalidNonZeroStreamIdError(): void
    {
        throw new Http2ConnectionException("Invalid non-zero stream ID", self::PROTOCOL_ERROR);
    }
}
