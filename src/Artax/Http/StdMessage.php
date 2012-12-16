<?php

namespace Artax\Http;

abstract class StdMessage extends ValueMessage implements MutableMessage {
    
    /**
     * Assign an entity body to the HTTP message
     * 
     * @param mixed $stringOrResource A string or stream resource
     * @return void
     */
    public function setBody($stringOrResource) {
        $this->assignBody($stringOrResource);
    }

    /**
     * Assign the HTTP version adhered to by the message
     *
     * @param string $protocol
     * @return void
     */
    public function setProtocol($protocol) {
        if (!empty($protocol)) {
            $this->assignProtocol($protocol);
        }
    }

    /**
     * Clears previous assignments and assigns new value(s) for the specified header field
     * 
     * Specifying an array value is equivalent to setting multiple header fields. For example:
     * 
     * ```php
     * <?php
     * $msg->setHeader('Set-Cookie', array(
     *     'cookie1=123; path=/; expires=Thu, 18-Oct-2032 04:54:20 GMT; domain=example.com;',
     *     'cookie2=456; path=/; expires=Thu, 18-Oct-2032 04:54:20 GMT; domain=example.com;'
     * );
     * ```
     * 
     * The above assignment would result in a raw HTTP message string with two distinct
     * `Set-Cookie:` headers:
     * 
     * ```
     * Set-Cookie: cookie1=123; path=/; expires=Thu, 18-Oct-2032 04:54:20 GMT; domain=example.com;
     * Set-Cookie: cookie2=456; path=/; expires=Thu, 18-Oct-2032 04:54:20 GMT; domain=example.com;
     * ```
     * 
     * @param string $field The header field name
     * @param mixed $value The header value or an array of values
     * @throws \Ardent\TypeException On invalid field or value types
     * @throws \Ardent\DomainException On unacceptable field or header values (invalid characters)
     * @return void
     */
    public function setHeader($field, $value) {
        $this->clearHeader($field);
        $this->appendHeader($field, $value);
    }

    /**
     * Clears all previously assigned values and adds new headers from a key-value traversable or
     * a HeaderCollection instance
     *
     * @param mixed $arrayOrTraversable
     * @throws \Ardent\TypeException On invalid field or value types
     * @throws \Ardent\DomainException On unacceptable field or header values (invalid characters)
     * @return void
     */
    public function setAllHeaders($arrayOrTraversable) {
        $this->clearAllHeaders();
        $this->appendAllHeaders($arrayOrTraversable);
    }

    /**
     * Add a header value without clearing previously assigned values for the same field
     *
     * @param string $field The header field name
     * @param mixed $value The header value or an array of values
     * @throws \Ardent\TypeException On invalid field or value types
     * @throws \Ardent\DomainException On unacceptable field or header values (invalid characters)
     * @return void
     */
    public function addHeader($field, $value) {
        $this->appendHeader($field, $value);
    }

    /**
     * Assign or append headers from a traversable without clearing previously assigned values
     *
     * @param mixed $arrayOrTraversable
     * @throws \Ardent\TypeException On invalid field or value types
     * @throws \Ardent\DomainException On unacceptable field or header values (invalid characters)
     * @return void
     */
    public function addAllHeaders($arrayOrTraversable) {
        $this->appendAllHeaders($arrayOrTraversable);
    }
    
    /**
     * Remove the specified header from the message
     *
     * @param string $field
     * @return void
     */
    public function removeHeader($field) {
        $this->clearHeader($field);
    }

    /**
     * Clear all assigned headers from the message
     *
     * @return void
     */
    public function removeAllHeaders() {
        $this->clearAllHeaders();
    }
}