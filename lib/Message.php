<?php

namespace Amp\Artax;

abstract class Message {
    private $protocol = '1.1';
    private $headers = [];
    private $headerCaseMap = [];
    private $body;

    /**
     * Retrieve the message's HTTP protocol version
     *
     * @return string
     */
    public function getProtocol(): string {
        return $this->protocol;
    }

    /**
     * Assign the message's HTTP protocol version
     *
     * @param string $protocol
     * @return self
     */
    public function setProtocol(string $protocol): self {
        $this->protocol = $protocol;

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
    public function setBody($body): self {
        $this->body = $body;

        return $this;
    }

    /**
     * Does the message contain an entity body?
     *
     * @return bool
     */
    public function hasBody(): bool {
        return ($this->body != '');
    }

    /**
     * Does the message contain the specified header field (case-insensitive)?
     *
     * @param string $field
     * @return bool
     */
    public function hasHeader(string $field): bool {
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
    public function getHeader(string $field): array {
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
    public function getAllHeaders(): array {
        return $this->headers;
    }

    /**
     * Assign a value for the specified header field (replaces any existing values for that field)
     *
     * @param string $field
     * @param mixed $value
     * @throws \InvalidArgumentException on invalid header value
     * @return self
     */
    public function setHeader(string $field, $value): self {
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
    public function setAllHeaders(array $headers): self {
        foreach ($headers as $field => $value) {
            $this->setHeader($field, $value);
        }

        return $this;
    }

    /**
     * Appends a header for the specified field (instead of replacing via setHeader())
     *
     * @param string $field
     * @param mixed $value
     * @return self
     */
    public function appendHeader(string $field, $value): self {
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
    public function removeHeader(string $field): self {
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
    public function removeAllHeaders(): self {
        $this->headers = [];
        $this->headerCaseMap = [];

        return $this;
    }
}
