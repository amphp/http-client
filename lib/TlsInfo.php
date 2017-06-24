<?php

namespace Amp\Artax;

final class TlsInfo {
    private $protocol;
    private $cipherName;
    private $cipherBits;
    private $cipherVersion;

    private function __construct(string $protocol, string $cipherName, int $cipherBits, string $cipherVersion) {
        $this->protocol = $protocol;
        $this->cipherName = $cipherName;
        $this->cipherBits = $cipherBits;
        $this->cipherVersion = $cipherVersion;
    }

    public static function fromMetaData(array $crypto): TlsInfo {
        return new self($crypto["protocol"], $crypto["cipher_name"], $crypto["cipher_bits"], $crypto["cipher_version"]);
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
}
