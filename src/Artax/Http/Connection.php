<?php

namespace Artax\Http;

use Artax\Http\Exceptions\ConnectException;

class Connection {
    
    protected $id;
    protected $authority;
    protected $stream;
    protected $inUse = false;
    protected $closed = false;
    protected $connectTimeout = 30;
    protected $transport = 'tcp';
    
    public function __construct($authority) {
        $this->id = uniqid();
        $this->authority = $authority;
    }
    
    public function connect($flags = STREAM_CLIENT_CONNECT) {
        $uri = $this->getUri();
        $stream = @stream_socket_client($uri, $errNo, $errStr, $this->connectTimeout, $flags);
        
        if ($stream) {
            stream_set_blocking($stream, 0);
            $this->stream = $stream;
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
        return $this->stream && !$this->closed;
    }
    
    public function close() {
        @fclose($this->stream);
        $this->closed = true;
    }
    
    public function writeData($data) {
        return @fwrite($this->stream, $data);
    }
    
    public function readBytes($bytes) {
        return @fread($this->stream, $bytes);
    }
    
    public function readLine() {
        return @fgets($this->stream);
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
    
    public function setInUseFlag($inUseFlag) {
        $this->inUse = (bool) $inUseFlag;
    }
    
    public function __toString() {
        return $this->getUri();
    }
}
