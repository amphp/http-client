<?php

namespace Artax\Streams;

use Spl\Mediator,
    Spl\ValueException,
    Artax\Uri;

class SslSocketStream extends SocketStream {
    
    /**
     * @var array
     */
    private $sslOptions;
    
    /**
     * @param Mediator $mediator
     * @param Uri $uri
     * @param array $sslOptions
     * @return void
     * @throws Spl\ValueException
     */
    public function __construct(Mediator $mediator, Uri $uri, array $sslOptions) {
        if (strcmp($uri->getScheme(), 'ssl')) {
            throw new ValueException();
        }
        
        parent::__construct($mediator, $uri);
        $this->sslOptions = $sslOptions;
    }
    
    /**
     * @return resource
     * @throws Artax\Streams\ConnectException
     */
    protected function doConnect() {
        $sslContext = stream_context_create(array('ssl' => $this->sslOptions));
        
        $stream = @stream_socket_client(
            $this->getUri(),
            $errNo,
            $errStr,
            $this->connectTimeout,
            $this->connectFlags,
            $sslContext
        );
        
        if (false === $stream) {
            throw new ConnectException(
                'Connection failure: ' . $this->getUri() . "; [$errNo] $errStr"
            );
        }
        
        return $stream;
    }
}
