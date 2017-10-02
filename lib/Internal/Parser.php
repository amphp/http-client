<?php

namespace Amp\Artax\Internal;

use Amp\Artax\ParseException;

/** @internal */
final class Parser {
    const STATUS_LINE_PATTERN = "#^
        HTTP/(?P<protocol>\d+\.\d+)[\x20\x09]+
        (?P<status>[1-5]\d\d)[\x20\x09]*
        (?P<reason>[^\x01-\x08\x10-\x19]*)
    $#ix";

    const HEADERS_PATTERN = "/
        (?P<field>[^\(\)<>@,;:\\\"\/\[\]\?\={}\x20\x09\x01-\x1F\x7F]+):[\x20\x09]*
        (?P<value>[^\x01-\x08\x0A-\x1F\x7F]*)[\x0D]?[\x20\x09]*[\r]?[\n]
    /x";

    const MODE_REQUEST = 1;
    const MODE_RESPONSE = 2;

    const AWAITING_HEADERS = 0;
    const BODY_IDENTITY = 1;
    const BODY_IDENTITY_EOF = 2;
    const BODY_CHUNKS = 3;
    const TRAILERS_START = 4;
    const TRAILERS = 5;

    const OP_MAX_HEADER_BYTES = "amp.artax.parser.max-header-bytes";
    const OP_MAX_BODY_BYTES = "amp.artax.parser.max-body-bytes";

    const DEFAULT_MAX_HEADER_BYTES = 8192;
    const DEFAULT_MAX_BODY_BYTES = 10485760;

    private $mode;
    private $state = self::AWAITING_HEADERS;
    private $buffer = '';
    private $traceBuffer;
    private $protocol;
    private $requestMethod;
    private $requestUri;
    private $responseCode;
    private $responseReason;
    private $headers = [];
    private $remainingBodyBytes;
    private $bodyBytesConsumed = 0;
    private $chunkLenRemaining = null;
    private $responseMethodMatch = [];
    private $parseFlowHeaders = [
        'TRANSFER-ENCODING' => null,
        'CONTENT-LENGTH' => null,
    ];

    private $maxHeaderBytes = self::DEFAULT_MAX_HEADER_BYTES;
    private $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES;
    private $bodyDataCallback;

    public function __construct(callable $bodyDataCallback = null, $mode = self::MODE_RESPONSE) {
        $this->bodyDataCallback = $bodyDataCallback;
        $this->mode = $mode;
    }

    public function setAllOptions(array $options) {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    public function setOption(string $option, $value) {
        switch ($option) {
            case self::OP_MAX_HEADER_BYTES:
                $this->maxHeaderBytes = (int) $value;
                break;
            case self::OP_MAX_BODY_BYTES:
                $this->maxBodyBytes = (int) $value;
                break;
            default:
                throw new \Error(
                    sprintf('Unknown parser option: %s', $option)
                );
        }
    }

    public function enqueueResponseMethodMatch(string $method) {
        $this->responseMethodMatch[] = $method;
    }

    /**
     * @return string|null
     */
    public function getBuffer() {
        return $this->buffer;
    }

    public function getState(): int {
        return $this->state;
    }

    public function buffer(string $data) {
        $this->buffer .= $data;
    }

    public function parse(string $data = null) {
        if ($data !== null) {
            $this->buffer .= $data;
        }

        if ($this->buffer == '') {
            goto more_data_needed;
        }

        switch ($this->state) {
            case self::AWAITING_HEADERS:
                goto awaiting_headers;
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

        awaiting_headers: {
            if (!$startLineAndHeaders = $this->shiftHeadersFromMessageBuffer()) {
                goto more_data_needed;
            }

            goto start_line;
        }

        start_line: {
            $startLineEndPos = strpos($startLineAndHeaders, "\n");
            $startLine = substr($startLineAndHeaders, 0, $startLineEndPos);
            $rawHeaders = substr($startLineAndHeaders, $startLineEndPos + 1);
            $this->traceBuffer = $startLineAndHeaders;

            if ($this->mode === self::MODE_REQUEST) {
                goto request_line_and_headers;
            }

            goto status_line_and_headers;
        }

        request_line_and_headers: {
            $parts = explode(' ', trim($startLine));

            if (isset($parts[0]) && ($method = trim($parts[0]))) {
                $this->requestMethod = $method;
            } else {
                throw new ParseException($this->getParsedMessageArray(), 'Invalid request line', 400);
            }

            if (isset($parts[1]) && ($uri = trim($parts[1]))) {
                $this->requestUri = $uri;
            } else {
                throw new ParseException($this->getParsedMessageArray(), 'Invalid request line', 400);
            }

            if (isset($parts[2]) && ($protocol = str_ireplace('HTTP/', '', trim($parts[2])))) {
                $this->protocol = $protocol;
            } else {
                throw new ParseException($this->getParsedMessageArray(), 'Invalid request line', 400);
            }

            if (!($protocol === '1.0' || '1.1' === $protocol)) {
                throw new ParseException($this->getParsedMessageArray(), "Protocol not supported: {$protocol}", 505);
            }

            if ($rawHeaders) {
                $this->headers = $this->parseHeadersFromRaw($rawHeaders);
            }

            goto transition_from_request_headers_to_body;
        }

        status_line_and_headers: {
            if (preg_match(self::STATUS_LINE_PATTERN, $startLine, $matches)) {
                $this->protocol = $matches['protocol'];
                $this->responseCode = (int) $matches['status'];
                $this->responseReason = trim($matches['reason']);
            } else {
                throw new ParseException($this->getParsedMessageArray(), 'Invalid status line', 400);
            }

            if ($rawHeaders) {
                $this->headers = $this->parseHeadersFromRaw($rawHeaders);
            }

            goto transition_from_response_headers_to_body;
        }

        transition_from_request_headers_to_body: {
            if ($this->requestMethod == 'HEAD' || $this->requestMethod == 'TRACE' || $this->requestMethod == 'OPTIONS') {
                goto complete;
            } elseif ($this->parseFlowHeaders['TRANSFER-ENCODING']) {
                $this->state = self::BODY_CHUNKS;
                goto before_body;
            } elseif ($this->parseFlowHeaders['CONTENT-LENGTH']) {
                $this->remainingBodyBytes = $this->parseFlowHeaders['CONTENT-LENGTH'];
                $this->state = self::BODY_IDENTITY;
                goto before_body;
            }

            goto complete;
        }

        transition_from_response_headers_to_body: {
            $requestMethod = array_shift($this->responseMethodMatch);

            if ($this->responseCode == 204
                || $this->responseCode == 304
                || $this->responseCode < 200
                || $requestMethod === 'HEAD'
                || $requestMethod === 'CONNECT'
            ) {
                goto complete;
            } elseif ($this->parseFlowHeaders['TRANSFER-ENCODING']) {
                $this->state = self::BODY_CHUNKS;
                goto before_body;
            } elseif ($this->parseFlowHeaders['CONTENT-LENGTH'] === null) {
                $this->state = self::BODY_IDENTITY_EOF;
                goto before_body;
            } elseif ($this->parseFlowHeaders['CONTENT-LENGTH'] > 0) {
                $this->remainingBodyBytes = $this->parseFlowHeaders['CONTENT-LENGTH'];
                $this->state = self::BODY_IDENTITY;
                goto before_body;
            }

            goto complete;
        }

        before_body: {
            if ($this->remainingBodyBytes === 0) {
                goto complete;
            }

            $parsedMsgArr = $this->getParsedMessageArray();
            $parsedMsgArr['headersOnly'] = true;

            return $parsedMsgArr;
        }

        body_identity: {
            $bufferDataSize = strlen($this->buffer);

            if ($bufferDataSize < $this->remainingBodyBytes) {
                $this->addToBody($this->buffer);
                $this->buffer = null;
                $this->remainingBodyBytes -= $bufferDataSize;
                goto more_data_needed;
            } elseif ($bufferDataSize == $this->remainingBodyBytes) {
                $this->addToBody($this->buffer);
                $this->buffer = null;
                $this->remainingBodyBytes = 0;
                goto complete;
            }

            $bodyData = substr($this->buffer, 0, $this->remainingBodyBytes);
            $this->addToBody($bodyData);
            $this->buffer = substr($this->buffer, $this->remainingBodyBytes);
            $this->remainingBodyBytes = 0;
            goto complete;
        }

        body_identity_eof: {
            $this->addToBody($this->buffer);
            $this->buffer = '';
            goto more_data_needed;
        }

        body_chunks: {
            if ($this->dechunk()) {
                $this->state = self::TRAILERS_START;
                goto trailers_start;
            }

            goto more_data_needed;
        }

        trailers_start: {
            $firstTwoBytes = substr($this->buffer, 0, 2);

            if ($firstTwoBytes == "" || $firstTwoBytes === "\r") {
                goto more_data_needed;
            } elseif ($firstTwoBytes === "\r\n") {
                $this->buffer = substr($this->buffer, 2);
                goto complete;
            }

            $this->state = self::TRAILERS;
            goto trailers;
        }

        trailers: {
            if ($trailers = $this->shiftHeadersFromMessageBuffer()) {
                $this->parseTrailers($trailers);
                goto complete;
            }

            goto more_data_needed;
        }

        complete: {
            $parsedMsgArr = $this->getParsedMessageArray();
            $parsedMsgArr['headersOnly'] = false;

            $this->state = self::AWAITING_HEADERS;
            $this->traceBuffer = null;
            $this->headers = [];
            $this->bodyBytesConsumed = 0;
            $this->remainingBodyBytes = null;
            $this->chunkLenRemaining = null;
            $this->protocol = null;
            $this->requestUri = null;
            $this->requestMethod = null;
            $this->responseCode = null;
            $this->responseReason = null;
            $this->parseFlowHeaders = [
                'TRANSFER-ENCODING' => null,
                'CONTENT-LENGTH' => null,
            ];

            return $parsedMsgArr;
        }

        more_data_needed: {
            return null;
        }
    }

    private function shiftHeadersFromMessageBuffer() {
        $this->buffer = ltrim($this->buffer, "\r\n");

        if ($headersSize = strpos($this->buffer, "\r\n\r\n")) {
            $headers = substr($this->buffer, 0, $headersSize + 2);
            $this->buffer = substr($this->buffer, $headersSize + 4);
        } elseif ($headersSize = strpos($this->buffer, "\n\n")) {
            $headers = substr($this->buffer, 0, $headersSize + 1);
            $this->buffer = substr($this->buffer, $headersSize + 2);
        } else {
            $headersSize = strlen($this->buffer);
            $headers = null;
        }

        if ($this->maxHeaderBytes > 0 && $headersSize > $this->maxHeaderBytes) {
            throw new ParseException($this->getParsedMessageArray(), "Maximum allowable header size exceeded: {$this->maxHeaderBytes}", 431);
        }

        return $headers;
    }

    private function parseHeadersFromRaw($rawHeaders) {
        if (strpos($rawHeaders, "\n\x20") || strpos($rawHeaders, "\n\t")) {
            $rawHeaders = preg_replace("/(?:\r\n|\n)[\x20\t]+/", ' ', $rawHeaders);
        }

        if (!preg_match_all(self::HEADERS_PATTERN, $rawHeaders, $matches)) {
            throw new ParseException(
                $this->getParsedMessageArray(),
                $msg = 'Invalid headers',
                $code = 400,
                $previousException = null
            );
        }

        $headers = [];

        $aggregateMatchedHeaders = '';

        for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
            $aggregateMatchedHeaders .= $matches[0][$i];
            $field = $matches['field'][$i];
            $headers[$field][] = $matches['value'][$i];
        }

        if (strlen($rawHeaders) !== strlen($aggregateMatchedHeaders)) {
            throw new ParseException(
                $this->getParsedMessageArray(),
                $msg = 'Invalid headers',
                $code = 400,
                $previousException = null
            );
        }

        $ucKeyHeaders = array_change_key_case($headers, CASE_UPPER);

        if (isset($ucKeyHeaders['TRANSFER-ENCODING'])
            && strcasecmp('identity', $ucKeyHeaders['TRANSFER-ENCODING'][0])
        ) {
            $this->parseFlowHeaders['TRANSFER-ENCODING'] = true;
        } elseif (isset($ucKeyHeaders['CONTENT-LENGTH'])) {
            $this->parseFlowHeaders['CONTENT-LENGTH'] = (int) $ucKeyHeaders['CONTENT-LENGTH'][0];
        }

        return $headers;
    }

    private function dechunk() {
        if ($this->chunkLenRemaining !== null) {
            goto dechunk;
        }

        determine_chunk_size: {
            if (false === ($lineEndPos = strpos($this->buffer, "\r\n"))) {
                goto more_data_needed;
            } elseif ($lineEndPos === 0) {
                throw new ParseException(
                    $this->getParsedMessageArray(),
                    $msg = 'Invalid new line; hexadecimal chunk size expected',
                    $code = 400,
                    $previousException = null
                );
            }

            $line = substr($this->buffer, 0, $lineEndPos);
            $hex = strtolower(trim(ltrim($line, '0'))) ?: 0;
            $dec = hexdec($hex);

            if ($hex == dechex($dec)) {
                $this->chunkLenRemaining = $dec;
            } else {
                throw new ParseException(
                    $this->getParsedMessageArray(),
                    $msg = 'Invalid hexadecimal chunk size',
                    $code = 400,
                    $previousException = null
                );
            }

            $this->buffer = substr($this->buffer, $lineEndPos + 2);

            if (!$dec) {
                return true;
            }
        }

        dechunk: {
            $bufferLen = strlen($this->buffer);

            // These first two (extreme) edge cases prevent errors where the packet boundary ends after
            // the \r and before the \n at the end of a chunk.
            if ($bufferLen === $this->chunkLenRemaining) {
                goto more_data_needed;
            } elseif ($bufferLen === $this->chunkLenRemaining + 1) {
                goto more_data_needed;
            } elseif ($bufferLen >= $this->chunkLenRemaining + 2) {
                $chunk = substr($this->buffer, 0, $this->chunkLenRemaining);
                $this->buffer = substr($this->buffer, $this->chunkLenRemaining + 2);
                $this->chunkLenRemaining = null;
                $this->addToBody($chunk);

                goto determine_chunk_size;
            }

            $this->addToBody($this->buffer);
            $this->buffer = '';
            $this->chunkLenRemaining -= $bufferLen;

            goto more_data_needed;
        }

        more_data_needed: {
            return false;
        }
    }

    private function parseTrailers($trailers) {
        $trailerHeaders = $this->parseHeadersFromRaw($trailers);
        $ucKeyTrailerHeaders = array_change_key_case($trailerHeaders, CASE_UPPER);
        $ucKeyHeaders = array_change_key_case($this->headers, CASE_UPPER);

        unset(
            $ucKeyTrailerHeaders['TRANSFER-ENCODING'],
            $ucKeyTrailerHeaders['CONTENT-LENGTH'],
            $ucKeyTrailerHeaders['TRAILER']
        );

        foreach (array_keys($this->headers) as $key) {
            $ucKey = strtoupper($key);
            if (isset($ucKeyTrailerHeaders[$ucKey])) {
                $this->headers[$key] = $ucKeyTrailerHeaders[$ucKey];
            }
        }

        foreach (array_keys($trailerHeaders) as $key) {
            $ucKey = strtoupper($key);
            if (!isset($ucKeyHeaders[$ucKey])) {
                $this->headers[$key] = $trailerHeaders[$key];
            }
        }
    }

    public function getParsedMessageArray(): array {
        $result = [
            'protocol' => $this->protocol,
            'headers' => $this->headers,
            'trace' => $this->traceBuffer,
            'buffer' => $this->buffer,
            'headersOnly' => false,
        ];

        if ($this->mode === self::MODE_REQUEST) {
            $result['method'] = $this->requestMethod;
            $result['uri'] = $this->requestUri;
        } else {
            $result['status'] = $this->responseCode;
            $result['reason'] = $this->responseReason;
        }

        return $result;
    }

    private function addToBody(string $data) {
        $this->bodyBytesConsumed += strlen($data);

        if ($this->maxBodyBytes > 0 && $this->bodyBytesConsumed > $this->maxBodyBytes) {
            throw new ParseException($this->getParsedMessageArray(), "Maximum allowable body size exceeded: {$this->maxBodyBytes}", 413);
        }

        if ($this->bodyDataCallback) {
            ($this->bodyDataCallback)($data);
        }
    }
}
