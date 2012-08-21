<?php

namespace Artax\Http;

use Artax\Http\Exceptions\ConnectException,
    Artax\Http\Exceptions\TimeoutException;

class TcpConnection implements ClientConnection {
    
    protected $id;
    protected $authority;
    protected $stream;
    protected $inUse = false;
    protected $connectFlags = STREAM_CLIENT_CONNECT;
    protected $connectTimeout = 60;
    protected $activityTimeout = 60;
    protected $transport = 'tcp';
    protected $lastActivity;
    
    public function __construct($authority) {
        $this->id = uniqid();
        $this->authority = $authority;
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
        } else {
            throw new ConnectException(
                "Connection to {$this->authority} failed: [Error $errNo] $errStr"
            );
        }
    }
    
    public function getUri() {
        return "{$this->transport}://{$this->authority}/{$this->id}";
    }
    
    public function isInUse() {
        return $this->inUse;
    }
    
    public function isConnected() {
        return $this->stream;
    }
    
    public function close() {
        @fclose($this->stream);
        $this->stream = null;
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function getAuthority() {
        return $this->authority;
    }
    
    public function getStream() {
        return $this->stream;
    }
    
    public function setConnectTimeout($seconds) {
        $this->connectTimeout = (int) $seconds;
    }
    
    public function setActivityTimeout($seconds) {
        $this->activityTimeout = (int) $seconds;
    }
    
    public function setConnectFlags($flags) {
        $this->connectFlags = $flags;
    }
    
    public function setInUseFlag($inUseFlag) {
        $this->inUse = (bool) $inUseFlag;
    }
    
    public function resetActivityTimeout() {
        $this->lastActivity = microtime(true);
    }
    
    public function hasTimedOut() {
        return (microtime(true) - $this->lastActivity) > $this->activityTimeout;
    }
    
    public function writeData($data) {
        if ($bytesWritten = @fwrite($this->stream, $data)) {
            $this->lastActivity = microtime(true);
        } elseif ($this->hasTimedOut()) {
            throw new TimeoutException();
        }
        
        
        return $bytesWritten; 
    }
    
    public function readBytes($bytes) {
        if ($readData = @fread($this->stream, $bytes)) {
            $this->lastActivity = microtime(true);
        } elseif ($this->hasTimedOut()) {
            throw new TimeoutException();
        }
        
        return $readData;
    }
    
    public function readLine() {
        if ($readLine = @fgets($this->stream)) {
            $this->lastActivity = microtime(true);
        } elseif ($this->hasTimedOut()) {
            throw new TimeoutException();
        }
        
        return $readLine;
    }
    
    public function __toString() {
        return $this->getUri();
    }
}
