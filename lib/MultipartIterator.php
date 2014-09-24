<?php

namespace Amp\Artax;

class MultipartIterator implements \Iterator {
    private $fields;
    private $currentCache;

    public function __construct(array $fields) {
        $this->fields = $fields;
    }

    public function getFields() {
        return $this->fields;
    }

    public function current() {
        if (isset($this->currentCache)) {
            return $this->currentCache;
        }

        $current = current($this->fields);

        return ($current instanceof \Iterator)
            ? ($this->currentCache = $current->current())
            : $current;
    }

    public function key() {
        return key($this->fields);
    }

    public function next() {
        $this->currentCache = null;
        $current = current($this->fields);
        if ($current instanceof \Iterator) {
            $this->advanceElementIterator($current);
        } else {
            next($this->fields);
        }
    }

    private function advanceElementIterator(\Iterator $element) {
        $element->next();
        if (!$element->valid()) {
            next($this->fields);
        }
    }

    public function valid() {
        return isset($this->fields[key($this->fields)]);
    }

    public function rewind() {
        foreach ($this->fields as $field) {
            if ($field instanceof \Iterator) {
                $field->rewind();
            }
        }

        reset($this->fields);

        $this->currentCache = null;
    }
}
