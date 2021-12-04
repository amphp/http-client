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

    public function read(?Cancellation $token = null): ?string
    {
        $chunk = $this->body->read($token);

        if ($chunk === null) {
            $this->successfulEnd = true;
        }

        return $chunk;
    }

    public function isReadable(): bool {
        return $this->body->isReadable();
    }

    public function __destruct()
    {
        if (!$this->successfulEnd) {
            $this->bodyCancellation->cancel();
        }
    }
}
