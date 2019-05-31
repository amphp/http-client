<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\Parser;

final class RequestOptions
{
    private $transferTimeout = 10000;
    private $discardBody = false;
    private $maxBodyBytes = Parser::DEFAULT_MAX_BODY_BYTES;
    private $maxHeaderBytes = Parser::DEFAULT_MAX_HEADER_BYTES;

    public function __construct()
    {
        // default
    }

    public function getTransferTimeout(): int
    {
        return $this->transferTimeout;
    }

    public function isDiscardBody(): bool
    {
        return $this->discardBody;
    }

    public function getBodySizeLimit(): int
    {
        return $this->maxBodyBytes;
    }

    public function getHeaderSizeLimit(): int
    {
        return $this->maxHeaderBytes;
    }

    public function withHeaderSizeLimit(int $maxHeaderBytes): self
    {
        $clone = clone $this;
        $clone->maxHeaderBytes = $maxHeaderBytes;

        return $clone;
    }

    public function withBodySizeLimit(int $maxBodyBytes): self
    {
        $clone = clone $this;
        $clone->maxBodyBytes = $maxBodyBytes;

        return $clone;
    }

    public function withBodyDiscarding(): self
    {
        $clone = clone $this;
        $clone->discardBody = true;

        return $clone;
    }

    public function withoutBodyDiscarding(): self
    {
        $clone = clone $this;
        $clone->discardBody = false;

        return $clone;
    }

    public function withTransferTimeout(int $transferTimeout): self
    {
        $clone = clone $this;
        $clone->transferTimeout = $transferTimeout;

        return $clone;
    }
}
