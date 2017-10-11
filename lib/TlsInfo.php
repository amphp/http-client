<?php

namespace Amp\Artax;

use Kelunik\Certificate\Certificate;

/**
 * Exposes a connection's negotiated TLS parameters.
 */
final class TlsInfo {
    private $protocol;
    private $cipherName;
    private $cipherBits;
    private $cipherVersion;
    private $certificates;
    private $parsedCertificates;

    private function __construct(string $protocol, string $cipherName, int $cipherBits, string $cipherVersion, array $certificates) {
        $this->protocol = $protocol;
        $this->cipherName = $cipherName;
        $this->cipherBits = $cipherBits;
        $this->cipherVersion = $cipherVersion;
        $this->certificates = $certificates;
    }

    /**
     * Constructs a new instance from PHP's internal info.
     *
     * Always pass the info as obtained from PHP as this method might extract additional fields in the future.
     *
     * @param array $cryptoInfo Crypto info obtained via `stream_get_meta_data($socket->getResource())["crypto"]`.
     * @param array $tlsContext Context obtained via `stream_context_get_options($socket->getResource())["ssl"])`.
     *
     * @return TlsInfo
     */
    public static function fromMetaData(array $cryptoInfo, array $tlsContext): TlsInfo {
        return new self(
            $cryptoInfo["protocol"],
            $cryptoInfo["cipher_name"],
            $cryptoInfo["cipher_bits"],
            $cryptoInfo["cipher_version"],
            array_merge([$tlsContext["peer_certificate"]] ?: [], $tlsContext["peer_certificate_chain"] ?? [])
        );
    }

    public function getProtocol(): string {
        return $this->protocol;
    }

    public function getCipherName(): string {
        return $this->cipherName;
    }

    public function getCipherBits(): int {
        return $this->cipherBits;
    }

    public function getCipherVersion(): string {
        return $this->cipherVersion;
    }

    /** @return Certificate[] */
    public function getPeerCertificates(): array {
        if ($this->parsedCertificates === null) {
            $this->parsedCertificates = array_map(function ($resource) {
                return new Certificate($resource);
            }, $this->certificates);
        }

        return $this->parsedCertificates;
    }
}
