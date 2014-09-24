<?php

namespace Amp\Artax;

class ResourceIterator extends StreamIterator {
    private $resource;
    private $currentCache;

    /**
     * @param $resource Stream resource
     */
    public function __construct($resource) {
        if (is_resource($resource)) {
            $this->resource = $resource;
        } else {
            throw new \DomainException(
                sprintf('ResourceIterator requires a resource (duh); %s provided', gettype($resource))
            );
        }
    }

    /**
     * @return string
     * @throws \RuntimeException If file cannot be read
     */
    public function current() {
        if (isset($this->currentCache)) {
            $current = $this->currentCache;
        } else {
            $current = $this->currentCache = $this->getResourceChunk();
        }

        return $current;
    }

    private function getResourceChunk() {
        $chunk = @fread($this->resource, $this->readSize);

        if ($chunk === false) {
            throw new \RuntimeException(
                sprintf('Failed reading from stream: %s', $this->path)
            );
        }

        return $chunk;
    }

    public function key() {
        return 0;
    }

    public function next() {
        return $this->currentCache = null;
    }

    public function valid() {
        return $this->resource && !@feof($this->resource);
    }

    public function rewind() {
        if ($this->resource && is_resource($this->resource)) {
            rewind($this->resource);
        }
    }
}
