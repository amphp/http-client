<?php

namespace Amp\Artax;

abstract class Message {
    private $protocol;
    private $headers = [];
    private $headerCaseMap = [];
    private $body;

    /**
     * Retrieve the message's HTTP protocol version
     *
     * @return string
     */
    public function getProtocol() {
        return $this->protocol;
    }

    /**
     * Assign the message's HTTP protocol version
     *
     * @param string $protocol
     * @return self
     */
    public function setProtocol($protocol) {
        $this->protocol = (string) $protocol;

        return $this;
    }

    /**
     * Retrieve the message entity body
     *
     * @return mixed
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * Assign the message entity body
     *
     * @param mixed $body
     * @return self
     */
    public function setBody($body) {
        $this->body = $body;

        return $this;
    }

    /**
     * Does the message contain an entity body?
     *
     * @return bool
     */
    public function hasBody() {
        return ($this->body != '');
    }

    /**
     * Does the message contain the specified header field (case-insensitive)?
     *
     * @param string $field
     * @return bool
     */
    public function hasHeader($field) {
        $fieldUpper = strtoupper($field);

        return isset($this->headerCaseMap[$fieldUpper]);
    }

    /**
     * Retrieve an array of values for the specified header field
     *
     * @param string $field
     * @throws \DomainException on unknown header field
     * @return array
     */
    public function getHeader($field) {
        $fieldUpper = strtoupper($field);

        if (isset($this->headerCaseMap[$fieldUpper])) {
            $field = $this->headerCaseMap[$fieldUpper];
            return $this->headers[$field];
        } else {
            throw new \DomainException(
                'Specified header field does not exist: ' . $field
            );
        }
    }

    /**
     * Retrieve an associative array of headers matching field names to an array of field values
     *
     * @return array
     */
    public function getAllHeaders() {
        return $this->headers;
    }

    /**
     * Assign a value for the specified header field (replaces any existing values for that field)
     *
     * @param string $field
     * @param string $value
     * @throws \InvalidArgumentException on invalid header value
     * @return self
     */
    public function setHeader($field, $value) {
        if (is_scalar($value)) {
            $value = array($value);
        } elseif (!(is_array($value) && $this->validateHeader($value))) {
            throw new \InvalidArgumentException(
                'Invalid header; scalar or one-dimensional array of scalars required'
            );
        }

        $fieldUpper = strtoupper($field);

        $this->headers[$field] = $value;
        $this->headerCaseMap[$fieldUpper] = $field;

        return $this;
    }

    private function validateHeader(array $headerValues) {
        foreach ($headerValues as $value) {
            if (!is_scalar($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign multiple header field values at once
     *
     * Example:
     *
     *     $msg->setAllHeaders([
     *         'X-My-Header' => '42',
     *         'Cookie' => [1, 2, 3, 4, 5]
     *     ]);
     *
     * @param array $headers
     * @return self
     */
    public function setAllHeaders(array $headers) {
        foreach ($headers as $field => $value) {
            $this->setHeader($field, $value);
        }

        return $this;
    }

    /**
     * Appends a header for the specified field (instead of replacing via setHeader())
     *
     * @param string $field
     * @param string $value
     * @return self
     */
    public function appendHeader($field, $value) {
        if ($this->hasHeader($field)) {
            $existingHeaders = $this->getHeader($field);
            $value = is_scalar($value) ? [$value] : $value;
            $newHeaders = array_merge($existingHeaders, $value);
            $result = $this->setHeader($field, $newHeaders);
        } else {
            $result = $this->setHeader($field, $value);
        }

        return $result;
    }

    /**
     * Remove the specified header field from the message
     *
     * @param string $field
     * @return self
     */
    public function removeHeader($field) {
        $fieldUpper = strtoupper($field);

        if (isset($this->headerCaseMap[$fieldUpper])) {
            $field = $this->headerCaseMap[$fieldUpper];
            unset(
                $this->headerCaseMap[$fieldUpper],
                $this->headers[$field]
            );
        }

        return $this;
    }

    /**
     * Remove all header fields from the message
     *
     * @return self
     */
    public function removeAllHeaders() {
        $this->headers = [];
        $this->headerCaseMap = [];

        return $this;
    }
}
