<?php

namespace Artax;

use Alert\Reactor;
use After\Future;
use After\Success;

class FormBody implements AggregateBody {
    private $fields = [];
    private $boundary;
    private $isMultipart = false;
    private $cachedBody;
    private $cachedHeaders;

    public function __construct($boundary = null) {
        $this->boundary = $boundary ?: md5(uniqid());
    }

    public function addField($name, $value, $contentType = 'text/plain') {
        $this->fields[] = [(string) $name, (string) $value, (string) $contentType, $fileName = null];
        $this->cachedBody = $this->cachedHeaders = null;

        return $this;
    }

    public function addFileField($name, $filePath, $contentType = 'application/octet-stream') {
        $filePath = (string) $filePath;
        $fileName = basename($filePath);
        $this->fields[] = [(string) $name, new FileBody($filePath), $contentType, $fileName];
        $this->isMultipart = true;
        $this->cachedBody = $this->cachedHeaders = null;

        return $this;
    }

    /**
     * @param \Alert\Reactor $reactor
     * @return \After\Promise
     */
    public function getBody(Reactor $reactor) {
        return $this->isMultipart
            ? $this->getIterableMultipartBody($reactor)
            : $this->getFormEncodedBodyString();
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

        return new Success(http_build_query($fields));
    }

    private function getIterableMultipartBody($reactor) {
        if ($this->cachedBody) {
            return new Success($this->cachedBody);
        }

        $fields = [];

        foreach ($this->fields as $fieldArr) {
            list($name, $field, $contentType, $fileName) = $fieldArr;

            $fields[] = "--{$this->boundary}\r\n";
            $fields[] = $field instanceof FileBody
                ? $this->generateMultipartFileHeader($name, $fileName, $contentType)
                : $this->generateMultipartFieldHeader($name, $contentType);

            $fields[] = $field instanceof FileBody ? $field->getBody($reactor) : $field;
            $fields[] = "\r\n";
        }

        $fields[] = "--{$this->boundary}--";

        $this->cachedBody = new MultipartIterator($fields);

        return new Success($this->cachedBody);
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

    /**
     * Retrieve a key-value array of headers to add to the outbound request
     *
     * @param \Alert\Reactor $reactor
     * @return \After\Promise
     */
    public function getHeaders(Reactor $reactor) {
        if ($this->cachedHeaders) {
            return new Success($this->cachedHeaders);
        }

        $future = new Future;
        $this->getBody($reactor)->when(function($error, $result) use ($reactor, $future) {
            if ($error) {
                $future->fail($error);
            } else {
                $this->determineContentLength($reactor, $result, $future);
            }
        });

        return $future->promise();
    }

    private function determineContentType() {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

    private function determineContentLength($reactor, $body, $future) {
        if (is_string($body)) {
            $this->cachedHeaders = [
                'Content-Type' => $this->determineContentType(),
                'Content-Length' => strlen($body)
            ];
            $future->succeed($this->cachedHeaders);
        } else {
            $this->determineMultipartLength($reactor, $body, $future);
        }
    }

    private function determineMultipartLength($reactor, $body, $future) {
        $lengths = array_map(function($el) use ($reactor) {
            return is_string($el) ? strlen($el) : $el;
        }, $body->getFields());

        \After\all($lengths)->when(function($error, $result) use ($future) {
            if ($error) {
                $future->fail($error);
            } else {
                $this->cachedHeaders = [
                    'Content-Type' => $this->determineContentType(),
                    'Content-Length' => $this->countResolvedLengths($result)
                ];
                $future->succeed($this->cachedHeaders);
            }
        });
    }

    private function countResolvedLengths(array $lengths) {
        $length = 0;
        foreach ($lengths as $el) {
            if (is_int($el)) {
                $length += $el;
            } elseif (isset($el['Content-Length'])) {
                $length += is_array($el['Content-Length'])
                    ? $el['Content-Length'][0]
                    : $el['Content-Length'];
            }
        }

        return $length;
    }
}
