<?php

namespace Amp\Artax;

use Amp\{
    ByteStream\InMemoryStream, ByteStream\InputStream, ByteStream\Message, Producer, Promise, Success
};
use function Amp\call;

class FormBody implements AggregateBody {
    private $fields = [];
    private $boundary;
    private $isMultipart = false;

    private $cachedBody;
    private $cachedLength;
    private $cachedFields;

    /**
     * @param string $boundary An optional multipart boundary string
     */
    public function __construct(string $boundary = null) {
        $this->boundary = $boundary ?? \hash("sha256", \random_bytes(16));
    }

    /**
     * Add a data field to the form entity body.
     *
     * @param string $name
     * @param string $value
     * @param string $contentType
     */
    public function addField(string $name, string $value, string $contentType = 'text/plain') {
        $this->fields[] = [$name, $value, $contentType, null];
        $this->resetCache();
    }

    /**
     * Add each element of a associative array as a data field to the form entity body.
     *
     * @param array  $data
     * @param string $contentType
     */
    public function addFields(array $data, string $contentType = 'text/plain') {
        foreach ($data as $key => $value) {
            $this->addField($key, $value, $contentType);
        }
    }

    /**
     * Add a file field to the form entity body.
     *
     * @param string $name
     * @param string $filePath
     * @param string $contentType
     */
    public function addFile(string $name, string $filePath, string $contentType = 'application/octet-stream') {
        $fileName = \basename($filePath);
        $this->fields[] = [$name, new FileBody($filePath), $contentType, $fileName];
        $this->isMultipart = true;
        $this->resetCache();
    }

    /**
     * Add each element of a associative array as a file field to the form entity body.
     *
     * @param array  $data
     * @param string $contentType
     */
    public function addFiles(array $data, string $contentType = 'application/octet-stream') {
        foreach ($data as $key => $value) {
            $this->addFile($key, $value, $contentType);
        }
    }

    private function resetCache() {
        $this->cachedBody = null;
        $this->cachedLength = null;
        $this->cachedFields = null;
    }

    public function getBody(): InputStream {
        if ($this->isMultipart) {
            $fields = $this->getMultipartFieldArray();
            return $this->generateMultipartStreamFromFields($fields);
        } else {
            return new InMemoryStream($this->getFormEncodedBodyString());
        }
    }

    private function getMultipartFieldArray(): array {
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

    private function generateMultipartFileHeader(string $name, string $fileName, string $contentType): string {
        $header = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\n";
        $header .= "Content-Type: {$contentType}\r\n";
        $header .= "Content-Transfer-Encoding: binary\r\n\r\n";

        return $header;
    }

    private function generateMultipartFieldHeader(string $name, string $contentType): string {
        $header = "Content-Disposition: form-data; name=\"{$name}\"\r\n";
        $header .= "Content-Type: {$contentType}\r\n\r\n";

        return $header;
    }

    private function generateMultipartStreamFromFields(array $fields): InputStream {
        foreach ($fields as $key => $field) {
            $fields[$key] = $field instanceof FileBody ? $field->getBody() : new InMemoryStream($field);
        }

        return new Message(new Producer(function (callable $emit) use ($fields) {
            foreach ($fields as $key => $stream) {
                while (($chunk = yield $stream->read()) !== null) {
                    yield $emit($chunk);
                }
            }
        }));
    }

    private function getFormEncodedBodyString(): string {
        $fields = [];

        foreach ($this->fields as $fieldArr) {
            list($name, $value) = $fieldArr;
            $fields[$name][] = $value;
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = isset($value[1]) ? $value : $value[0];
        }

        return \http_build_query($fields);
    }

    public function getHeaders(): Promise {
        return new Success([
            'Content-Type' => $this->determineContentType(),
        ]);
    }

    private function determineContentType() {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

    public function getBodyLength(): Promise {
        if (!$this->isMultipart) {
            return new Success(\strlen($this->getFormEncodedBodyString()));
        }

        return call(function () {
            $fields = $this->getMultipartFieldArray();
            $length = 0;

            foreach ($fields as $field) {
                if (is_string($field)) {
                    $length += \strlen($field);
                } else {
                    $length += yield $field->getBodyLength();
                }
            }

            return $length;
        });
    }
}
