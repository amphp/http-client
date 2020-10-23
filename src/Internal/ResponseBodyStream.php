<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Amp\CancellationTokenSource;

/** @internal */
final class ResponseBodyStream implements InputStream
{
    use ForbidSerialization;
    use ForbidCloning;

    private InputStream $body;

    private CancellationTokenSource $bodyCancellation;

    private bool $successfulEnd = false;

    public function __construct(InputStream $body, CancellationTokenSource $bodyCancellation)
    {
        $this->body = $body;
        $this->bodyCancellation = $bodyCancellation;
    }

    public function read(): ?string
    {
        $chunk = $this->body->read();

        if ($chunk === null) {
            $this->successfulEnd = true;
        }

        return $chunk;
    }

    public function __destruct()
    {
        if (!$this->successfulEnd) {
            $this->bodyCancellation->cancel();
        }
    }
}
