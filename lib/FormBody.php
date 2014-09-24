<?php

namespace Amp\Artax;

use Amp\Reactor;
use Amp\Success;
use Amp\Future;
use Amp\Combinator;

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
     * Retrieve the sendable Amp\Artax entity body representation
     *
     * AggregateBody::getBody() implementations always return a Promise instance to allow
     * for future resolution of non-blocking operations (e.g. when the entity body comprises
     * filesystem resources).
     *
     * @param \Amp\Reactor $reactor
     * @return \Amp\Promise
     */
    public function getBody(Reactor $reactor) {
        if ($this->isMultipart) {
            $fields = $this->getMultipartFieldArray();
            return $this->generateMultipartIteratorFromFields($reactor, $fields);
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

    private function generateMultipartIteratorFromFields(Reactor $reactor, array $fields) {
        foreach ($fields as $key => $field) {
            $fields[$key] = $field instanceof FileBody ? $field->getBody($reactor) : $field;
        }

        $future = new Future($reactor);
        (new Combinator($reactor))->all($fields)->when(function($error, $result) use ($future) {
            if ($error) {
                $future->fail($error);
            } else {
                $this->cachedBody = $result;
                $future->succeed(new MultipartIterator($result));
            }
        });

        return $future->promise();
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
     * @param \Amp\Reactor $reactor
     * @return \Amp\Promise
     */
    public function getHeaders(Reactor $reactor) {
        $future = new Future($reactor);
        $length = $this->getLength($reactor);
        $length->when(function($error, $result) use ($future) {
            if ($error) {
                $future->fail($error);
            } else {
                $type = $this->determineContentType();
                $future->succeed([
                    'Content-Type' => $type,
                    'Content-Length' => $result
                ]);
            }
        });

        return $future->promise();
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
     * @param \Amp\Reactor $reactor
     * @return \Amp\Promise
     */
    public function getLength(Reactor $reactor) {
        if (isset($this->cachedLength)) {
            return new Success($this->cachedLength);
        } elseif ($this->isMultipart) {
            $fields = $this->getMultipartFieldArray($reactor);
            $length = $this->sumMultipartFieldLengths($reactor, $fields);
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

    private function sumMultipartFieldLengths(Reactor $reactor, array $fields) {
        $lengths = [];
        foreach ($fields as $field) {
            if (is_string($field)) {
                $lengths[] = strlen($field);
            } else {
                $lengths[] = $field->getLength($reactor);
            }
        }

        $future = new Future($reactor);
        (new Combinator($reactor))->all($lengths)->when(function($error, $result) use ($future) {
            if ($error) {
                $future->fail($error);
            } else {
                $future->succeed(array_sum($result));
            }
        });

        return $future->promise();
    }
}
