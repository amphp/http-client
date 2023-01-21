<?php declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\ParseException;
use Amp\Http\HttpStatus;

/**
 * @internal
 * @implements \IteratorAggregate<int, string>
 */
final class SizeLimitingReadableStream implements ReadableStream, \IteratorAggregate
{
    use ForbidSerialization;
    use ForbidCloning;
    use ReadableStreamIteratorAggregate;

    private int $bytesRead = 0;

    private ?\Throwable $exception = null;

    public function __construct(
        private readonly ReadableStream $source,
        private readonly int $sizeLimit
    ) {
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
                    HttpStatus::PAYLOAD_TOO_LARGE
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
