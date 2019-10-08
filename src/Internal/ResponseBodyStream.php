<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\InputStream;
use Amp\CancellationTokenSource;
use Amp\Promise;

/** @internal */
final class ResponseBodyStream implements InputStream
{
    private $body;
    private $bodyCancellation;
    private $successfulEnd = false;

    public function __construct(InputStream $body, CancellationTokenSource $bodyCancellation)
    {
        $this->body = $body;
        $this->bodyCancellation = $bodyCancellation;
    }

    public function read(): Promise
    {
        $promise = $this->body->read();
        $promise->onResolve(function ($error, $value) {
            if ($value === null && $error === null) {
                $this->successfulEnd = true;
            }
        });

        return $promise;
    }

    public function __destruct()
    {
        if (!$this->successfulEnd) {
            $this->bodyCancellation->cancel();
        }
    }

    public function __sleep(): array
    {
        throw new \Error('Serialization of class ' . __CLASS__ . ' is not allowed');
    }
}
