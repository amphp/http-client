<?php

namespace Artax;

class ClientState {
    
    public $state = Client::STATE_NEEDS_SOCKET;
    public $headerBytesSent = 0;
    public $bodyBytesSent = 0;
    public $bytesRecd = 0;
    public $buffer = '';
    public $responseBodyStream = null;
    
    public $streamRequestBodyPos = 0;
    public $streamRequestBodyLength = 0;
    public $streamRequestBodyChunk = '';
    public $streamRequestBodyChunkPos = 0;
    public $streamRequestBodyChunkLength = 0;
    public $streamRequestBodyChunkRawLength = 0;
}