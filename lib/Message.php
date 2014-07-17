<?php

namespace Artax;

abstract class Message {
    private $protocol;
    private $headers = [];
    private $headerCaseMap = [];
    private $body;

    public function getProtocol() {
        return $this->protocol;
    }

    public function setProtocol($protocol) {
        $this->protocol = $protocol;

        return $this;
    }

    public function getBody() {
        return $this->body;
    }

    public function setBody($body) {
        $this->body = $body;

        return $this;
    }

    public function hasBody() {
        return ($this->body || $this->body === '0');
    }

    public function hasHeader($field) {
        $fieldUpper = strtoupper($field);

        return isset($this->headerCaseMap[$fieldUpper]);
    }

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

    public function getAllHeaders() {
        return $this->headers;
    }

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

    public function setAllHeaders(array $headers) {
        foreach ($headers as $field => $value) {
            $this->setHeader($field, $value);
        }

        return $this;
    }

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

    public function removeAllHeaders() {
        $this->headers = [];
        $this->headerCaseMap = [];

        return $this;
    }
}
