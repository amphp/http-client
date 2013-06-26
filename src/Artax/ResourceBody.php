<?php

namespace Artax;

class ResourceBody implements \Iterator, \Countable {
    
    private $resource;
    private $lengthCache;
    private $currentIterCache;
    private $streamGranularity = 32768;
    
    function __construct($resource) {
        if (is_resource($resource)) {
            $this->validateSeekability($resource);
            $this->resource = $resource;
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
    
    function count() {
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
        if ($position !== FALSE) {
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
            $isEof = TRUE;
        } elseif ($resourceReportsEof) {
            throw new \RuntimeException(
                'Resource went away unexpectedly'
            );
        } else {
            $isEof = FALSE;
        }
        
        return $isEof;
    }
    
    function current() {
        if (isset($this->currentIterCache)) {
            $current = $this->currentIterCache;
        } else {
            $current = $this->currentIterCache = $this->getResourceChunk($this->resource);
        }
        
        return $current;
    }
    
    private function getResourceChunk($resource) {
        $chunk = @fread($resource, $this->streamGranularity);
        
        if ($chunk === FALSE) {
            throw new \RuntimeException(
                'Failed reading from stream'
            );
        }
        
        return $chunk;
    }
    
    function key() {
        return $this->getResourcePosition($this->resource);
    }

    function next() {
        $this->currentIterCache = NULL;
    }

    function valid() {
        return !$this->isEof($this->resource);
    }

    function rewind() {
        $this->seekResource($this->resource, 0);
    }
    
    function setStreamGranularity($bytes) {
        $this->streamGranularity = filter_var($bytes, FILTER_VALIDATE_INT, ['options' => [
            'min_range' => 1,
            'default' => 32768
        ]]);
    }
}

