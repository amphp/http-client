<?php

namespace Artax\Http;

use Iterator,
    Countable,
    Spl\TypeException,
    Spl\ValueException;

class Header implements Iterator, Countable {
    
    /**
     * @var string
     */
    private $name;
    
    /**
     * @var array
     */
    private $value;
    
    /**
     * @param string $name
     * @param mixed $value A scalar value or one-dimensional array of scalars
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function __construct($name, $value) {
        if (!is_string($name)) {
            $type = is_object($name) ? get_class($name) : gettype($name);
            throw new TypeException(
                get_class($this) . '::__construct expects a string value at Argument 1: ' .
                "$type provided"
            );
        }
        $this->name = $name;
        $this->setValue($value);
    }
    
    /**
     * Returns the header as it should appear in a raw HTTP message (including trailing CRLF)
     * 
     * @return string
     */
    public function __toString() {
        $str = '';
        foreach ($this->value as $value) {
            $str .= "{$this->name}: $value\r\n";
        }
        return $str;
    }
    
    /**
     * Assign a value to the header -- replaces previous value(s)
     * 
     * @param mixed $value A scalar value or one-dimensional array of scalars
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function setValue($value) {
        if ($this->validateValue($value)) {
            $this->value = is_array($value) ? array_values($value) : array($value);
        } elseif (is_array($value)) {
            throw new ValueException(
                get_class($this) . '::setValue expects a scalar value or a one-dimensional ' .
                'array of scalars at Argument 1: invalid array provided'
            );
        } else {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new TypeException(
                get_class($this) . '::setValue expects a scalar value or a one-dimensional ' .
                "array of scalars at Argument 1: $type provided"
            );
        }
    }
    
    /**
     * @param mixed $value
     * @return bool
     */
    private function validateValue($value) {
        if (is_scalar($value)) {
            return true;
        } elseif (!is_array($value)) {
            return false;
        }
        
        return $value === array_filter($value, 'is_string');
    }
    
    /**
     * Append a new value to the already existing header
     * 
     * @param mixed $value A scalar value or one-dimensional array of scalars
     * @return void
     * @throws Spl\TypeException
     * @throws Spl\ValueException
     */
    public function appendValue($value) {
        if ($this->validateValue($value)) {
            if (is_array($value)) {
                $this->value = array_merge($this->value, array_values($value));
            } else {
                $this->value[] = $value;
            }
        } elseif (is_array($value)) {
            throw new ValueException(
                get_class($this) . '::appendValue expects a scalar value or a one-dimensional ' .
                'array of scalars at Argument 1: invalid array provided'
            );
        } else {
            $type = is_object($value) ? get_class($value) : gettype($value);
            throw new TypeException(
                get_class($this) . '::appendValue expects a scalar value or a one-dimensional ' .
                "array of scalars at Argument 1: $type provided"
            );
        }
    }
    
    /**
     * Retrieve the header field name
     * 
     * @return string
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * Retrieve the header value or a comma-separated concatenation of values if multiples exist
     * 
     * @return string
     */
    public function getValue() {
        return count($this->value) > 1 ? implode(',', $this->value) : $this->value[0];
    }
    
    /**
     * Retrieve the array of header values
     * 
     * @return array
     */
    public function getValueArray() {
        return $this->value;
    }
    
    /**
     * Output the header
     * 
     * @return void
     */
    public function send() {
        foreach ($this->value as $value) {
            header("{$this->name}: $value");
        }
    }
    
    /**
     * @return int
     */
    public function count() {
        return count($this->value);
    }
    
    public function rewind() {
        return reset($this->value);
    }
    
    public function current() {
        return current($this->value);
    }
    
    public function key() {
        return key($this->value);
    }
    
    public function next() {
        return next($this->value);
    }
    
    public function valid() {
        return key($this->value) !== null;
    }
}
