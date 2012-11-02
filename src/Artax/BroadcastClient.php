<?php

namespace Artax;

use Exception,
    Spl\Mediator;

class BroadcastClient extends Client {
    
    const EVENT_IO_READ = 'artax.client.socket.io.read';
    const EVENT_IO_WRITE = 'artax.client.socket.io.write';
    const EVENT_REQUEST = 'artax.client.request';
    const EVENT_REDIRECT = 'artax.client.redirect';
    const EVENT_RESPONSE = 'artax.client.response';
    
    /**
     * @var \Spl\Mediator
     */
    private $mediator;

    /**
     * @param \Spl\Mediator $mediator
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    protected function buildRequestMaps($requests) {
        parent::buildRequestMaps($requests);
        
        foreach ($this->requests as $requestKey => $request) {
            $this->doRequestNotify($requestKey, $request);
        }
    }
    
    /**
     * Notify listeners as new Requests are ready to be sent
     * 
     * @param string $requestKey
     * @param Http\Request $request
     * @throws ClientException (only when not in multi-mode)
     * @return void
     */
    private function doRequestNotify($requestKey, $request) {
        try {
            $this->mediator->notify(
                self::EVENT_REQUEST,
                $requestKey,
                $request
            );
        } catch (Exception $e) {
            $listenerException = new ClientException(
                'An event listener threw an exception while responding to EVENT_IO_WRITE',
                0,
                $e
            );
            if ($this->isInMultiMode) {
                $this->errors[$requestKey] = $listenerException;
            } else {
                throw $listenerException;
            }
        }
    }
    
    /**
     * Write data to the specified socket, notifying listeners of the result
     * 
     * @param resource $socket
     * @param string $dataToWrite
     * @return int Returns number of bytes written or FALSE on failure
     */
    protected function doSockWrite($socket, $dataToWrite) {
        if ($bytesWritten = parent::doSockWrite($socket, $dataToWrite)) {
            $socketId = (int) $socket;
            $requestKey = $this->socketIdRequestKeyMap[$socketId];
            
            $actualDataWritten = substr($dataToWrite, 0, $bytesWritten);
            $this->doWriteNotify($requestKey, $actualDataWritten, $bytesWritten);
        }
        
        return $bytesWritten;
    }
    
    /**
     * Notify event listeners of new data written to the socket
     * 
     * @param string $requestKey
     * @param string $writeData
     * @param int $writeDataLength
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function doWriteNotify($requestKey, $writeData, $writeDataLength) {
        try {
            $this->mediator->notify(
                self::EVENT_IO_WRITE,
                $requestKey,
                $writeData,
                $writeDataLength,
                $this->requestStatistics[$requestKey]
            );
        } catch (Exception $e) {
            $listenerException = new ClientException(
                'An event listener threw an exception while responding to EVENT_IO_WRITE',
                0,
                $e
            );
            if ($this->isInMultiMode) {
                $this->errors[$requestKey] = $listenerException;
            } else {
                throw $listenerException;
            }
        }
    }
    
    /**
     * Read data from the specified socket, notifying listeners of the result
     * 
     * @param resource $socket
     * @return array(mixed $readData, int $readDataLength)
     */
    protected function doSockRead($socket) {
        list($readData, $readDataLength) = parent::doSockRead($socket);
        
        // The read length in bytes must be used to test for empty reads because "empty" read data
        // (such as the one-byte string, "0") can yield false positives.
        if ($readDataLength) {
            $socketId = (int) $socket;
            $requestKey = $this->socketIdRequestKeyMap[$socketId];
            $this->doReadNotify($requestKey, $readData, $readDataLength);
        }
        
        return array($readData, $readDataLength);
    }
    
    /**
     * Notify event listeners of new data read from the socket
     * 
     * @param string $requestKey
     * @param string $readData
     * @param int $readDataLength
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function doReadNotify($requestKey, $readData, $readDataLength) {
        try {
            $this->mediator->notify(
                self::EVENT_IO_READ,
                $requestKey,
                $readData,
                $readDataLength,
                $this->requestStatistics[$requestKey]
            );
        } catch (Exception $e) {
            $listenerException = new ClientException(
                'An event listener threw an exception while responding to EVENT_IO_READ',
                0,
                $e
            );
            if ($this->isInMultiMode) {
                $this->errors[$requestKey] = $listenerException;
            } else {
                throw $listenerException;
            }
        }
    }
    
    /**
     * Notify listeners on response completion
     * 
     * @param string $requestKey
     * @return void
     */
    protected function completeResponse($requestKey) {
        parent::completeResponse($requestKey);
        
        // make sure the parent function didn't redirect the request
        if ($this->isComplete($requestKey)) {
            $this->doResponseNotify($requestKey);
        }
    }
    
    /**
     * Notify event listeners upon response completion
     * 
     * @param string $requestKey
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function doResponseNotify($requestKey) {
        try {
            $this->mediator->notify(
                self::EVENT_RESPONSE,
                $requestKey,
                $this->responses[$requestKey],
                $this->requestStatistics[$requestKey]
            );
        } catch (Exception $e) {
            $listenerException = new ClientException(
                'An event listener threw an exception while responding to EVENT_RESPONSE',
                0,
                $e
            );
            if ($this->isInMultiMode) {
                $this->errors[$requestKey] = $listenerException;
            } else {
                throw $listenerException;
            }
        }
    }

    /**
     * Generate a new set of request map values so we can follow the response's Location header
     * 
     * @param string $requestKey
     * @return void
     */
    protected function doRedirect($requestKey) {
        parent::doRedirect($requestKey);
        $this->doRedirectNotify($requestKey);
    }
    
    /**
     * Notify event listeners that a redirect has occurred
     * 
     * @param string $requestKey
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function doRedirectNotify($requestKey) {
        try {
            /**
             * @var \Artax\Http\ChainableResponse $response
             */
            $response = $this->responses[$requestKey];
            $previousResponse = $response->getPreviousResponse();
            
            $this->mediator->notify(
                self::EVENT_REDIRECT,
                $requestKey,
                $previousResponse,
                $this->requestStatistics[$requestKey]
            );
        } catch (Exception $e) {
            $listenerException = new ClientException(
                'An event listener threw an exception while responding to EVENT_REDIRECT',
                0,
                $e
            );
            if ($this->isInMultiMode) {
                $this->errors[$requestKey] = $listenerException;
            } else {
                throw $listenerException;
            }
        }
    }
}