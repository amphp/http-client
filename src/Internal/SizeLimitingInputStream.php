<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Amp\Failure;
use Amp\Http\Client\ParseException;
use Amp\Http\Status;
use Amp\Promise;

/** @internal */
final class SizeLimitingInputStream implements InputStream
{
    use ForbidSerialization;
    use ForbidCloning;

    /** @var InputStream|null */
    private $source;
    /** @var int */
    private $bytesRead = 0;
    /** @var int */
    private $sizeLimit;
    /** @var \Throwable|null */
    private $exception;

    public function __construct(
        InputStream $source,
        int $sizeLimit
    ) {
        $this->source = $source;
        $this->sizeLimit = $sizeLimit;
    }

    public function read(): Promise
    {
        if ($this->exception) {
            return new Failure($this->exception);
        }

        \assert($this->source !== null);

        $promise = $this->source->read();
        $promise->onResolve(function ($error, $value) {
            if ($value !== null) {
                $this->bytesRead += \strlen($value);
                if ($this->bytesRead > $this->sizeLimit) {
                    $this->exception = new ParseException(
                        "Configured body size exceeded: {$this->bytesRead} bytes received, while the configured limit is {$this->sizeLimit} bytes",
                        Status::PAYLOAD_TOO_LARGE
                    );

                    $this->source = null;
                }
            }
        });

        return $promise;
    }
}
