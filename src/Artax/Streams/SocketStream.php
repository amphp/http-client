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
     * @param string $uri
     * @throws ValueException On invalid URI string
     * @return void
     */
    public function __construct($uri) {
        $this->uri = new Uri($uri);
        
        if (!$this->uri->wasExplicitPortSpecified()) {
            throw new ValueException(
                'Invalid URI: ' . $uri . ' explicit port value required'
            );
        }
    }
    
    /**
     * Specify the number of seconds before a connection attempt times out (defaults to 60)
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
     * Set optional context options to apply on the socket stream when opened
     * 
     * @param array $options
     * @return void
     */
    public function setContextOptions(array $options) {
        $this->contextOptions = $options;
    }
    
    /**
     * Open a socket stream to the URI specified in the constructor
     * 
     * @throws ConnectException On socket connect failure
     * @return void
     */
    public function open() {
        list($stream, $errNo, $errStr) = $this->doConnect(
            $this->connectFlags,
            $this->connectTimeout,
            $this->contextOptions
        );
        
        if (false !== $stream) {
            $this->resource = $stream;
            $this->activityTimestamp = microtime(true);
        } elseif ($sslError = $this->getOpenSslError()) {
            throw new ConnectException(
                'SSL Connection failure: ' . $this->getUri() . ' -- "' . $sslError . '"'
            );
        } else {
            $errorMsg = 'Connection failure: ' . $this->getUri();
            $errorMsg .= !empty($errNo) ? "; [Error# $errNo] $errStr" : '';
            throw new ConnectException($errorMsg);
        }
    }
    
    /**
     * Returns the most recent error message in the openssl error message queue
     * 
     * @return string
     */
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
     * Socket stream reads can return an empty string ad infinitum once the connection goes away.
     * When the read method returns an empty value users should utilize SocketStream::isConnected()
     * to discern whether the read actually produced no results or if the connection has died.
     * 
     * @param int $bytesToRead
     * @throws IoException
     * @return string
     */
    public function read($bytesToRead) {
        if ($readData = parent::read($bytesToRead)) {
            $bytesRecd = strlen($readData);
            $this->bytesRecd += $bytesRecd;
            $this->activityTimestamp = microtime(true);
        }
        
        return $readData;
    }
    
    /**
     * Write data to the stream resource (fwrite)
     * 
     * Note that the full string may not be written at one time when using non-blocking streams and
     * users should check the return value to ascertain how many bytes were actually written to the
     * socket stream.
     * 
     * @param string $dataToWrite
     * @throws IoException
     * @return int Returns the number of bytes actually written
     */
    public function write($dataToWrite) {
        $bytesWritten = parent::write($dataToWrite);
        $this->activityTimestamp = microtime(true);
        $this->bytesSent += $bytesWritten;
        
        return $bytesWritten;
    }
    
    /**
     * Determine if the socket connection is currently alive
     * 
     * @return bool
     */
    public function isConnected() {
        $resource = $this->getResource();
        return is_resource($resource) && !feof($resource);
    }
    
    /**
     * Get the number of bytes written to this socket since the connection was first opened
     * 
     * @return int
     */
    public function getBytesSent() {
        return $this->bytesSent;
    }
    
    /**
     * Get the number of bytes read from this socket since the connection was first opened
     * 
     * @return int
     */
    public function getBytesRecd() {
        return $this->bytesRecd;
    }
    
    /**
     * Return the micro-timestamp of the last activity on this socket
     * 
     * This value is first populated when the socket stream connection is opened. The timestamp
     * is subsequently updated on any IO read or write action.
     * 
     * @return float
     */
    public function getActivityTimestamp() {
        return $this->activityTimestamp;
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
     * @return void
     */
    public function __destruct() {
        $this->close();
    }
}
