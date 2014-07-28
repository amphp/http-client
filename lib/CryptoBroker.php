<?php

namespace Artax;

use Alert\Reactor,
    After\Failure,
    After\Success,
    After\Deferred;

class CryptoBroker {
    private $reactor;
    private $pending = [];
    private $defaultCryptoMethod;
    private $opMsCryptoTimeout = 10000;

    public function __construct(Reactor $reactor) {
        $this->reactor = $reactor;
        $this->defaultCryptoMethod = (PHP_VERSION_ID < 50600)
            ? STREAM_CRYPTO_METHOD_SSLv23_CLIENT
            : STREAM_CRYPTO_METHOD_ANY_CLIENT;
    }

    /**
     * Encrypt the specified socket with using optional stream context settings
     *
     * @param resource $socket
     * @param array $options
     * @return After\Promise
     */
    public function encrypt($socket, array $options) {
        $socketId = (int) $socket;

        if (isset($this->pending[$socketId])) {
            return new Failure(new CryptoException(
                'Cannot enable crypto: operation currently in progress for this socket'
            ));
        }

        // @TODO Check if the currently assigned crypto options match those specified
        // in the $options array. If not, disable crypto and renegotiate using the
        // new settings. For now, we'll just pretend this doesn't happen and treat
        // the original TLS negotiation as authoritative.
        if (!empty(stream_context_get_options($socket)['ssl'])) {
            return new Success($socket);
        }

        stream_context_set_option($socket, ['ssl'=> $options]);

        if ($result = $this->doEnable($socket)) {
            return new Success($socket);
        } elseif ($result === false) {
            return new Failure($this->generateErrorException());
        } else {
            return $this->enablePendingWatcher($socket);
        }
    }

    private function doEnable($socket) {
        $cryptoOpts = stream_context_get_options($socket)['ssl'];
        $cryptoMethod = empty($cryptoOpts['crypto_method'])
            ? $this->defaultCryptoMethod
            : $cryptoOpts['crypto_method'];

        return @stream_socket_enable_crypto($socket, true, $cryptoMethod);
    }

    private function enablePendingWatcher($socket) {
        $socketId = (int) $socket;
        $cryptoStruct = new CryptoStruct;
        $cryptoStruct->id = $socketId;
        $cryptoStruct->socket = $socket;
        $cryptoStruct->deferred = new Deferred;
        $cryptoStruct->pendingWatcher = $this->reactor->onWritable($socket, function() use ($cryptoStruct) {
            $socket = $cryptoStruct->socket;
            if ($result = $this->doEnable($socket)) {
                $cryptoStruct->deferred->succeed($socket);
                $this->unloadPendingStruct($cryptoStruct);
            } elseif ($result === false) {
                $cryptoStruct->deferred->fail($this->generateErrorException());
                $this->unloadPendingStruct($cryptoStruct);
            }
        });
        $cryptoStruct->timeoutWatcher = $this->reactor->once(function() use ($cryptoStruct) {
            $cryptoStruct->deferred->fail(new TimeoutException(
                sprintf('Crypto enabling timeout exceeded: %d ms', $this->opMsCryptoTimeout)
            ));
            $this->unloadPendingStruct($cryptoStruct);
        }, $this->opMsCryptoTimeout);

        $this->pending[$socketId] = $cryptoStruct;

        return $cryptoStruct->deferred;
    }

    private function generateErrorException() {
        return new CryptoException(
            sprintf('Crypto failure: %s', error_get_last()['message'])
        );
    }

    private function unloadPendingStruct(CryptoStruct $cryptoStruct) {
        $socketId = $cryptoStruct->id;
        unset($this->pending[$socketId]);
        $this->reactor->cancel($cryptoStruct->pendingWatcher);
        $this->reactor->cancel($cryptoStruct->timeoutWatcher);
    }
}
