<?php

namespace Artax\Http;

use Ardent\Mediator,
    Ardent\KeyException,
    Ardent\TypeException,
    Ardent\DomainException,
    Artax\Http\Request,
    Artax\Http\StdRequest;

class RequestWriter {
    
    const CRLF = "\r\n";
    const FINAL_CHUNK = "0\r\n";
    
    const EVENT_WRITE = 'artax.http.request-writer.write';
    
    const STATE_START = 0;
    const STATE_START_LINE = 1;
    const STATE_HEADERS = 2;
    const STATE_AWAITING_CONTINUE = 3;
    const STATE_INIT_BODY = 4;
    const STATE_BODY = 5;
    const STATE_BODY_STREAM = 6;
    const STATE_BODY_STREAM_CHUNKS = 7;
    const STATE_COMPLETE = 8;
    
    const ATTR_100_CONTINUE_DELAY = 'attr100ContinueDelay';
    const ATTR_STREAM_BUFFER_SIZE = 'attrStreamBufferSize';
    
    private $attributes = array(
        self::ATTR_100_CONTINUE_DELAY => 5,
        self::ATTR_STREAM_BUFFER_SIZE => 8192
    );
    
    private $state = self::STATE_START;
    private $request;
    private $destination;
    
    private $buffer;
    private $bufferPos = 0;
    private $bufferSize;
    
    private $continueDelayStartedAt;
    
    /**
     * @var \Ardent\Mediator
     */
    private $mediator;
    
    /**
     * @param Http\Request $request
     * @param resource $destination
     * @param \Ardent\Mediator $mediator
     * @throws \Ardent\TypeException on non-resource destination
     */
    public function __construct(Request $request, $destination, Mediator $mediator = null) {
        if (!is_resource($destination)) {
            throw new TypeException(
                get_class($this) . '::send requires a valid stream resource at Argument 2'
            );
        }
        
        $this->request = new StdRequest;
        $this->request->import($request);
        $this->destination = $destination;
        $this->mediator = $mediator;
    }
    
    /**
     * Is the writer currently awaiting a 100 Continue response?
     * 
     * @return bool
     */
    public function expectsContinue() {
        return ($this->state == self::STATE_AWAITING_CONTINUE);
    }
    
    /**
     * Instruct the writer that it's okay to continue with the entity body
     * 
     * @return void
     */
    public function allowContinue() {
        if ($this->state == self::STATE_AWAITING_CONTINUE) {
            $this->state = self::STATE_INIT_BODY;
        }
    }
    
    /**
     * Writes the request to the destination stream
     * 
     * If the request specifies an `Expect: 100-continue` header the request writer will pause
     * sending data to the destination stream until either:
     * 
     * 1. `RequestWriter::allowContinue()` is invoked, or
     * 2. The delay time specified by the `ATTR_100_CONTINUE_DELAY` attribute expires
     * 
     * Clients should use the `expectsContinue()` method to determine if this is the case and read
     * the response appropriately:
     * 
     * ```php
     * <?php
     * while (!$writer->send()) {
     *     if ($writer->expectsContinue()) {
     *         break;
     *     }
     * }
     * 
     * // Then, once a 100 Continue response is received ...
     * $writer->allowContinuation();
     * while (!$writer->send()) {
     *     continue;
     * }
     * ```
     * 
     * If the request entity body is a stream resource the body will be sent using the "chunked"
     * transfer encoding. Request headers are not normalized in this case -- the request must
     * specify its own `Transfer-Encoding: chunked` header or risk the remote endpoint incorrectly
     * interpreting the entity body contents.
     * 
     * @throws \Ardent\DomainException On disconnection from the destination stream prior to completion
     * @return bool Returns TRUE on send completion or FALSE for all other states
     */
    public function send() {
        while (true) {
            switch ($this->state) {
                case self::STATE_START:
                    $this->start();
                    break;
                case self::STATE_START_LINE:
                    if ($this->startLine()) {
                        break;
                    } else {
                        break 2;
                    }
                case self::STATE_HEADERS:
                    if ($this->headers()) {
                        break;
                    } else {
                        break 2;
                    }
                case self::STATE_AWAITING_CONTINUE:
                    if ($this->awaitingContinue()) {
                        break;
                    } else {
                        break 2;
                    }
                case self::STATE_INIT_BODY:
                    $this->initBody();
                    break;
                case self::STATE_BODY:
                    if ($this->body()) {
                        break;
                    } else {
                        break 2;
                    }
                case self::STATE_BODY_STREAM:
                    if ($this->bodyStream()) {
                        break;
                    } else {
                        break 2;
                    }
                case self::STATE_BODY_STREAM_CHUNKS:
                    if ($this->bodyStreamChunks()) {
                        break;
                    } else {
                        break 2;
                    }
                case self::STATE_COMPLETE:
                    break 2;
            }
        }
        
        if ($this->state == self::STATE_COMPLETE) {
            return true;
        } elseif (!$this->isDestinationAlive()) {
            throw new DomainException(
                'Connection to destination stream lost prior to write completion'
            );
        } else {
            return false;
        }
    }
    
    private function isDestinationAlive() {
        return is_resource($this->destination) && !feof($this->destination);
    }
    
    private function start() {
        $buffer = $this->request->getStartLine() . self::CRLF;
        $this->setBuffer($buffer);
        $this->state = self::STATE_START_LINE;
    }
    
    private function setBuffer($buffer) {
        $this->buffer = $buffer;
        $this->bufferPos = 0;
        $this->bufferSize = strlen($buffer);
    }
    
    private function startLine() {
        while ($this->bufferPos < $this->bufferSize - 1) {
            if (!$this->doBufferedWrite()) {
                return false;
            }
        }
        
        $buffer = '';
        foreach ($this->request->getAllHeaders() as $header) {
            $buffer .= "$header\r\n";
        }
        $buffer .= "\r\n";
        
        $this->setBuffer($buffer);
        $this->state = self::STATE_HEADERS;
        
        return true;
    }
    
    private function doBufferedWrite() {
        $chunk = substr($this->buffer, $this->bufferPos);
        if (!$bytes = @fwrite($this->destination, $chunk)) {
            return false;
        }
        
        $dataWritten = substr($this->buffer, $this->bufferPos, $bytes);
        
        if ($this->mediator) {
            $this->mediator->notify(self::EVENT_WRITE, $this, $dataWritten, $bytes);
        }
        
        $this->bufferPos += $bytes;
        
        return $bytes;
    }
    
    private function headers() {
        while ($this->bufferPos < $this->bufferSize - 1) {
            if (!$this->doBufferedWrite()) {
                return false;
            }
        }
        
        $this->buffer = null;
        $this->bufferPos = 0;
        $this->bufferSize = null;
        
        if ($this->request->getMethod() == Request::TRACE) {
            $this->state = self::STATE_COMPLETE;
        } elseif (!$body = $this->request->getBody()) {
            $this->state = self::STATE_COMPLETE;
        } elseif ($this->request->hasHeader('Expect')) {
            $expectsContinue = false;
            
            foreach ($this->request->getHeaders('Expect') as $expectHeader) {
                /**
                 * @var \Artax\Http\Header $expectHeader
                 */
                if (!strcasecmp($expectHeader->getValue(), '100-continue')) {
                    $expectsContinue = true;
                    $this->continueDelayStartedAt = time();
                    break;
                }
            }
            
            $this->state = $expectsContinue ? self::STATE_AWAITING_CONTINUE : self::STATE_INIT_BODY;
            
        } else {
            $this->state = self::STATE_INIT_BODY;
        }
        
        return true;
    }
    
    private function awaitingContinue() {
        $now = time();
        $waitTime = $now - $this->continueDelayStartedAt;
        
        if ($waitTime > $this->attributes[self::ATTR_100_CONTINUE_DELAY]) {
            $this->state = self::STATE_INIT_BODY;
            return true;
        } else {
            return false;
        }
    }
    
    private function initBody() {
        $body = $this->request->getBody();
        $isResource = is_resource($body);
        
        if ($isResource && $this->canStreamChunks()) {
            $this->loadBodyStreamChunkBuffer();
            $this->state = self::STATE_BODY_STREAM_CHUNKS;
        } elseif ($isResource) {
            $this->loadBodyStreamBuffer();
            $this->state = self::STATE_BODY_STREAM;
        } elseif (!empty($body) || $body === '0') {
            $this->setBuffer($body);
            $this->state = self::STATE_BODY;
        }
    }
    
    private function canStreamChunks() {
        if (!$this->request->hasHeader('Transfer-Encoding')) {
            return false;
        }
        
        if (strcasecmp($this->request->getCombinedHeader('Transfer-Encoding'), 'chunked')) {
            return false;
        }
        
        if (1 == version_compare(1.1, $this->request->getProtocol())) {
            return false;
        }
        
        return true;
    }
    
    private function body() {
        while ($this->bufferPos < $this->bufferSize - 1) {
            if (!$this->doBufferedWrite()) {
                return false;
            }
        }
        
        $this->state = self::STATE_COMPLETE;
    }
    
    private function bodyStream() {
        while ($this->bufferPos < $this->bufferSize) {
            if (!$this->doBufferedWrite()) {
                return false;
            }
        }
        
        $this->loadBodyStreamBuffer();
        
        return true;
    }
    
    private function loadBodyStreamBuffer() {
        $bodyStream = $this->request->getBody();
        
        if (!feof($bodyStream)) {
            $chunk = fread($bodyStream, $this->attributes[self::ATTR_STREAM_BUFFER_SIZE]);
            $this->setBuffer($chunk);
        } else {
            $this->setBuffer(null);
            $this->state = self::STATE_COMPLETE;
        }
    }
    
    private function bodyStreamChunks() {
        while ($this->bufferPos < $this->bufferSize) {
            if (!$this->doBufferedWrite()) {
                return false;
            }
        }
        
        $this->loadBodyStreamChunkBuffer();
        
        return true;
    }
    
    private function loadBodyStreamChunkBuffer() {
        $bodyStream = $this->request->getBody();
        
        if (!feof($bodyStream)) {
            $chunk = fread($bodyStream, $this->attributes[self::ATTR_STREAM_BUFFER_SIZE]);
            $chunk = dechex(strlen($chunk)) . self::CRLF . $chunk . self::CRLF;
            $this->setBuffer($chunk);
        } elseif ($this->buffer == self::FINAL_CHUNK) {
            $this->setBuffer(null);
            $this->state = self::STATE_COMPLETE;
        } else {
            $this->setBuffer(self::FINAL_CHUNK);
        }
    }
    
    /**
     * Set optional attributes
     * 
     * @param string $attribute
     * @param mixed $value
     * @throws \Ardent\KeyException On invalid attribute
     * @throws \Ardent\DomainException If attribute assignment is attempted after write has started
     * @return void
     */
    public function setAttribute($attribute, $value) {
        if ($this->state > self::STATE_START) {
            throw new DomainException(
                'Attributes may not be altered once request write has commenced'
            );
        }
        if (!isset($this->attributes[$attribute])) {
            throw new KeyException(
                'Invalid attribute'
            );
        }
        
        $setter = 'set' . ucfirst($attribute);

        $this->$setter($value);
    }
    
    private function setAttr100ContinueDelay($seconds) {
        $seconds = filter_var($seconds, FILTER_VALIDATE_FLOAT, array(
            'options' => array(
                'default' => 3,
                'min_range' => 0.1
            )
        ));
        
        $this->attributes[self::ATTR_100_CONTINUE_DELAY] = $seconds;
    }
    
    private function setAttrStreamBufferSize($bytes) {
        $bytes = filter_var($bytes, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 8192,
                'min_range' => 1
            )
        ));
        
        $this->attributes[self::ATTR_STREAM_BUFFER_SIZE] = $bytes;
    }
}