<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\ByteStream\InMemoryStream;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Http\Status;

/** @internal */
final class Http1Parser
{
    use ForbidSerialization;
    use ForbidCloning;

    private const STATUS_LINE_PATTERN = "#^
        HTTP/(?P<protocol>\d+\.\d+)[\x20\x09]+
        (?P<status>[1-9]\d\d)[\x20\x09]*
        (?P<reason>[^\x01-\x08\x10-\x19]*)
    $#ix";

    public const AWAITING_HEADERS = 0;
    public const BODY_IDENTITY = 1;
    public const BODY_IDENTITY_EOF = 2;
    public const BODY_CHUNKS = 3;
    public const TRAILERS_START = 4;
    public const TRAILERS = 5;

    /** @var int */
    private $state = self::AWAITING_HEADERS;

    /** @var string */
    private $buffer = '';

    /** @var string|null */
    private $protocol;

    /** @var int|null */
    private $statusCode;

    /** @var string|null */
    private $statusReason;

    /** @var string[][] */
    private $headers = [];

    /** @var int|null */
    private $remainingBodyBytes;

    /** @var int */
    private $bodyBytesConsumed = 0;

    /** @var bool */
    private $chunkedEncoding = false;

    /** @var int|null */
    private $chunkLengthRemaining;

    /** @var bool */
    private $complete = false;

    /** @var Request */
    private $request;

    /** @var int */
    private $maxHeaderBytes;

    /** @var int */
    private $maxBodyBytes;

    /** @var callable */
    private $bodyDataCallback;

    /** @var callable */
    private $trailersCallback;

    public function __construct(Request $request, callable $bodyDataCallback, callable $trailersCallback)
    {
        $this->request = $request;
        $this->bodyDataCallback = $bodyDataCallback;
        $this->trailersCallback = $trailersCallback;
        $this->maxHeaderBytes = $request->getHeaderSizeLimit();
        $this->maxBodyBytes = $request->getBodySizeLimit();
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function buffer(string $data): void
    {
        $this->buffer .= $data;
    }

    /**
     * @param string|null $data
     *
     * @return Response|null
     *
     * @throws ParseException
     */
    public function parse(string $data = null): ?Response
    {
        if ($data !== null) {
            $this->buffer .= $data;
        }

        if ($this->buffer === '') {
            return null;
        }

        if ($this->complete) {
            throw new ParseException('Can\'t continue parsing, response is already complete', Status::BAD_REQUEST);
        }

        switch ($this->state) {
            case self::AWAITING_HEADERS:
                goto headers;
            case self::BODY_IDENTITY:
                goto body_identity;
            case self::BODY_IDENTITY_EOF:
                goto body_identity_eof;
            case self::BODY_CHUNKS:
                goto body_chunks;
            case self::TRAILERS_START:
                goto trailers_start;
            case self::TRAILERS:
                goto trailers;
        }

        headers:
        {
            $startLineAndHeaders = $this->shiftHeadersFromBuffer();
            if ($startLineAndHeaders === null) {
                return null;
            }

            $startLineEndPos = \strpos($startLineAndHeaders, "\r\n");

            \assert($startLineEndPos !== false);

            $startLine = \substr($startLineAndHeaders, 0, $startLineEndPos);
            $rawHeaders = \substr($startLineAndHeaders, $startLineEndPos + 2);

            if (\preg_match(self::STATUS_LINE_PATTERN, $startLine, $match)) {
                $this->protocol = $match['protocol'];
                $this->statusCode = (int) $match['status'];
                $this->statusReason = \trim($match['reason']);
            } else {
                throw new ParseException('Invalid status line: ' . $startLine, Status::BAD_REQUEST);
            }

            if ($rawHeaders !== '') {
                $this->headers = $this->parseRawHeaders($rawHeaders);
            } else {
                $this->headers = [];
            }

            $requestMethod = $this->request->getMethod();
            $skipBody = $this->statusCode < Status::OK || $this->statusCode === Status::NOT_MODIFIED || $this->statusCode === Status::NO_CONTENT
                || $requestMethod === 'HEAD' || $requestMethod === 'CONNECT';

            if ($skipBody) {
                $this->complete = true;
            } elseif ($this->chunkedEncoding) {
                $this->state = self::BODY_CHUNKS;
            } elseif ($this->remainingBodyBytes === null) {
                $this->state = self::BODY_IDENTITY_EOF;
            } elseif ($this->remainingBodyBytes > 0) {
                $this->state = self::BODY_IDENTITY;
            } else {
                $this->complete = true;
            }

            $response = new Response($this->protocol, $this->statusCode, $this->statusReason, [], new InMemoryStream, $this->request);
            foreach ($this->headers as [$key, $value]) {
                $response->addHeader($key, $value);
            }

            return $response;
        }

        body_identity:
        {
            $bufferDataSize = \strlen($this->buffer);

            if ($bufferDataSize <= $this->remainingBodyBytes) {
                $chunk = $this->buffer;
                $this->buffer = '';
                $this->remainingBodyBytes -= $bufferDataSize;
                $this->addToBody($chunk);

                if ($this->remainingBodyBytes === 0) {
                    $this->complete = true;
                }

                return null;
            }

            $bodyData = \substr($this->buffer, 0, $this->remainingBodyBytes);
            $this->addToBody($bodyData);
            $this->buffer = \substr($this->buffer, $this->remainingBodyBytes);
            $this->remainingBodyBytes = 0;

            goto complete;
        }

        body_identity_eof:
        {
            $this->addToBody($this->buffer);
            $this->buffer = '';
            return null;
        }

        body_chunks:
        {
            if ($this->parseChunkedBody()) {
                $this->state = self::TRAILERS_START;
                goto trailers_start;
            }

            return null;
        }

        trailers_start:
        {
            $firstTwoBytes = \substr($this->buffer, 0, 2);

            if ($firstTwoBytes === "" || $firstTwoBytes === "\r") {
                return null;
            }

            if ($firstTwoBytes === "\r\n") {
                $this->buffer = \substr($this->buffer, 2);
                goto complete;
            }

            $this->state = self::TRAILERS;
            goto trailers;
        }

        trailers:
        {
            $trailers = $this->shiftHeadersFromBuffer();
            if ($trailers === null) {
                return null;
            }

            $this->parseTrailers($trailers);
            goto complete;
        }

        complete:
        {
            $this->complete = true;

            return null;
        }
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * @return string|null
     *
     * @throws ParseException
     */
    private function shiftHeadersFromBuffer(): ?string
    {
        $this->buffer = \ltrim($this->buffer, "\r\n");

        if ($headersSize = \strpos($this->buffer, "\r\n\r\n")) {
            $headers = \substr($this->buffer, 0, $headersSize + 2);
            $this->buffer = \substr($this->buffer, $headersSize + 4);
        } else {
            $headersSize = \strlen($this->buffer);
            $headers = null;
        }

        if ($this->maxHeaderBytes > 0 && $headersSize > $this->maxHeaderBytes) {
            throw new ParseException("Configured header size exceeded: {$headersSize} bytes received, while the configured limit is {$this->maxHeaderBytes} bytes", Status::REQUEST_HEADER_FIELDS_TOO_LARGE);
        }

        return $headers;
    }

    /**
     * @param string $rawHeaders
     *
     * @return array
     *
     * @throws ParseException
     */
    private function parseRawHeaders(string $rawHeaders): array
    {
        // Legacy support for folded headers
        if (\strpos($rawHeaders, "\r\n\x20") || \strpos($rawHeaders, "\r\n\t")) {
            $rawHeaders = \preg_replace("/\r\n[\x20\t]++/", ' ', $rawHeaders);
        }

        try {
            $headers = Rfc7230::parseRawHeaders($rawHeaders);

            $headerMap = [];
            foreach ($headers as [$key, $value]) {
                $headerMap[\strtolower($key)][] = $value;
            }
        } catch (InvalidHeaderException $e) {
            throw new ParseException('Invalid headers', Status::BAD_REQUEST, $e);
        }

        if (isset($headerMap['transfer-encoding'])) {
            $transferEncodings = \explode(',', \strtolower(\implode(',', $headerMap['transfer-encoding'])));
            $transferEncodings = \array_map('trim', $transferEncodings);
            $this->chunkedEncoding = \in_array('chunked', $transferEncodings, true);
        } elseif (isset($headerMap['content-length'])) {
            if (\count($headerMap['content-length']) > 1) {
                throw new ParseException('Can\'t determine body length, because multiple content-length headers present in the response', Status::BAD_REQUEST);
            }

            $contentLength = $headerMap['content-length'][0];

            if (!\preg_match('/^(0|[1-9][0-9]*)$/', $contentLength)) {
                throw new ParseException('Can\'t determine body length, because the content-length header value is invalid', Status::BAD_REQUEST);
            }

            $this->remainingBodyBytes = (int) $contentLength;
        }

        return $headers;
    }

    /**
     * Decodes a chunked response body.
     *
     * @return bool {@code true} if the body is complete, otherwise {@code false}.
     *
     * @throws ParseException
     */
    private function parseChunkedBody(): bool
    {
        if ($this->chunkLengthRemaining !== null) {
            goto decode_chunk;
        }

        determine_chunk_size:
        {
            if (false === ($lineEndPos = \strpos($this->buffer, "\r\n"))) {
                return false;
            }

            if ($lineEndPos === 0) {
                throw new ParseException('Invalid line; hexadecimal chunk size expected', Status::BAD_REQUEST);
            }

            $line = \substr($this->buffer, 0, $lineEndPos);
            $hex = \strtolower(\trim(\ltrim($line, '0'))) ?: '0';
            $dec = \hexdec($hex);

            if ($hex !== \dechex($dec)) {
                throw new ParseException('Invalid hexadecimal chunk size', Status::BAD_REQUEST);
            }

            $this->chunkLengthRemaining = $dec;
            $this->buffer = \substr($this->buffer, $lineEndPos + 2);

            if ($this->chunkLengthRemaining === 0) {
                return true;
            }
        }

        decode_chunk:
        {
            $bufferLength = \strlen($this->buffer);

            // These first two (extreme) edge cases prevent errors where the packet boundary ends after
            // the \r and before the \n at the end of a chunk.
            if ($bufferLength === $this->chunkLengthRemaining || $bufferLength === $this->chunkLengthRemaining + 1) {
                return false;
            }

            if ($bufferLength >= $this->chunkLengthRemaining + 2) {
                $chunk = \substr($this->buffer, 0, $this->chunkLengthRemaining);
                $this->buffer = \substr($this->buffer, $this->chunkLengthRemaining + 2);
                $this->chunkLengthRemaining = null;
                $this->addToBody($chunk);

                goto determine_chunk_size;
            }

            /** @noinspection SuspiciousAssignmentsInspection */
            $chunk = $this->buffer;
            $this->buffer = '';
            $this->chunkLengthRemaining -= $bufferLength;
            $this->addToBody($chunk);

            return false;
        }
    }

    /**
     * @param string $trailers
     *
     * @throws ParseException
     */
    private function parseTrailers(string $trailers): void
    {
        try {
            $trailers = Rfc7230::parseHeaders($trailers);
        } catch (InvalidHeaderException $e) {
            throw new ParseException('Invalid trailers', Status::BAD_REQUEST, $e);
        }

        ($this->trailersCallback)($trailers);
    }

    /**
     * @param string $data
     *
     * @throws ParseException
     */
    private function addToBody(string $data): void
    {
        $length = \strlen($data);

        if (!$length) {
            return;
        }

        $this->bodyBytesConsumed += $length;

        if ($this->maxBodyBytes > 0 && $this->bodyBytesConsumed > $this->maxBodyBytes) {
            throw new ParseException("Configured body size exceeded: {$this->bodyBytesConsumed} bytes received, while the configured limit is {$this->maxBodyBytes} bytes", Status::PAYLOAD_TOO_LARGE);
        }

        if ($this->bodyDataCallback) {
            ($this->bodyDataCallback)($data);
        }
    }
}
