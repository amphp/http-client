<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Amp\CancellationToken;
use Amp\Http\Client\ParseException;
use Amp\Http\Status;

/** @internal */
final class SizeLimitingInputStream implements InputStream
{
    use ForbidSerialization;
    use ForbidCloning;

    private ?InputStream $source;

    private int $bytesRead = 0;

    private int $sizeLimit;

    private ?\Throwable $exception = null;

    public function __construct(
        InputStream $source,
        int $sizeLimit
    ) {
        $this->source = $source;
        $this->sizeLimit = $sizeLimit;
    }

    public function read(?CancellationToken $token = null): ?string
    {
        if ($this->exception) {
            throw $this->exception;
        }

        \assert($this->source !== null);

        $chunk = $this->source->read($token);

        if ($chunk !== null) {
            $this->bytesRead += \strlen($chunk);
            if ($this->bytesRead > $this->sizeLimit) {
                $this->exception = new ParseException(
                    "Configured body size exceeded: {$this->bytesRead} bytes received, while the configured limit is {$this->sizeLimit} bytes",
                    Status::PAYLOAD_TOO_LARGE
                );

                $this->source = null;
            }
        }

        return $chunk;
    }
}
