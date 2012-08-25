<?php

namespace Artax\Http;

use Spl\Mediator,
    Artax\Http\Exceptions\ConnectException,
    Artax\Http\Exceptions\TimeoutException;

class TcpConnection implements ClientConnection {
    
    /**
     * @var Spl\Mediator
     */
    protected $mediator;
    
    
    protected $id;
    protected $host;
    protected $port;
    protected $stream;
    protected $connectFlags = STREAM_CLIENT_CONNECT;
    protected $connectTimeout = 60;
    protected $transport = 'tcp';
    protected $lastActivity;
    
    /**
     * @param Mediator $mediator
     * @param string $host
     * @param int $port
     * @return void
     */
    public function __construct(Mediator $mediator, $host, $port) {
        $this->mediator = $mediator;
        $this->host = $host;
        $this->port = $port;
        $this->id = uniqid();
    }
    
    public function setConnectTimeout($seconds) {
        $this->connectTimeout = (int) $seconds;
    }
    
    public function setConnectFlags($flags) {
        $this->connectFlags = $flags;
    }
    
    public function connect() {
        $stream = @stream_socket_client(
            $this->getUri(),
            $errNo,
            $errStr,
            $this->connectTimeout,
            $this->connectFlags
        );
        
        if ($stream) {
            stream_set_blocking($stream, 0);
            $this->stream = $stream;
            $this->lastActivity = microtime(true);
            $this->mediator->notify(Client::EVENT_CONN_OPEN, $this);
        } else {
            throw new ConnectException(
                "Connection failure: {$this->host}:{$this->port}; [$errNo] $errStr"
            );
        }
    }
    
    public function isConnected() {
        return !empty($this->stream);
    }
    
    public function close() {
        @fclose($this->stream);
        $this->stream = null;
        $this->mediator->notify(Client::EVENT_CONN_CLOSE, $this);
    }
    
    public function resetActivityTimestamp() {
        $this->lastActivity = microtime(true);
    }
    
    public function hasBeenIdleFor($secondsOfInactivity) {
        $normalized = (int) $secondsOfInactivity;
        return (microtime(true) - $this->lastActivity) > $normalized;
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function getHost() {
        return $this->host;
    }
    
    public function getPort() {
        return $this->port;
    }
    
    public function getAuthority() {
        return $this->host . ':' . $this->port;
    }
    
    public function getUri() {
        return "{$this->transport}://{$this->host}:{$this->port}/{$this->id}";
    }
    
    public function getStream() {
        return $this->stream;
    }
    
    public function writeData($data) {
        if ($bytesWritten = @fwrite($this->stream, $data)) {
            $this->lastActivity = microtime(true);
        }
        
        return $bytesWritten;
    }
    
    public function readBytes($bytes) {
        if ($readData = @fread($this->stream, $bytes)) {
            $this->lastActivity = microtime(true);
        }
        
        return $readData;
    }
    
    public function readLine() {
        if ($readLine = @fgets($this->stream)) {
            $this->lastActivity = microtime(true);
        }
        
        return $readLine;
    }
    
    public function __toString() {
        return $this->getUri();
    }
}
