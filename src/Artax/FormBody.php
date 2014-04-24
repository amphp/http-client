<?php

namespace Artax;

class FormBody implements BodyAggregate {

    private $fields = [];
    private $boundary;
    private $isMultipart = FALSE;

    function __construct($boundary = NULL) {
        $this->boundary = $boundary ?: md5(uniqid());
    }

    function addField($name, $value, $contentType = 'text/plain') {
        $this->validateFieldName($name);
        $this->validateFieldValue($value);
        $this->fields[] = [$name, $value, $contentType, $fileName = NULL];

        return $this;
    }

    private function validateFieldName($name) {
        if (!(is_string($name) && strlen($name))) {
            throw new \InvalidArgumentException(
                'Invalid field name; string expected'
            );
        }
    }

    private function validateFieldValue($value) {
        if (!(is_scalar($value) || is_array($value))) {
            throw new \InvalidArgumentException(
                'Invalid field value; scalar or array expected'
            );
        }
    }
    
    private function validateFileFieldValue($value) {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                'Invalid field value; string expected'
            );
        }
    }

    function addFileField($name, $filePath, $contentType = 'application/octet-stream') {
        $this->validateFieldName($name);
        $this->validateFileFieldValue($filePath);

        $fileName = basename($filePath);
        $this->fields[] = [$name, new FileBody($filePath), $contentType, $fileName];
        $this->isMultipart = TRUE;

        return $this;
    }

    function getBody() {
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

            $fields[] = is_string($field)
                ? $this->generateMultipartFieldHeader($name, $contentType)
                : $this->generateMultipartFileHeader($name, $fileName, $contentType);

            $fields[] = $field;
            $fields[] = "\r\n";
        }

        $fields[] = "--{$this->boundary}--";

        foreach ($fields as $field) {
            $length += is_string($field) ? strlen($field) : $field->count();
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

    function getContentType() {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

}

