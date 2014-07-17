<?php

namespace Artax;

class ResourceBody implements \Iterator, \Countable {
    private $resource;
    private $lengthCache;
    private $currentCache;
    private $currentIterCache;
    private $streamGranularity = 32768;

    public function __construct($resource) {
        if (is_resource($resource)) {
            $this->validateSeekability($resource);
            $this->resource = $resource;
            $this->currentCache = 0;
        } else {
            throw new \InvalidArgumentException(
                'Invalid stream resource'
            );
        }
    }

    private function validateSeekability($resource) {
        if (!stream_get_meta_data($resource)['seekable']) {
            throw new \InvalidArgumentException(
                'Invalid stream resource: must be seekable'
            );
        }
    }

    public function count() {
        if (isset($this->lengthCache)) {
            $length = $this->lengthCache;
        } else {
            $currentPosition = $this->getResourcePosition($this->resource);
            $length = $this->lengthCache = $this->getResourceLength($this->resource);
            $this->seekResource($this->resource, $currentPosition);
        }

        return $length;
    }

    private function getResourceLength($resource) {
        if (fseek($resource, 0, SEEK_END)) {
            throw new \RuntimeException(
                'Failed seeking on stream'
            );
        }

        $length = $this->getResourcePosition($resource);

        $this->seekResource($resource, 0);

        return $length;
    }

    private function getResourcePosition($resource) {
        $position = @ftell($resource);
        if ($position !== false) {
            return $position;
        } else {
            throw new \RuntimeException(
                'Failed to determine stream position'
            );
        }
    }

    private function seekResource($resource, $pos, $whence = SEEK_SET) {
        if (@fseek($resource, $pos, $whence)) {
            throw new \RuntimeException(
                'Failed seeking on stream'
            );
        }
    }

    private function isEof($resource) {
        $resourceReportsEof = @feof($resource);

        if ($resourceReportsEof && is_resource($resource)) {
            $isEof = true;
        } elseif ($resourceReportsEof) {
            throw new \RuntimeException(
                'Resource went away unexpectedly'
            );
        } else {
            $isEof = false;
        }

        return $isEof;
    }

    public function current() {
        if (isset($this->currentIterCache)) {
            $current = $this->currentIterCache;
        } else {
            $current = $this->currentIterCache = $this->getResourceChunk($this->resource);
        }

        return $current;
    }

    private function getResourceChunk($resource) {
        $remaining = $this->lengthCache - $this->currentCache;
        $chunk = @fread($resource, min($remaining, $this->streamGranularity));

        if ($chunk === false) {
            throw new \RuntimeException(
                'Failed reading from stream'
            );
        }

        $this->currentCache += strlen($chunk);

        return $chunk;
    }

    public function key() {
        return $this->getResourcePosition($this->resource);
    }

    public function next() {
        $this->currentIterCache = null;
        return $this->currentCache < $this->count();
    }

    public function valid() {
        return !$this->isEof($this->resource);
    }

    public function rewind() {
        $this->seekResource($this->resource, 0);
    }

    public function setStreamGranularity($bytes) {
        $this->streamGranularity = filter_var($bytes, FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 1,
            'default' => 32768
        ]]);
    }
}
