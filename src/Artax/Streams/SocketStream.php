<?php

namespace Artax\Streams;

use Artax\Uri,
    Spl\ValueException;

class SocketStream extends Stream implements SocketResource {
    
    /**
     * @var resource
     */
    protected $resource;
    
    /**
     * @var Uri
     */
    private $uri;
    
    /**
     * @var int
     */
    private $connectTimeout = 60;
    
    /**
     * @var int
     */
    private $connectFlags = STREAM_CLIENT_CONNECT;
    
    /**
     * @var array
     */
    private $contextOptions = array();
    
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
     * @param mixed $uri
     * @throws ValueException
     * @return void
     */
    public function __construct($uri) {
        $this->uri = $uri instanceof Uri ? $uri : new Uri($uri);
        
        if (!$this->uri->wasExplicitPortSpecified()) {
            throw new ValueException(
                "Invalid URI: {$uri}; explicit port value required"
            );
        }
    }
    
    /**
     * 
     * 
     * @param int $seconds
     * @return void
     */
    public function setConnectTimeout($seconds) {
        $this->connectTimeout = filter_var($seconds, FILTER_VALIDATE_INT);
    }
    
    /**
     * Bitmask field which may be set to any combination of connection flags.
     * 
     * Currently the select of connection flags is limited to STREAM_CLIENT_CONNECT (default),
     * STREAM_CLIENT_ASYNC_CONNECT and STREAM_CLIENT_PERSISTENT.
     * 
     * @param int $flagBitmask
     * @return void
     */
    public function setConnectFlags($flagBitmask) {
        $this->connectFlags = filter_var($flagBitmask, FILTER_VALIDATE_INT);
    }
    
    /**
     * @param array $options
     * @return void
     */
    public function setContextOptions(array $options) {
        $this->contextOptions = $options;
    }
    
    /**
     * Open the socket connection (stream_socket_client)
     * 
     * @throws ConnectException
     * @return void
     */
    public function open() {
        $this->clearSslErrorBuffer();
        
        list($stream, $errNo, $errStr) = $this->doConnect(
            $this->connectFlags,
            $this->connectTimeout,
            $this->contextOptions
        );
        
        if (false !== $stream) {
            $this->resource = $stream;
            $this->activityTimestamp = microtime(true);
        } elseif ($sslError = $this->getOpenSslError()) {
            throw new SslConnectException(
                'Connection failure: ' . $this->getUri() . '. OpenSSL error: "' . $sslError . '"'
            );
        } else {
            $errorMsg = 'Connection failure: ' . $this->getUri();
            $errorMsg .= !empty($errNo) ? "; [Error# $errNo] $errStr" : '';
            throw new ConnectException($errorMsg);
        }
    }
    
    protected function clearSslErrorBuffer() {
        while ($err = openssl_error_string());
    }
    
    protected function getOpenSslError() {
        if ($tmpSslError = openssl_error_string()) {
            $sslError = $tmpSslError;
            while ($tmpSslError = openssl_error_string()) {
                $sslError = $tmpSslError;
            }
            return $sslError;
        } else {
            return null;
        }
    }
    
    /**
     * A test seam for mocking stream_socket_client results
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
        
        // stream_socket_client modifies $errNo + $errStr by reference, so if we don't want to 
        // throw an exception here on connection failures we need to return these values along with
        // the $stream return value.
        return array($stream, $errNo, $errStr);
    }
    
    /**
     * Read data from the stream resource (fread)
     * 
     * @param int $bytesToRead
     * @throws IoException
     * @throws SocketDisconnectedException
     * @return string
     */
    public function read($bytesToRead) {
        if ($readData = parent::read($bytesToRead)) {
            $bytesRecd = strlen($readData);
            $this->bytesRecd += $bytesRecd;
            $this->activityTimestamp = microtime(true);
        } elseif ('' === $readData && feof($this->getResource())) {
            throw new SocketDisconnectedException(
                'The connection to ' . $this->getHost() . ' has gone away'
            );
        }
        
        return $readData;
    }
    
    /**
     * Write data to the stream resource (fwrite)
     * 
     * @param string $dataToWrite
     * @return int
     * @throws IoException
     */
    public function write($dataToWrite) {
        $bytesWritten = parent::write($dataToWrite);
        $this->activityTimestamp = microtime(true);
        $this->bytesSent += $bytesWritten;
        
        return $bytesWritten;
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
     * @return int
     */
    public function getActivityTimestamp() {
        return $this->activityTimestamp;
    }
    
    /**
     * @return void
     */
    public function __destruct() {
        $this->close();
    }
}
