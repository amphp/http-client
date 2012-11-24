<?php

namespace Artax\Http;

use Spl\TypeException,
    Spl\DomainException;

/**
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
 */
class Header {
    
    /**
     * @var string
     */
    private $field;
    
    /**
     * @var string
     */
    private $normalizedValue;
    
    /**
     * @var string
     */
    private $rawValue;

    /**
     * @param string $field
     * @param string $value
     * @throws \Spl\TypeException On non-string parameters
     * @throws \Spl\DomainException On the presence of invalid header characters
     */
    public function __construct($field, $value) {
        if (!is_scalar($field) || is_resource($field)) {
            throw new TypeException(
                get_class($this) . '::__construct expects a scalar at Argument 1'
            );
        } elseif (!is_scalar($value) || is_resource($value)) {
            throw new TypeException(
                get_class($this) . '::__construct expects a scalar at Argument 2'
            );
        }
        $this->setField($field);
        $this->setValue($value);
    }
    
    private function setField($field) {
        $field = rtrim($field, ':');
        $pattern = ",^([^\x{00}-\x{20}\(\)<>@\,;:\"/\[\]\?={}\\\\]+)$,";
        
        if (preg_match($pattern, $field, $match)) {
            $this->field = $match[1];
        } else {
            throw new DomainException(
                'Invalid header field'
            );
        }
    }
    
    private function setValue($value) {
        $normalizedValue = preg_replace(",\r?\n[\t\x20]+,", "\x20", $value);
        $pattern = ",^[\x{20}\x{09}]*([^\x{00}-\x{08}\x{0a}-\x{1f}]+)?[\x{20}\x{09}]*$,";
        
        if (preg_match($pattern, $normalizedValue, $match)) {
            $this->normalizedValue = isset($match[1]) ? $match[1] : '';
            $this->rawValue = $value;
        } else {
            throw new DomainException(
                'Invalid header value'
            );
        }
    }
    
    /**
     * Returns the header in string form
     * 
     * @return string
     */
    public function __toString() {
        return $this->field . ': ' . $this->rawValue;
    }
    
    /**
     * Retrieve the header field
     * 
     * @return string
     */
    public function getField() {
        return $this->field;
    }
    
    /**
     * Retrieve the normalized header value
     * 
     * @return string
     */
    public function getValue() {
        return $this->normalizedValue;
    }
    
    /**
     * Retrieve the original, un-normalized header value with any LWS intact
     * 
     * @return string
     */
    public function getRawValue() {
        return $this->rawValue;
    }
}
