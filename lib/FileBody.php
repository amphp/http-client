<?php

namespace Amp\Artax;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use function Amp\File\open;
use function Amp\File\size;

final class FileBody implements RequestBody {
    /** @var string */
    private $path;

    /**
     * @param string $path The filesystem path for the file we wish to send
     */
    public function __construct(string $path) {
        $this->path = $path;
    }

    public function createBodyStream(): InputStream {
        $handlePromise = open($this->path, "r");

        return new class($handlePromise) implements InputStream {
            /** @var Promise */
            private $promise;

            /** @var InputStream */
            private $stream;

            public function __construct(Promise $promise) {
                $this->promise = $promise;
                $this->promise->onResolve(function ($error, $stream) {
                    if ($error) {
                        return;
                    }

                    $this->stream = $stream;
                });
            }

            public function read(): Promise {
                if (!$this->stream) {
                    return call(function () {
                        /** @var InputStream $stream */
                        $stream = yield $this->promise;
                        return $stream->read();
                    });
                }

                return $this->stream->read();
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
