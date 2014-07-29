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
    public function enable($socket, array $options) {
        $socketId = (int) $socket;

        if (isset($this->pending[$socketId])) {
            return new Failure(new CryptoException(
                'Cannot enable crypto: operation currently in progress for this socket'
            ));
        }

        $existingContext = @stream_context_get_options($socket)['ssl'];

        if ($existingContext && ($existingContext == $options)) {
            return new Success($socket);
        } elseif ($existingContext) {
            // If crypto was previously enabled for this socket we need to disable
            // it before we can negotiate the new options.
            return $this->renegotiate($socket, $options);
        }

        if (PHP_VERSION_ID < 50600 && isset($options['peer_name'])) {
            $options['CN_match'] = $options['peer_name'];
        }

        stream_context_set_option($socket, ['ssl'=> $options]);

        if ($result = $this->doEnable($socket)) {
            return new Success($socket);
        } elseif ($result === false) {
            return new Failure($this->generateErrorException());
        } else {
            return $this->watch($socket, 'doEnable');
        }
    }

    private function renegotiate($socket, $options) {
        $deferred = new Deferred;
        $deferredDisable = $this->disable($socket);
        $deferredDisable->onResolve(function($error, $result) use ($deferred, $options) {
            if ($error) {
                $deferred->fail(new CryptoException(
                    'Failed renegotiating crypto',
                    0,
                    $error
                ));
            } else {
                $deferredEnable = $this->encrypt($result, $options);
                $deferredEnable->onResolve(function($error, $result) use ($deferred) {
                    return $error ? $deferred->fail($error) : $deferred->succeed($result);
                });
            }
        });

        return $deferred->promise();
    }

    private function doEnable($socket) {
        $cryptoOpts = stream_context_get_options($socket)['ssl'];
        $cryptoMethod = empty($cryptoOpts['crypto_method'])
            ? $this->defaultCryptoMethod
            : $cryptoOpts['crypto_method'];

        return @stream_socket_enable_crypto($socket, true, $cryptoMethod);
    }

    private function generateErrorException() {
        return new CryptoException(
            sprintf('Crypto error: %s', error_get_last()['message'])
        );
    }

    private function watch($socket, $func) {
        $socketId = (int) $socket;
        $cryptoStruct = new CryptoStruct;
        $cryptoStruct->id = $socketId;
        $cryptoStruct->socket = $socket;
        $cryptoStruct->deferred = new Deferred;
        $cryptoStruct->pendingWatcher = $this->reactor->onWritable($socket, function() use ($cryptoStruct, $func) {
            $socket = $cryptoStruct->socket;
            if ($result = $this->{$func}($socket)) {
                $cryptoStruct->deferred->succeed($socket);
                $this->unloadPendingStruct($cryptoStruct);
            } elseif ($result === false) {
                $cryptoStruct->deferred->fail($this->generateErrorException());
                $this->unloadPendingStruct($cryptoStruct);
            }
        });
        $cryptoStruct->timeoutWatcher = $this->reactor->once(function() use ($cryptoStruct) {
            $cryptoStruct->deferred->fail(new TimeoutException(
                sprintf('Crypto timeout exceeded: %d ms', $this->opMsCryptoTimeout)
            ));
            $this->unloadPendingStruct($cryptoStruct);
        }, $this->opMsCryptoTimeout);

        $this->pending[$socketId] = $cryptoStruct;

        return $cryptoStruct->deferred;
    }

    private function unloadPendingStruct(CryptoStruct $cryptoStruct) {
        $socketId = $cryptoStruct->id;
        unset($this->pending[$socketId]);
        $this->reactor->cancel($cryptoStruct->pendingWatcher);
        $this->reactor->cancel($cryptoStruct->timeoutWatcher);
    }

    /**
     *
     */
    public function disable($socket) {
        $socketId = (int) $socket;

        if (isset($this->pending[$socketId])) {
            return new Failure(new CryptoException(
                'Cannot disable crypto: operation currently in progress for this socket'
            ));
        }

        // @TODO This may be unnecessary. Decide if it is.
        if (!@stream_context_get_options($socket)['ssl']) {
            // If crypto is already disabled we're finished here
            return new Success($socket);
        } elseif ($result = $this->doDisable($socket)) {
            return new Success($socket);
        } elseif ($result === false) {
            return new Failure($this->generateErrorException());
        } else {
            return $this->watch($socket, 'doDisable');
        }
    }

    private function doDisable($socket) {
        return @stream_socket_enable_crypto($socket, false);
    }

}






























