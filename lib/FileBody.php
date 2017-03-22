<?php

namespace Amp\Artax;

use Amp\{ Deferred, Failure, Promise, Success };

class FileBody implements AggregateBody {
    /** @var string */
    private $path;

    /**
     * @param string $path The filesystem path for the file we wish to send
     */
    public function __construct(string $path) {
        $this->path = $path;
    }

    /**
     * Retrieve the sendable Amp\Artax entity body representation
     *
     * @return \Amp\Promise
     */
    public function getBody(): Promise {
        // @TODO Implement non-blocking php-uv iterator.
        // For now we'll just use the dumb blocking version.
        // v1.0.0 cannot be a thing until this is implemented.
        if ($resource = @fopen($this->path, 'r')) {
            return new Success(new ResourceIterator($resource));
        } else {
            return new Failure(new \RuntimeException(
                sprintf('Failed opening file resource: %s', $this->path)
            ));
        }
    }

    /**
     * Return a key-value array of headers to add to the outbound request
     *
     * @return \Amp\Promise
     * @TODO
     */
    public function getHeaders(): Promise {
        // @TODO Implement non-blocking php-uv header retrieval.
        // For now we'll just use the dumb blocking version.
        // v1.0.0 cannot be a thing until this is implemented.
        $deferred = new Deferred;
        $this->getLength()->onResolve(function($error, $result) use ($deferred) {
            if ($error) {
                $deferred->fail($error);
            } else {
                $deferred->resolve(['Content-Length' => $result]);
            }
        });

        return $deferred->promise();
    }

    /**
     * Retrieve the entity body's content length
     *
     * @return \Amp\Promise
     */
    public function getLength(): Promise {
        // @TODO Implement non-blocking php-uv file size retrieval.
        // For now we'll just use the dumb blocking version.
        // v1.0.0 cannot be a thing until this is implemented.
        $size = @filesize($this->path);
        if ($size === false) {
            return new Failure(new \RuntimeException(
                sprintf('Could not determine file size for FileBody: %s', $this->path)
            ));
        }

        return new Success($size);
    }
}
