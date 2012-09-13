<?php

namespace Artax\Network;

use Spl\Mediator,
    Spl\TypeException,
    Spl\ValueException,
    Artax\Uri;

class SocketStream implements Stream {
    
    /**
     * @var Spl\Mediator
     */
    protected $mediator;
    
    /**
     * @var Uri
     */
    private $uri;
    
    /**
     * @var resource
     */
    private $stream;
    
    /**
     * @var int
     */
    private $connectTimeout = 60;
    
    /**
     * @var int
     */
    private $lastIoActivity;
    
    /**
     * @var int
     */
    protected $connectFlags = STREAM_CLIENT_CONNECT;
    
    /**
     * @param Mediator $mediator
     * @param Uri $uri
     * @return void
     * @throws Spl\ValueException
     */
    public function __construct(Mediator $mediator, Uri $uri) {
        $this->mediator = $mediator;
        $this->uri = $uri;
        
        if (!$this->uri->wasExplicitPortSpecified()) {
            throw new ValueException(
                "Invalid URI: {$uri}; explicit port value required"
            );
        }
    }
    
    /**
     * Number of seconds until the connect() system call should timeout.
     * 
     * This parameter only applies when not making asynchronous connection attempts.
     * 
     * @param int $seconds
     * @return void
     */
    public function setConnectTimeout($seconds) {
        $this->timeout = (int) $seconds;
    }
    
    /**
     * @param int $timeout
     * @param int $flags
     * @return void
     * @throws Artax\Network\ConnectException
     */
    public function connect() {
        $stream = $this->doConnect();
        stream_set_blocking($stream, 0);
        $this->stream = $stream;
        $this->mediator->notify(self::EVENT_OPEN, $this);
    }
    
    /**
     * @return resource
     * @throws Artax\Network\ConnectException
     */
    protected function doConnect() {
        $stream = @stream_socket_client(
            $this->getUri(),
            $errNo,
            $errStr,
            $this->connectTimeout,
            $this->connectFlags
        );
        
        if (false === $stream) {
            throw new ConnectException(
                'Connection failure: ' . $this->getUri() . "; [$errNo] $errStr"
            );
        }
        
        return $stream;
    }
    
    /**
     * @return void
     */
    public function close() {
        if (!empty($this->stream)) {
            @fclose($this->stream);
            $this->stream = null;
            $this->mediator->notify(self::EVENT_CLOSE, $this);
        }
    }
    
    /**
     * @return bool
     */
    public function isConnected() {
        return !empty($this->stream);
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
    public function getStream() {
        return $this->stream;
    }
    
    /**
     * @param int $bytesToRead
     * @return string
     * @throws Artax\Network\StreamReadException
     */
    public function read($bytesToRead) {
        if (false === ($readData = @fread($this->stream, $bytesToRead))) {
            throw new StreamReadException(
                "Failed reading $bytesToRead bytes from " . $this->getUri()
            );
        } elseif (!empty($readData)) {
            $this->lastIoActivity = time();
            $this->mediator->notify(self::EVENT_READ, $this, $readData, strlen($readData));
        }
        
        return $readData;
    }
    
    /**
     * @param string $dataToWrite
     * @return int
     * @throws Artax\Network\StreamWriteException
     */
    public function write($dataToWrite) {
        $dataToWriteLength = strlen($dataToWrite);
        
        if (false === ($bytesWritten = @fwrite($this->stream, $dataToWrite))) {
            throw new StreamWriteException(
                "Failed writing $dataToWriteLength bytes to " . $this->getUri()
            );
        }
        
        if ($bytesWritten === 0) {
            return 0;
        } elseif ($bytesWritten == $dataToWriteLength) {
            $actualDataWritten = $dataToWrite;
        } else {
            $actualDataWritten = substr($dataToWrite, 0, $bytesWritten);
        }
        
        $this->lastIoActivity = time();
        $this->mediator->notify(self::EVENT_WRITE, $this, $actualDataWritten, $bytesWritten);
        
        return $bytesWritten;
    }
    
    /**
     * @return int
     */
    public function getLastActivityTimestamp() {
        return $this->lastIoActivity;
    }
    
    /**
     * @return string
     */
    public function __toString() {
        return $this->uri->__toString();
    }
    
    /**
     * @return void
     */
    public function __destruct() {
        $this->close();
    }
}
