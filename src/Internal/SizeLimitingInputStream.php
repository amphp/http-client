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
    /** @var InputStream */
    private $source;
    private $bytesRead = 0;
    private $sizeLimit;
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

        $promise = $this->source->read();
        $promise->onResolve(function ($error, $value) {
            if ($error === null) {
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
            }
        });

        return $promise;
    }
}
