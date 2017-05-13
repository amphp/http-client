<?php

namespace Amp\Artax;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\File\open;
use function Amp\File\size;

class FileBody implements AggregateBody {
    /** @var string */
    private $path;

    /**
     * @param string $path The filesystem path for the file we wish to send
     */
    public function __construct(string $path) {
        $this->path = $path;
    }

    public function getBody(): InputStream {
        $handlePromise = open($this->path, "r");

        // TODO: Move to amphp/byte-stream with more efficient implementation
        return new class($handlePromise) implements InputStream {
            private $promise;

            public function __construct(Promise $promise) {
                $this->promise = $promise;
            }

            public function read(): Promise {
                return call(function () {
                    /** @var InputStream $stream */
                    $stream = yield $this->promise;
                    return $stream->read();
                });
            }
        };
    }

    public function getHeaders(): Promise {
        return new Success([]);
    }

    public function getBodyLength(): Promise {
        return size($this->path);
    }
}
