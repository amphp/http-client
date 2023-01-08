<?php declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;

/**
 * @internal
 * @implements \IteratorAggregate<int, string>
 */
final class ResponseBodyStream implements ReadableStream, \IteratorAggregate
{
    use ForbidSerialization;
    use ForbidCloning;
    use ReadableStreamIteratorAggregate;

    private bool $successfulEnd = false;

    public function __construct(
        private readonly ReadableStream $body,
        private readonly DeferredCancellation $bodyCancellation
    ) {
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        $chunk = $this->body->read($cancellation);

        if ($chunk === null) {
            $this->successfulEnd = true;
        }

        return $chunk;
    }

    public function isReadable(): bool
    {
        return $this->body->isReadable();
    }

    public function __destruct()
    {
        if (!$this->successfulEnd) {
            $this->bodyCancellation->cancel();
        }
    }

    public function close(): void
    {
        $this->body->close();
    }

    public function isClosed(): bool
    {
        return $this->body->isClosed();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->body->onClose($onClose);
    }
}
