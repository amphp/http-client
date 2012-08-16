<?php

namespace Artax\Http;

use RuntimeException,
    Artax\Http\Exceptions\ConnectException;

class SslConnection extends Connection {
    
    protected $transport = 'ssl';
    protected $sslOptions = array();
    protected $openSslStatus;
    
    public function connect($flags = STREAM_CLIENT_CONNECT) {
        if (is_null($this->openSslStatus)) {
            $this->openSslStatus = $this->isOpenSslLoaded();
        }
        
        if (!$this->openSslStatus) {
            throw new RuntimeException(
                'openssl extension is required to originate SSL requests'
            );
        }
        
        $uri = $this->getUri();
        $ctx = stream_context_create(array('ssl' => $this->sslOptions));
        $stream = @stream_socket_client($uri, $errNo, $errStr, $this->connectTimeout, $flags, $ctx);
        
        if ($stream) {
            stream_set_blocking($stream, 0);
            $this->stream = $stream;
        } else {
            throw new ConnectException(
                "Connection to {$this->authority} failed: [Error $errNo] $errStr"
            );
        }
    }
    
    protected function isOpenSslLoaded() {
        return extension_loaded('openssl');
    }
    
    public function setSslOptions(array $options) {
        $this->sslOptions = $options;
    }
}
