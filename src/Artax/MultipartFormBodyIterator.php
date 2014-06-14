<?php

namespace Artax;

class MultipartFormBodyIterator implements \Iterator, \Countable {
    private $fields;
    private $length;
    private $currentCache;

    public function __construct(array $fields, $length) {
        $this->fields = $fields;
        $this->length = $length;
    }

    public function current() {
        if (isset($this->currentCache)) {
            return $this->currentCache;
        }

        $current = current($this->fields);

        return ($current instanceof FileBody)
            ? ($this->currentCache = $current->current())
            : $current;
    }

    public function key() {
        return key($this->fields);
    }

    public function next() {
        $this->currentCache = NULL;
        $current = current($this->fields);
        if ($current instanceof FileBody) {
            $this->advanceFileBodyIterator($current);
        } else {
            next($this->fields);
        }
    }

    private function advanceFileBodyIterator(FileBody $fileBody) {
        $fileBody->next();
        if (!$fileBody->valid()) {
            next($this->fields);
        }
    }

    public function valid() {
        return isset($this->fields[key($this->fields)]);
    }

    public function rewind() {
        foreach ($this->fields as $field) {
            if ($field instanceof FileBody) {
                $field->rewind();
            }
        }

        reset($this->fields);

        $this->currentCache = NULL;
    }

    public function count() {
        return $this->length;
    }
}
