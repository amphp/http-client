<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\DeferredCancellation;

/** @internal */
final class ResponseBodyStream implements ReadableStream
{
    use ForbidSerialization;
    use ForbidCloning;

    private ReadableStream $body;

    private DeferredCancellation $bodyCancellation;

    private bool $successfulEnd = false;

    public function __construct(ReadableStream $body, DeferredCancellation $bodyCancellation)
    {
        $this->body = $body;
        $this->bodyCancellation = $bodyCancellation;
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
