<?php

namespace Amp\Artax;

use Amp\Success;
use Amp\Deferred;

class FormBody implements AggregateBody {
    private $fields = [];
    private $boundary;
    private $isMultipart = false;
    private $cachedBody;
    private $cachedLength;

    /**
     * @param string $boundary An optional multipart boundary string
     */
    public function __construct($boundary = null) {
        $this->boundary = $boundary ?: md5(uniqid($prefix = '', $moreEntropy = true));
    }

    /**
     * Add a data field to the form entity body
     *
     * @param string $name
     * @param string $value
     * @param string $contentType
     * @return self
     */
    public function addField($name, $value, $contentType = 'text/plain') {
        $this->fields[] = [(string) $name, (string) $value, (string) $contentType, $fileName = null];
        $this->cachedBody = $this->cachedLength = $this->cachedFields = null;

        return $this;
    }

    /**
    * Add each element of a associative array as a data field to the form entity body
    *
    * @param array $data
    * @param string $contentType
    * @return self
    */
    public function addFields(array $data, $contentType = 'text/plain') {
        foreach ($data as $key => $value) {
            $this->addField($key, $value, $contentType);
        }

        return $this;
    }

    /**
     * Add a file field to the form entity body
     *
     * @param string $name
     * @param string $filePath
     * @param string $contentType
     * @return self
     */
    public function addFile($name, $filePath, $contentType = 'application/octet-stream') {
        $filePath = (string) $filePath;
        $fileName = basename($filePath);
        $this->fields[] = [(string) $name, new FileBody($filePath), $contentType, $fileName];
        $this->isMultipart = true;
        $this->cachedBody = $this->cachedLength = $this->cachedFields = null;

        return $this;
    }

    /**
    * Add each element of a associative array as a file field to the form entity body
    *
    * @param array $data
    * @param string $contentType
    * @return self
    */
    public function addFiles(array $data, $contentType = 'application/octet-stream') {
        foreach ($data as $key => $value) {
            $this->addFile($key, $value, $contentType);
        }

        return $this;
    }

    /**
     * Retrieve the sendable Amp\Artax entity body representation
     *
     * AggregateBody::getBody() implementations always return a Promise instance to allow
     * for future resolution of non-blocking operations (e.g. when the entity body comprises
     * filesystem resources).
     *
     * @return \Amp\Promise
     */
    public function getBody() {
        if ($this->isMultipart) {
            $fields = $this->getMultipartFieldArray();
            return $this->generateMultipartIteratorFromFields($fields);
        } else {
            return new Success($this->getFormEncodedBodyString());
        }
    }

    private function getMultipartFieldArray() {
        if (isset($this->cachedFields)) {
            return $this->cachedFields;
        }

        $fields = [];

        foreach ($this->fields as $fieldArr) {
            list($name, $field, $contentType, $fileName) = $fieldArr;

            $fields[] = "--{$this->boundary}\r\n";
            $fields[] = $field instanceof FileBody
                ? $this->generateMultipartFileHeader($name, $fileName, $contentType)
                : $this->generateMultipartFieldHeader($name, $contentType);

            $fields[] = $field;
            $fields[] = "\r\n";
        }

        $fields[] = "--{$this->boundary}--";

        return $this->cachedFields = $fields;
    }

    private function generateMultipartFileHeader($name, $fileName, $contentType) {
        $header = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\n";
        $header.= "Content-Type: {$contentType}\r\n";
        $header.= "Content-Transfer-Encoding: binary\r\n\r\n";

        return $header;
    }

    private function generateMultipartFieldHeader($name, $contentType) {
        $header = "Content-Disposition: form-data; name=\"{$name}\"\r\n";
        $header.= "Content-Type: {$contentType}\r\n\r\n";

        return $header;
    }

    private function generateMultipartIteratorFromFields(array $fields) {
        foreach ($fields as $key => $field) {
            $fields[$key] = $field instanceof FileBody ? $field->getBody() : $field;
        }

        $promisor = new Deferred;
        \Amp\all($fields)->when(function($error, $result) use ($promisor) {
            if ($error) {
                $promisor->fail($error);
            } else {
                $this->cachedBody = $result;
                $promisor->succeed(new MultipartIterator($result));
            }
        });

        return $promisor->promise();
    }

    private function getFormEncodedBodyString() {
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

    /**
     * Retrieve a key-value array of headers to add to the outbound request
     *
     * AggregateBody::getHeaders() implementations always return a Promise instance to allow
     * for future resolution of non-blocking operations (e.g. when using filesystem stats to
     * generate content-length headers).
     *
     * @return \Amp\Promise
     */
    public function getHeaders() {
        $promisor = new Deferred;
        $length = $this->getLength();
        $length->when(function($error, $result) use ($promisor) {
            if ($error) {
                $promisor->fail($error);
            } else {
                $type = $this->determineContentType();
                $promisor->succeed([
                    'Content-Type' => $type,
                    'Content-Length' => $result
                ]);
            }
        });

        return $promisor->promise();
    }

    private function determineContentType() {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

    /**
     * Retrieve the content length of the form entity body
     *
     * AggregateBody::getLength() implementations always return a Promise instance to allow
     * for future resolution of non-blocking operations (e.g. when using filesystem stats to
     * determine entity body length).
     *
     * @return \Amp\Promise
     */
    public function getLength() {
        if (isset($this->cachedLength)) {
            return new Success($this->cachedLength);
        } elseif ($this->isMultipart) {
            $fields = $this->getMultipartFieldArray();
            $length = $this->sumMultipartFieldLengths($fields);
            $length->when(function($error, $result) {
                if (empty($error)) {
                    $this->cachedLength = $result;
                }
            });
            return $length;
        } else {
            $length = strlen($this->getFormEncodedBodyString());
            return new Success($length);
        }
    }

    private function sumMultipartFieldLengths(array $fields) {
        $lengths = [];
        foreach ($fields as $field) {
            if (is_string($field)) {
                $lengths[] = strlen($field);
            } else {
                $lengths[] = $field->getLength();
            }
        }

        $promisor = new Deferred;
        \Amp\all($lengths)->when(function($error, $result) use ($promisor) {
            if ($error) {
                $promisor->fail($error);
            } else {
                $promisor->succeed(array_sum($result));
            }
        });

        return $promisor->promise();
    }
}
