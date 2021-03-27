<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Amp\Failure;
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

    public function read(): ?string
    {
        if ($this->exception) {
            return new Failure($this->exception);
        }

        \assert($this->source !== null);

        $chunk = $this->source->read();

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
