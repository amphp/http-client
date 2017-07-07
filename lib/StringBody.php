<?php

namespace Amp\Artax;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Success;

final class StringBody implements RequestBody {
    private $body;

    public function __construct(string $body) {
        $this->body = $body;
    }

    public function createBodyStream(): InputStream {
        return new InMemoryStream($this->body);
    }

    public function getHeaders(): Promise {
        return new Success([]);
    }

    public function getBodyLength(): Promise {
        return new Success(\strlen($this->body));
    }
}
