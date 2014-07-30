<?php

namespace Artax;

use Alert\Reactor,
    After\Failure,
    After\Success,
    After\Deferred;

class CryptoBroker {
    private $reactor;
    private $pending = [];
    private $isLegacy;
    private $defaultCryptoMethod;
    private $opMsCryptoTimeout = 10000;

    public function __construct(Reactor $reactor) {
        $this->reactor = $reactor;
        $this->isLegacy = $isLegacy = (PHP_VERSION_ID < 50600);
        $this->defaultCryptoMethod = $isLegacy
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

        if ($this->isLegacy) {
            // For pre-5.6 we always manually verify names in userland using the captured
            // peer certificate
            $options['capture_peer_cert'] = true;
            if (isset($options['CN_match'])) {
                $options['peer_name'] = $options['CN_match'];
                unset($options['CN_match']);
            }
        }

        $existingContext = @stream_context_get_options($socket)['ssl'];

        if ($existingContext && ($existingContext == $options)) {
            return new Success($socket);
        } elseif ($existingContext) {
            // If crypto was previously enabled for this socket we need to disable
            // it before we can negotiate the new options.
            return $this->renegotiate($socket, $options);
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
        return $this->isLegacy
            ? $this->doLegacyEnable($socket)
            : @stream_socket_enable_crypto($socket, true);
    }

    private function doLegacyEnable($socket) {
        $cryptoOpts = stream_context_get_options($socket)['ssl'];

        $cryptoMethod = empty($cryptoOpts['crypto_method'])
            ? $this->defaultCryptoMethod
            : $cryptoOpts['crypto_method'];

        $peerName = isset($cryptoOpts['peer_name'])
            ? $cryptoOpts['peer_name']
            : null;

        $peerFingerprint = isset($cryptoOpts['peer_fingerprint'])
            ? $cryptoOpts['peer_fingerprint']
            : null;

        // If PHP's internal verification routines return false or zero we're finished
        if (!$result = @stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            return $result;
        }

        $cert = stream_context_get_options($socket)['ssl']['peer_certificate'];
        $certInfo = openssl_x509_parse($cert);

        if ($peerFingerprint && !$this->legacyVerifyPeerFingerprint($peerFingerprint, $cert)) {
            @trigger_error('Peer fingerprint verification failed', E_USER_WARNING);
            return false;
        }

        if ($peerName && !$this->legacyVerifyPeerName($peerName, $certInfo)) {
            @trigger_error('Peer name verification failed', E_USER_WARNING);
            return false;
        }

        return true;
    }

    private function legacyVerifyPeerFingerprint($peerFingerprint, $cert) {
        if (is_string($peerFingerprint)) {
            $peerFingerprint = [$peerFingerprint];
        } elseif (!is_array($peerFingerprint)) {
            @trigger_error(
                sprintf('Invalid peer_fingerprint; string or array required (%s)', gettype($peerFingerprint)),
                E_USER_WARNING
            );
            return false;
        }

        if (!openssl_x509_export($cert, $str, false)) {
            @trigger_error('Failed exporting peer cert for fingerprint verification', E_USER_WARNING);
            return false;
        }

        if (!preg_match("/-+BEGIN CERTIFICATE-+(.+)-+END CERTIFICATE-+/s", $str, $matches)) {
            @trigger_error('Failed parsing cert PEM for fingerprint verification', E_USER_WARNING);
            return false;
        }

        $pem = $matches[1];
        $pem = base64_decode($pem);

        foreach ($peerFingerprint as $expectedFingerprint) {
            $algo = (strlen($expectedFingerprint) === 40) ? 'sha1' : 'md5';
            $actualFingerprint = openssl_digest($pem, $algo);
            if ($expectedFingerprint === $actualFingerprint) {
                return true;
            }
        }

        return false;
    }

    private function legacyVerifyPeerName($peerName, array $certInfo) {
        if ($this->matchesWildcardName($peerName, $certInfo['subject']['CN'])) {
            return true;
        }

        if (empty($certInfo['extensions']['subjectAltName'])) {
            return false;
        }

        $subjectAltNames = array_map('trim', explode(',', $certInfo['extensions']['subjectAltName']));

        foreach ($subjectAltNames as $san) {
            if (stripos($san, 'DNS:') !== 0) {
                continue;
            }
            $sanName = substr($san, 4);

            if ($this->matchesWildcardName($peerName, $sanName)) {
                return true;
            }
        }

        return false;
    }

    private function matchesWildcardName($peerName, $certName) {
        if (strcasecmp($peerName, $certName) === 0) {
            return true;
        }
        if (!(stripos($certName, '*.') === 0 && stripos($peerName, '.'))) {
            return false;
        }
        $certName = substr($certName, 2);
        $peerName = explode(".", $peerName);
        unset($peerName[0]);
        $peerName = implode(".", $peerName);

        return ($peerName == $certName);
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
