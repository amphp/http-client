<?php

namespace Artax\Streams;

use Spl\Mediator,
    Spl\TypeException,
    Spl\ValueException,
    Artax\Uri;

class SocketStream implements Stream {
    
    /**
     * @var Uri
     */
    private $uri;
    
    /**
     * @var int
     */
    private $activityTimestamp;
    
    /**
     * @var int
     */
    private $bytesSent = 0;
    
    /**
     * @var int
     */
    private $bytesRecd = 0;
    
    /**
     * @var Spl\Mediator
     */
    private $mediator;
    
    /**
     * @var resource
     */
    private $resource;
    
    /**
     * @var int
     */
    protected $defaultConnectTimeout = 60;
    
    /**
     * @param Mediator $mediator
     * @param mixed $uri
     * @return void
     * @throws Spl\ValueException
     */
    public function __construct(Mediator $mediator, $uri) {
        $this->mediator = $mediator;
        $this->uri = $uri instanceof Uri ? $uri : new Uri($uri);
        
        if (!$this->uri->wasExplicitPortSpecified()) {
            throw new ValueException(
                "Invalid URI: {$uri}; explicit port value required"
            );
        }
    }
    
    /**
     * Make socket connection
     * 
     * @param int $flagBitmask
     * @param int $timeout
     * @param array $contextOptions
     * @return resource
     * @throws Artax\Streams\ConnectException
     */
    public function connect(
        $flagBitmask = STREAM_CLIENT_CONNECT,
        $timeout = 60,
        array $contextOptions = array()
    ) {
        $flagBitmask = empty($flagBitmask) ? STREAM_CLIENT_CONNECT : (int) $flagBitmask;
        $timeout = empty($timeout) ? $this->defaultConnectTimeout : (int) $timeout;
        
        list($stream, $errNo, $errStr) = $this->doConnect($flagBitmask, $timeout, $contextOptions);
        
        if (false !== $stream) {
            $this->resource = $stream;
            $this->activityTimestamp = microtime(true);
            $this->mediator->notify(self::EVENT_OPEN, $this);
            return $stream;
        } else {
            $errorMsg = 'Connection failure: ' . $this->getUri();
            $errorMsg .= !empty($errNo) ? "; [Error# $errNo] $errStr" : '';
            throw new ConnectException($errorMsg);
        }
    }
    
    /**
     * A test "seam" for mocking stream_socket_client results
     * 
     * @param int $flagBitmask
     * @param int $timeout
     * @param array $contextOptions
     * @return array
     */
    protected function doConnect($flagBitmask, $timeout, $contextOptions) {
        $context = stream_context_create($contextOptions);
        
        $stream = @stream_socket_client(
            $this->getUri(),
            $errNo,
            $errStr,
            $timeout,
            $flagBitmask,
            $context
        );
        
        // stream_socket_client is stupid and modifies $errNo + $errStr by reference, so if we
        // don't want to throw an exception here on connection failures we need to return these
        // values along with the $stream return value.
        return array($stream, $errNo, $errStr);
    }
    
    /**
     * @return void
     */
    public function close() {
        if (!empty($this->resource)) {
            @fclose($this->resource);
            $this->resource = null;
            $this->mediator->notify(self::EVENT_CLOSE, $this);
        }
    }
    
    /**
     * @param int $bytesToRead
     * @return string
     * @throws Artax\Streams\IoException
     */
    public function read($bytesToRead) {
        $readData = $this->doRead($bytesToRead);
        
        if (false === $readData) {
            throw new IoException(
                "Failed reading $bytesToRead bytes from " . $this->getUri()
            );
        } elseif (!empty($readData)) {
            $bytesRecd = strlen($readData);
            $this->bytesRecd += $bytesRecd;
            $this->activityTimestamp = microtime(true);
            $this->mediator->notify(self::EVENT_READ, $this, $readData, $bytesRecd);
        }
        
        return $readData;
    }
    
    /**
     * A test "seam" for mocking fread results
     * 
     * @return mixed Returns read data or FALSE on error
     */
    protected function doRead($bytes) {
        return @fread($this->resource, $bytes);
    }
    
    /**
     * @param string $dataToWrite
     * @return int
     * @throws Artax\Streams\IoException
     */
    public function write($dataToWrite) {
        $bytesWritten = $this->doWrite($dataToWrite);
        $dataToWriteLength = strlen($dataToWrite);
        
        if (false === $bytesWritten) {
            throw new IoException(
                "Failed writing $dataToWriteLength bytes to " . $this->getUri()
            );
        }
        
        $this->bytesSent += $bytesWritten;
        
        if ($bytesWritten == $dataToWriteLength) {
            $actualDataWritten = $dataToWrite;
        } else {
            $actualDataWritten = substr($dataToWrite, 0, $bytesWritten);
        }
        
        $this->activityTimestamp = microtime(true);
        $this->mediator->notify(self::EVENT_WRITE, $this, $actualDataWritten, $bytesWritten);
        
        return $bytesWritten;
    }
    
    /**
     * A test "seam" for mocking fwrite results
     * 
     * @return mixed Returns the number of bytes written or FALSE on error
     */
    protected function doWrite($data) {
        return @fwrite($this->resource, $data);
    }
    
    /**
     * @return bool
     */
    public function isConnected() {
        return !empty($this->resource);
    }
    
    /**
     * @return string
     */
    public function getScheme() {
        return $this->uri->getScheme();
    }
    
    /**
     * @return string
     */
    public function getHost() {
        return $this->uri->getHost();
    }
    
    /**
     * @return int
     */
    public function getPort() {
        return $this->uri->getPort();
    }
    
    /**
     * @return string
     */
    public function getAuthority() {
        return $this->uri->getAuthority();
    }
    
    /**
     * @return string
     */
    public function getPath() {
        return $this->uri->getPath();
    }
    
    /**
     * @return string
     */
    public function getUri() {
        return $this->uri->__toString();
    }
    
    /**
     * @return resource
     */
    public function getResource() {
        return $this->resource;
    }
    
    /**
     * @return int
     */
    public function getActivityTimestamp() {
        return $this->activityTimestamp;
    }
    
    /**
     * @return int
     */
    public function getBytesSent() {
        return $this->bytesSent;
    }
    
    /**
     * @return int
     */
    public function getBytesRecd() {
        return $this->bytesRecd;
    }
    
    /**
     * @return void
     */
    public function __destruct() {
        $this->close();
    }
}
