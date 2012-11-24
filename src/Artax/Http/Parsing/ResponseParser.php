<?php

namespace Artax\Http\Parsing;

use Spl\Mediator,
    Spl\KeyException,
    Artax\Http\StdResponse;

class ResponseParser {
    
    const EVENT_READ = 'artax.http.parsing.response-parser.data';
    
    const START = 0;
    const RES_H = 100;
    const RES_HT = 101;
    const RES_HTT = 102;
    const RES_HTTP = 103;
    const RES_FIRST_HTTP_MAJOR = 104;
    const RES_HTTP_MAJOR = 105;
    const RES_FIRST_HTTP_MINOR = 106;
    const RES_HTTP_MINOR = 107;
    const RES_FIRST_STATUS_CODE = 108;
    const RES_STATUS_CODE = 109;
    const RES_REASON = 110;
    const RES_LINE_ALMOST_DONE = 111;
    const HEADER_FIELD_START = 300;
    const HEADER_FIELD = 301;
    const HEADER_VALUE_START = 302;
    const HEADER_VALUE = 303;
    const HEADER_VALUE_LWS = 304;
    const HEADER_ALMOST_DONE = 305;
    const HEADERS_ALMOST_DONE = 306;
    const BODY = 500;
    const BODY_IDENTITY = 501;
    const BODY_IDENTITY_EOF = 502;
    const CHUNK_SIZE_START = 600;
    const CHUNK_SIZE = 601;
    const CHUNK_SIZE_ALMOST_DONE = 602;
    const CHUNK_DATA = 603;
    const CHUNK_DATA_TERMINATOR = 604;
    const CHUNK_DATA_ALMOST_DONE = 605;
    const CHUNK_DONE = 606;
    const TRAILER_START = 800;
    const TRAILER_ALMOST_DONE = 801;
    const MESSAGE_COMPLETE = 999;
    
    const E_BAD_START_LINE = 1000;
    const E_BAD_HEADER_TOKEN = 1300;
    const E_BAD_HEADER_VALUE = 1400;
    const E_BAD_CONTENT_LENGTH = 1500;
    const E_BAD_CHUNK_SIZE = 2000;
    const E_BAD_CHUNK_TERMINAL = 2200;
    const E_BAD_TRAILER = 3000;
    const E_UNEXPECTED_EOF = 9000;
    
    const ATTR_STRICT = 'attrStrict';
    const ATTR_IGNORE_BODY = 'attrIgnoreBody';
    const ATTR_BUFFER_BODY = 'attrBufferBody';
    const ATTR_MAX_GRANULARITY = 'attrMaxGranularity';
    const ATTR_TEMP_BODY_MEMORY = 'attrTempBodyMemory';
    
    private $attributes = array(
        self::ATTR_STRICT => true,
        self::ATTR_IGNORE_BODY => false,
        self::ATTR_BUFFER_BODY => false,
        self::ATTR_MAX_GRANULARITY => 8192,
        self::ATTR_TEMP_BODY_MEMORY => 2097152 // 2 MB
    );
    
    private $state = self::START;
    private $tokenizer;
    private $token;
    private $response;
    private $fieldBuffer;
    private $valueBuffer;
    private $remainingBytes;
    
    /**
     * @var resource
     */
    private $entityBody;
    
    /**
     * @var \Spl\Mediator
     */
    private $mediator;
    
    /**
     * @param Tokenizer $tokenizer
     * @param \Spl\Mediator $mediator 
     */
    public function __construct(Tokenizer $tokenizer, Mediator $mediator = null) {
        $this->tokenizer = $tokenizer;
        $this->mediator = $mediator;
        $this->response = new StdResponse;
    }
    
    /**
     * Iterates over the Tokenizer until parse completion, EOF or a socket wait condition
     * 
     * For local or blocking input streams only one call to `ResponseParser::parse()` is required.
     * However, non-blocking socket streams may result in several NULL returns prior to completion 
     * while the full message is downloaded. Applications can safely loop calls in such instances as
     * the result will either be the parsed response, NULL or a thrown ParseExeption. For example:
     * 
     * ```php
     * try {
     *     while (!$response = $parser->parse()) {
     *         continue;
     *     }
     * } catch (Artax\Http\Parsing\ParseException $e) {
     *     // Invalid message or EOF prior to completion
     * }
     * ```
     * 
     * @throws ParseException            On invalid HTTP message or unexpected EOF
     * @return \Artax\Http\ValueResponse Returns parsed response or NULL if awaiting more data from
     *                                   the input stream (non-local socket streams)
     */
    public function parse() {
        while ($this->token = $this->tokenizer->current()) {
            
            if ($this->token instanceof Symbols\EOF) {
                return $this->handleEof();
            }
            
            switch ($this->state) {
                case self::START:
                    $this->startRes();
                    break;
                case self::RES_H:
                    $this->resH();
                    break;
                case self::RES_HT:
                    $this->resHt();
                    break;
                case self::RES_HTT:
                    $this->resHtt();
                    break;
                case self::RES_HTTP:
                    $this->resHttp();
                    break;
                case self::RES_FIRST_HTTP_MAJOR:
                    $this->resFirstHttpMajor();
                    break;
                case self::RES_HTTP_MAJOR:
                    $this->resHttpMajor();
                    break;
                case self::RES_FIRST_HTTP_MINOR:
                    $this->resFirstHttpMinor();
                    break;
                case self::RES_HTTP_MINOR:
                    $this->resHttpMinor();
                    break;
                case self::RES_FIRST_STATUS_CODE:
                    $this->resFirstStatusCode();
                    break;
                case self::RES_STATUS_CODE:
                    $this->resStatusCode();
                    break;
                case self::RES_REASON:
                    $this->resReason();
                    break;
                case self::RES_LINE_ALMOST_DONE:
                    $this->resLineAlmostDone();
                    break;
                case self::HEADER_FIELD_START:
                    $this->headerFieldStart();
                    break;
                case self::HEADER_FIELD:
                    $this->headerField();
                    break;
                case self::HEADER_VALUE_START:
                    $this->headerValueStart();
                    break;
                case self::HEADER_VALUE:
                    $this->headerValue();
                    break;
                case self::HEADER_ALMOST_DONE:
                    $this->headerAlmostDone();
                    break;
                case self::HEADER_VALUE_LWS:
                    $this->headerValueLws();
                    break;
                case self::HEADERS_ALMOST_DONE:
                    $this->headersAlmostDone();
                    break;
                case self::CHUNK_SIZE_START:
                    $this->chunkSizeStart();
                    break;
                case self::CHUNK_SIZE:
                    $this->chunkSize();
                    break;
                case self::CHUNK_SIZE_ALMOST_DONE:
                    $this->chunkSizeAlmostDone();
                    break;
                case self::CHUNK_DATA:
                    $this->chunkData();
                    break;
                case self::CHUNK_DATA_TERMINATOR:
                    $this->chunkDataTerminator();
                    break;
                case self::CHUNK_DATA_ALMOST_DONE:
                    $this->chunkDataAlmostDone();
                    break;
                case self::BODY_IDENTITY:
                    $this->bodyIdentity();
                    break;
                case self::BODY_IDENTITY_EOF:
                    $this->bodyIdentityEof();
                    break;
                case self::TRAILER_START:
                    $this->trailerStart();
                    break;
                case self::TRAILER_ALMOST_DONE:
                    $this->trailerAlmostDone();
                    break;
            }
            
            if ($this->mediator) {
                $this->notifyDataListeners();
            }
            
            if ($this->state !== self::MESSAGE_COMPLETE) {
                $this->tokenizer->next();
            } else {
                return $this->getResponse();
            }
        }
    }
    
    private function notifyDataListeners() {
        $size = $this->token->getSize();
        $data = $this->token->__toString();
        
        // Only attempt to retrieve Content-Length if headers are complete
        if ($this->state > self::HEADERS_ALMOST_DONE
            && $this->response->hasHeader('Content-Length')
        ) {
            $bodySize = $this->response->getCombinedHeader('Content-Length');
            if (!filter_var($bodySize, FILTER_VALIDATE_INT)) {
                $bodySize = null;
            }
        } else {
            $bodySize = null;
        }
        
        $this->mediator->notify(self::EVENT_READ, $this, $data, $size, $bodySize);
    }
    
    private function handleEof() {
        if ($this->state == self::BODY_IDENTITY_EOF
            || $this->state == self::TRAILER_START
        ) {
            $this->state == self::MESSAGE_COMPLETE;
            return $this->getResponse();
        } else {
            throw new ParseException(
                'Unexpected EOF encountered prior to message parse completion',
                self::E_UNEXPECTED_EOF
            );
        }
    }
    
    private function getResponse() {
        if (!is_resource($this->entityBody)) {
            return $this->response->export();
        }
        
        rewind($this->entityBody);
        
        if ($this->attributes[self::ATTR_BUFFER_BODY]) {
            $bufferedBody = stream_get_contents($this->entityBody);
            $this->response->setBody($bufferedBody);
        } else {
            $this->response->setBody($this->entityBody);
        }
        
        return $this->response->export();
    }
    
    /**
     * "In the interest of robustness, servers SHOULD ignore any empty line(s) received where a
     * Request-Line is expected. In other words, if the server is reading the protocol stream at
     * the beginning of a message and receives a CRLF first, it should ignore the CRLF."
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.1
     */
    private function startRes() {
        if ($this->token instanceof Symbols\CR) {
            return;
        } elseif ($this->token instanceof Symbols\LF) {
            return;
        } elseif ('H' == $this->token) {
            $this->state = self::RES_H;
        } elseif ('h' == $this->token && !$this->attributes[self::ATTR_STRICT]) {
            $this->state = self::RES_H;
        } else {
            throw new ParseException(
                "Invalid status line: expected `H` at position " . $this->tokenizer->key() .
                "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function getTokenType() {
        $nameParts = explode('\\', get_class($this->token));
        return '[' . end($nameParts) . ']';
    }
    
    private function resH() {
        if ('T' == $this->token) {
            $this->state = self::RES_HT;
        } elseif ('t' == $this->token && !$this->attributes[self::ATTR_STRICT]) {
            $this->state = self::RES_HT;
        } else {
            throw new ParseException(
                "Invalid status line: expected `T` at position " . $this->tokenizer->key() .
                "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resHt() {
        if ('T' == $this->token) {
            $this->state = self::RES_HTT;
        } elseif ('t' == $this->token && !$this->attributes[self::ATTR_STRICT]) {
            $this->state = self::RES_HTT;
        } else {
            throw new ParseException(
                "Invalid status line: expected `T` at position " . $this->tokenizer->key() .
                "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resHtt() {
        if ('P' == $this->token) {
            $this->state = self::RES_HTTP;
        } elseif ('p' == $this->token && !$this->attributes[self::ATTR_STRICT]) {
            $this->state = self::RES_HTTP;
        } else {
            throw new ParseException(
                "Invalid status line: expected `P` at position " . $this->tokenizer->key() .
                "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resHttp() {
        if ('/' != $this->token) {
            throw new ParseException(
                "Invalid status line: expected `/` at position " . $this->tokenizer->key() .
                "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
        
        $this->state = self::RES_FIRST_HTTP_MAJOR;
    }
    
    private function resFirstHttpMajor() {
        if ($this->token instanceof Symbols\DIGIT) {
            $this->valueBuffer .= $this->token;
            $this->state = self::RES_HTTP_MAJOR;
        } else {
            throw new ParseException(
                "Invalid status line: expected `[0-9]` at position " .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resHttpMajor() {
        if ('.' == $this->token) {
            $this->valueBuffer .= $this->token;
            $this->state = self::RES_FIRST_HTTP_MINOR;
        } elseif ($this->token instanceof Symbols\DIGIT) {
            $this->valueBuffer .= $this->token;
        } else {
            throw new ParseException(
                "Invalid status line: expected `[0-9]` at position " .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resFirstHttpMinor() {
        if ($this->token instanceof Symbols\DIGIT) {
            $this->valueBuffer .= $this->token;
            $this->state = self::RES_HTTP_MINOR;
        } else {
            throw new ParseException(
                "Invalid status line: expected `[0-9]` at position " .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resHttpMinor() {
        if ($this->token instanceof Symbols\SP) {
            $this->state = self::RES_FIRST_STATUS_CODE;
        } elseif ($this->token instanceof Symbols\DIGIT) {
            $this->valueBuffer .= $this->token;
        } else {
            throw new ParseException(
                "Invalid status line: expected `[0-9]` at position " .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    /**
     * "Clients SHOULD be tolerant in parsing the Status-Line and servers tolerant when parsing the
     * Request-Line. In particular, they SHOULD accept any amount of SP or HT characters between
     * fields, even though only a single SP is required."
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.3
     */
    private function resFirstStatusCode() {
        if ($this->token instanceof Symbols\LWS && !$this->attributes[self::ATTR_STRICT]) {
            return;
        } else {
            $this->response->setProtocol($this->valueBuffer);
            $this->valueBuffer = null;
        }
        
        if ($this->token instanceof Symbols\DIGIT) {
            $this->valueBuffer .= $this->token;
            $this->state = self::RES_STATUS_CODE;
        } else {
            throw new ParseException(
                "Invalid status line: expected `[0-9]` at position " .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resStatusCode() {
        if ($this->token instanceof Symbols\DIGIT) {
            $this->valueBuffer .= $this->token;
        } elseif ($this->token instanceof Symbols\SP) {
            $this->response->setStatusCode($this->valueBuffer);
            $this->valueBuffer = null;
            $this->state = self::RES_REASON;
        } elseif ($this->token instanceof Symbols\CR) {
            $this->response->setStatusCode($this->valueBuffer);
            $this->valueBuffer = null;
            $this->state = self::RES_LINE_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\LF && !$this->attributes[self::ATTR_STRICT]) {
            $this->response->setStatusCode($this->valueBuffer);
            $this->valueBuffer = null;
            $this->state = self::HEADER_FIELD_START;
        } else {
            throw new ParseException(
                "Invalid status code: expected `[0-9]` at position " .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resReason() {
        if ($this->token instanceof Symbols\CR) {
            $this->response->setReasonPhrase($this->valueBuffer);
            $this->valueBuffer = null;
            $this->state = self::RES_LINE_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\LF && !$this->attributes[self::ATTR_STRICT]) {
            $this->response->setReasonPhrase($this->valueBuffer);
            $this->valueBuffer = null;
            $this->state = self::HEADER_FIELD_START;
        } elseif ($this->token instanceof Symbols\LWS || !$this->token instanceof Symbols\CTL) {
            $this->valueBuffer .= $this->token;
        } else {
            throw new ParseException(
                'Invalid reason phrase: expected TEXT at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function resLineAlmostDone() {
        if ($this->token instanceof Symbols\LF) {
            $this->state = self::HEADER_FIELD_START;
        } else {
            throw new ParseException(
                'Invalid status line: expected `LF` (\\n) at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_START_LINE
            );
        }
    }
    
    private function headerFieldStart() {
        if ($this->token instanceof Symbols\CR) {
            $this->state = self::HEADERS_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\LF && !$this->attributes[self::ATTR_STRICT]) {
            $this->initializeBodyRetrieval();
        } elseif ($this->token instanceof Symbols\TOKEN) {
            $this->fieldBuffer .= $this->token;
            $this->state = self::HEADER_FIELD;
        } else {
            throw new ParseException(
                'Invalid header field name: expected token at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_HEADER_TOKEN
            );
        }
    }
    
    private function initializeBodyRetrieval() {
        if ($this->attributes[self::ATTR_IGNORE_BODY]) {
            $this->state = self::MESSAGE_COMPLETE;
            return;
        }
        
        $status = $this->response->getStatusCode();
        if ($status == 204 || $status == 304 || $status < 200) {
            $this->state = self::MESSAGE_COMPLETE;
            return;
        }
        
        if ($this->isChunkEncoded()) {
            $this->state = self::CHUNK_SIZE_START;
        } elseif ($this->response->hasHeader('Content-Length')) {
            $contentLengthIterator = $this->response->getHeaders('Content-Length');
            // Use the first `Content-Length:` header if multiples were specified
            $contentLength = $contentLengthIterator->current()->getValue();
            
            if (!$contentLength) {
                $this->state = self::MESSAGE_COMPLETE;
            } elseif (!filter_var($contentLength, FILTER_VALIDATE_INT)) {
                throw new ParseException(
                    'Invalid Content-Length header value; integer required',
                    self::E_BAD_CONTENT_LENGTH
                );
            } else {
                $granularity = $this->getMaxAllowableGranularity($contentLength);
                $this->tokenizer->setGranularity($granularity);
                $this->remainingBytes = $contentLength;
                $this->state = self::BODY_IDENTITY;
            }
        } else {
            $granularity = $this->getMaxAllowableGranularity();
            $this->tokenizer->setGranularity($granularity);
            $this->state = self::BODY_IDENTITY_EOF;
        }
        
        $inMemoryBodySize = $this->attributes[self::ATTR_TEMP_BODY_MEMORY];
        $uri = $inMemoryBodySize ?  "php://temp/maxmemory:$inMemoryBodySize" : 'php://memory';
        
        $this->entityBody = fopen($uri, 'r+');
    }
    
    private function isChunkEncoded() {
        if (!$this->response->hasHeader('Transfer-Encoding')) {
            return false;
        }
        
        // Use the first `Transfer-Encoding:` header if multiples were specified
        $transferEncoding = $this->response->getCombinedHeader('Transfer-Encoding');
        
        return strcasecmp($transferEncoding, 'identity');
    }
    
    private function getMaxAllowableGranularity($proposedBytes = null) {
        $maxAllowed = $this->attributes[self::ATTR_MAX_GRANULARITY];
        return (!$proposedBytes || $proposedBytes > $maxAllowed) ? $maxAllowed : $proposedBytes;
    }
    
    private function headerField() {
        if ($this->token instanceof Symbols\TOKEN) {
            $this->fieldBuffer .= $this->token;
        } elseif ($this->token == ':') {
            $this->state = self::HEADER_VALUE_START;
        } else {
            throw new ParseException(
                'Invalid header field name: expected token at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_HEADER_TOKEN
            );
        }
    }
    
    private function headerValueStart() {
        if ($this->token instanceof Symbols\LWS) {
            // ignore leading whitespace in header values
            return;
        } elseif ($this->token instanceof Symbols\CR) {
            $this->state = self::HEADER_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\LF && !$this->attributes[self::ATTR_STRICT]) {
            $this->finalizeCompletedHeader();
            $this->state = self::HEADER_FIELD_START;
        } else {
            $this->valueBuffer .= $this->token;
            $this->state = self::HEADER_VALUE;
        }
    }
    
    private function headerValue() {
        if ($this->token instanceof Symbols\CR) {
            $this->state = self::HEADER_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\LF && !$this->attributes[self::ATTR_STRICT]) {
            $this->finalizeCompletedHeader();
            $this->state = self::HEADER_FIELD_START;
        } elseif ($this->token instanceof Symbols\HT || !$this->token instanceof Symbols\CTL) {
            $this->valueBuffer .= $this->token;
        } else {
            throw new ParseException(
                '[STRICT] Invalid header value: control character not allowed at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_HEADER_VALUE
            );
        }
    }
    
    private function finalizeCompletedHeader() {
        $this->response->addHeader($this->fieldBuffer, (string) $this->valueBuffer);
        $this->fieldBuffer = null;
        $this->valueBuffer = null;
    }
    
    private function headerAlmostDone() {
        if (!$this->token instanceof Symbols\LF) {
            throw new ParseException(
                'Invalid header value: line feed (`\\n`) expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_HEADER_VALUE
            );
        }
        
        $this->state = self::HEADER_VALUE_LWS;
    }
    
    private function headerValueLws() {
        if ($this->token instanceof Symbols\LWS) {
            $this->valueBuffer .= ' ';
            // We go to HEADER_VALUE_START instead of HEADER_VALUE to continue trimming whitespace
            $this->state = self::HEADER_VALUE_START;
            return;
        }
        
        // We may have gotten here for a second time if an EOF was encountered between a CR and an
        // LF while reading a non-local stream. If this is the case, the buffer will have already
        // been reset to NULL. If that's the case we don't need to finalize again.
        if ($this->fieldBuffer) {
            $this->finalizeCompletedHeader();
        }
        
        if ($this->token instanceof Symbols\CR) {
            $this->state = self::HEADERS_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\TOKEN) {
            $this->fieldBuffer .= $this->token;
            $this->state = self::HEADER_FIELD;
        } else {
            throw new ParseException(
                'Unexpected token at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_HEADER_VALUE
            );
        }
    }
    
    private function headersAlmostDone() {
        if (!$this->token instanceof Symbols\LF) {
            throw new ParseException(
                'Invalid header value: line feed (`\\n`) expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_HEADER_VALUE
            );
        }
        
        $this->initializeBodyRetrieval();
    }
    
    private function chunkSizeStart() {
        if (!$this->token instanceof Symbols\HEX) {
            throw new ParseException(
                'Invalid chunk size: HEX character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_CHUNK_SIZE
            );
        }
        
        $this->state = self::CHUNK_SIZE;
        $this->remainingBytes .= $this->token;
    }
    
    /**
     * @todo Add support for chunk extensions
     */
    private function chunkSize() {
        if ($this->token instanceof Symbols\CR) {
            $this->state = self::CHUNK_SIZE_ALMOST_DONE;
        } elseif ($this->token instanceof Symbols\HEX) {
            $this->remainingBytes .= $this->token;
        } else {
            throw new ParseException(
                'Invalid chunk size: CR, HEX, SP or `;` character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_CHUNK_SIZE
            );
        }
    }
    
    private function chunkSizeAlmostDone() {
        if (!$this->token instanceof Symbols\LF) {
            throw new ParseException(
                'Invalid chunk size: LF (`\\n`) character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_CHUNK_SIZE
            );
        }
        
        $decimalChunkSize = hexdec($this->remainingBytes);
        
        if ($decimalChunkSize === 0) {
            $this->remainingBytes = null;
            $this->state = self::TRAILER_START;
        } else {
            $this->remainingBytes = $decimalChunkSize;
            $granularity = $this->getMaxAllowableGranularity($decimalChunkSize);
            $this->tokenizer->setGranularity($granularity);
            $this->state = self::CHUNK_DATA;
        }
    }
    
    private function trailerStart() {
        if ($this->token instanceof Symbols\CR) {
            $this->state = self::TRAILER_ALMOST_DONE;
        } else {
            throw new ParseException(
                'Invalid chunk size: CR (`\\r`) character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_TRAILER
            );
        }
    }
    
    private function trailerAlmostDone() {
        if (!$this->token instanceof Symbols\LF) {
            throw new ParseException(
                'Invalid chunk size: LF (`\\n`) character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_TRAILER
            );
        }
        
        $this->state = self::MESSAGE_COMPLETE;
    }
    
    private function chunkData() {
        fwrite($this->entityBody, $this->token);
        
        $blockSize = $this->token->getSize();
        
        if ($blockSize == $this->remainingBytes) {
            $this->remainingBytes = null;
            $this->state = self::CHUNK_DATA_TERMINATOR;
            $this->tokenizer->setGranularity(1);
        } else {
            $remainingChunkSize = $this->remainingBytes - $blockSize;
            $this->remainingBytes = $remainingChunkSize;
            $granularity = $this->getMaxAllowableGranularity($remainingChunkSize);
            $this->tokenizer->setGranularity($granularity);
        }
    }
    
    private function chunkDataTerminator() {
        if (!$this->token instanceof Symbols\CR) {
            throw new ParseException(
                'Invalid chunk terminal: CR (`\\n`) character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_CHUNK_TERMINAL
            );
        }
        
        $this->state = self::CHUNK_DATA_ALMOST_DONE;
    }
    
    private function chunkDataAlmostDone() {
        if (!$this->token instanceof Symbols\LF) {
            throw new ParseException(
                'Invalid chunk terminal: LF (`\\n`) character expected at position ' .
                $this->tokenizer->key() . "; " . $this->getTokenType() . " received",
                self::E_BAD_CHUNK_TERMINAL
            );
        }
        
        $this->state = self::CHUNK_SIZE_START;
    }
    
    private function bodyIdentity() {
        fwrite($this->entityBody, $this->token);
        
        $tokenLength = $this->token->getSize();
        if ($tokenLength == $this->remainingBytes) {
            $this->state = self::MESSAGE_COMPLETE;
            return;
        }
        
        $remainingContentLength = $this->remainingBytes - $tokenLength;
        $granularity = $this->getMaxAllowableGranularity($remainingContentLength);
        
        $this->tokenizer->setGranularity($granularity);
        $this->remainingBytes = $remainingContentLength;
    }
    
    private function bodyIdentityEof() {
        fwrite($this->entityBody, $this->token);
    }
    
    /**
     * Set optional attributes
     * 
     * @param string $attribute
     * @param mixed $value
     * @throws \Spl\KeyException On invalid attribute
     * @return void
     */
    public function setAttribute($attribute, $value) {
        if (!isset($this->attributes[$attribute])) {
            throw new KeyException(
                'Invalid attribute'
            );
        }
        
        $setter = 'set' . ucfirst($attribute);
        
        $this->$setter($value);
    }
    
    private function setAttrStrict($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_STRICT] = $boolFlag;
    }
    
    private function setAttrIgnoreBody($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_IGNORE_BODY] = $boolFlag;
    }
    
    private function setAttrMaxGranularity($bytes) {
        $granularity = filter_var($bytes, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 8192,
                'min_range' => 1
            )
        ));
        
        $this->attributes[self::ATTR_MAX_GRANULARITY] = $granularity;
    }
    
    private function setAttrTempBodyMemory($bytes) {
        $bytes = filter_var($bytes, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 2097152,
                'min_range' => 0
            )
        ));
        
        $this->attributes[self::ATTR_TEMP_BODY_MEMORY] = $bytes;
    }
    
    private function setAttrBufferBody($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_BUFFER_BODY] = $boolFlag;
    }
}