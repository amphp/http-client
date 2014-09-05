<?php

namespace Artax;

use Alert\Reactor;
use Alert\UvReactor;
use After\Future;
use After\Failure;
use After\Success;

class FileBody implements AggregateBody {
    private $path;

    /**
     * @param string $path The filesystem path for the file we wish to send
     */
    public function __construct($path) {
        $this->path = (string) $path;
    }

    /**
     * Retrieve the sendable Artax entity body representation
     *
     * @param \Alert\Reactor $reactor
     * @return \After\Promise
     */
    public function getBody(Reactor $reactor) {
        return ($reactor instanceof UvReactor)
            ? $this->generateUvBody($reactor)
            : $this->generateNaiveBody();
    }

    private function generateUvBody($reactor) {
        // @TODO Implement non-blocking php-uv iterator.
        // For now we'll just use the dumb blocking version.
        // v1.0.0 cannot be a thing until this is implemented.
        return $this->generateNaiveBody();
    }

    private function generateNaiveBody() {
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
     * @param Reactor $reactor
     * @return \After\Promise
     * @TODO
     */
    public function getHeaders(Reactor $reactor) {
        return ($reactor instanceof UvReactor)
            ? $this->generateUvHeaders($reactor)
            : $this->generateNaiveHeaders();
    }

    private function generateUvHeaders($reactor) {
        // @TODO Implement non-blocking php-uv header retrieval.
        // For now we'll just use the dumb blocking version.
        // v1.0.0 cannot be a thing until this is implemented.
        return $this->generateNaiveBody();
    }

    private function generateNaiveHeaders() {
        $future = new Future;
        $this->generateNaiveLength()->when(function($error, $result) use ($future) {
            if ($error) {
                $future->fail($error);
            } else {
                $future->succeed(['Content-Length' => $result]);
            }
        });

        return $future->promise();
    }

    /**
     * Retrieve the entity body's content length
     *
     * @param \Alert\Reactor $reactor
     * @return \After\Promise
     */
    public function getLength(Reactor $reactor) {
        return ($reactor instanceof UvReactor)
            ? $this->generateUvLength($reactor)
            : $this->generateNaiveLength();
    }

    private function generateUvLength($reactor) {
        // @TODO Implement non-blocking php-uv file size retrieval.
        // For now we'll just use the dumb blocking version.
        // v1.0.0 cannot be a thing until this is implemented.
        return $this->generateNaiveLength();
    }

    private function generateNaiveLength() {
        $size = @filesize($this->path);
        if ($size === false) {
            return new Failure(new \RuntimeException(
                sprintf('Could not determine file size for FileBody: %s', $this->path)
            ));
        }

        return new Success($size);
    }
}
