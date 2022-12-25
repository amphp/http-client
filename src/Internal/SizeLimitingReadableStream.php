<?php declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\ParseException;
use Amp\Http\Status;

/** @internal */
final class SizeLimitingReadableStream implements ReadableStream
{
    use ForbidSerialization;
    use ForbidCloning;

    private ReadableStream $source;

    private int $bytesRead = 0;

    private int $sizeLimit;

    private ?\Throwable $exception = null;

    public function __construct(
        ReadableStream $source,
        int $sizeLimit
    ) {
        $this->source = $source;
        $this->sizeLimit = $sizeLimit;
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if ($this->exception) {
            throw $this->exception;
        }

        $chunk = $this->source->read($cancellation);

        if ($chunk !== null) {
            $this->bytesRead += \strlen($chunk);
            if ($this->bytesRead > $this->sizeLimit) {
                $this->exception = new ParseException(
                    "Configured body size exceeded: {$this->bytesRead} bytes received, while the configured limit is {$this->sizeLimit} bytes",
                    Status::PAYLOAD_TOO_LARGE
                );

                $this->source->close();
            }
        }

        return $chunk;
    }

    public function isReadable(): bool
    {
        return $this->source->isReadable();
    }

    public function close(): void
    {
        $this->source->close();
    }

    public function isClosed(): bool
    {
        return $this->source->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->source->onClose($onClose);
    }
}
