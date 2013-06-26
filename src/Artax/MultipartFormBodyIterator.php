<?php

namespace Artax;

class MultipartFormBodyIterator implements \Iterator, \Countable {
    
    private $fields;
    private $length;
    private $currentCache;
    private $position = 0;
    
    function __construct(array $fields, $length) {
        $this->fields = $fields;
        $this->length = $length;
    }
    
    function current() {
        if (isset($this->currentCache)) {
            $current = $this->currentCache;
        } elseif (current($this->fields) instanceof FileBody) {
            $current = $this->currentCache = current($this->fields)->current();
        } else {
            $current = $this->currentCache = current($this->fields);
        }
        
        return $current;
    }
    
    function key() {
        return key($this->fields);
    }

    function next() {
        $this->currentCache = NULL;
        if (current($this->fields) instanceof FormBody) {
            current($this->fields)->next();
        } else {
            next($this->fields);
        }
    }

    function valid() {
        return isset($this->fields[key($this->fields)]);
    }

    function rewind() {
        foreach ($this->fields as $field) {
            if ($field instanceof MultipartFormFile) {
                $field->rewind();
            }
        }
        
        reset($this->fields);
        
        $this->currentCache = NULL;
    }
    
    function count() {
        return $this->length;
    }
}

