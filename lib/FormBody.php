<?php

namespace Artax;

class FormBody implements AggregateBody {
    private $fields = [];
    private $boundary;
    private $isMultipart = false;

    public function __construct($boundary = null) {
        $this->boundary = $boundary ?: md5(uniqid());
    }

    public function addField($name, $value, $contentType = 'text/plain') {
        $name = $this->normalizeFieldName($name);

        $this->fields[] = [$name, (string) $value, $contentType, $fileName = null];

        return $this;
    }

    private function normalizeFieldName($name) {
        $name = (string) $name;
        if (!isset($name[0])) {
            throw new \InvalidArgumentException(
                'Invalid field name; non-empty string required'
            );
        }

        return $name;
    }

    public function addFileField($name, $filePath, $contentType = 'application/octet-stream') {
        $name = $this->normalizeFieldName($name);
        $filePath = (string) $filePath;
        $fileName = basename($filePath);
        $this->fields[] = [$name, new FileBody($filePath), $contentType, $fileName];
        $this->isMultipart = true;

        return $this;
    }

    public function getBody() {
        return $this->isMultipart
            ? $this->getIterableMultipartBody()
            : $this->getFormEncodedBody();
    }

    private function getFormEncodedBody() {
        $fields = [];

        foreach ($this->fields as $fieldArr) {
            list($name, $value) = $fieldArr;
            $fields[$name][] = $value;
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = isset($value[1]) ? $value : $value[0];
        }

        return http_build_query($fields);
    }

    private function getIterableMultipartBody() {
        $fields = [];
        $length = 0;

        foreach ($this->fields as $fieldArr) {
            list($name, $field, $contentType, $fileName) = $fieldArr;

            $fields[] = "--{$this->boundary}\r\n";

            $fields[] = is_scalar($field)
                ? $this->generateMultipartFieldHeader($name, $contentType)
                : $this->generateMultipartFileHeader($name, $fileName, $contentType);

            $fields[] = $field;
            $fields[] = "\r\n";
        }

        $fields[] = "--{$this->boundary}--";

        foreach ($fields as $field) {
            $length += is_scalar($field) ? strlen($field) : $field->count();
        }
        reset($fields);

        return new MultipartFormBodyIterator($fields, $length);
    }

    private function generateMultipartFieldHeader($name, $contentType) {
        $header = "Content-Disposition: form-data; name=\"{$name}\"\r\n";
        $header.= "Content-Type: {$contentType}\r\n\r\n";

        return $header;
    }

    private function generateMultipartFileHeader($name, $fileName, $contentType) {
        $header = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\n";
        $header.= "Content-Type: {$contentType}\r\n";
        $header.= "Content-Transfer-Encoding: binary\r\n\r\n";

        return $header;
    }

    public function getContentType() {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

    public function getHeaders() {
        return ['Content-Type' => $this->getContentType()];
    }
}
