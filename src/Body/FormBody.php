<?php

namespace Amp\Http\Client\Body;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\Http\Client\RequestBody;
use Amp\Producer;
use Amp\Promise;
use Amp\Success;
use function Amp\call;

final class FormBody implements RequestBody
{
    /** @var (array{0: string, 1: string, 2: string, 3: null}|array{0: string, 1: FileBody, 2: string, 3: string})[] */
    private $fields = [];
    /** @var string */
    private $boundary;
    /** @var bool */
    private $isMultipart = false;

    /** @var string|null */
    private $cachedBody;
    /** @var Promise<int>|null */
    private $cachedLength;
    /** @var list<string|FileBody>|null */
    private $cachedFields;

    /**
     * @param string $boundary An optional multipart boundary string
     */
    public function __construct(string $boundary = null)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->boundary = $boundary ?? \bin2hex(\random_bytes(16));
    }

    /**
     * Add a data field to the form entity body.
     *
     * @param string $name
     * @param string $value
     * @param string $contentType
     */
    public function addField(string $name, string $value, string $contentType = 'text/plain'): void
    {
        $this->fields[] = [$name, $value, $contentType, null];
        $this->resetCache();
    }

    /**
     * Add each element of a associative array as a data field to the form entity body.
     *
     * @param array  $data
     * @param string $contentType
     */
    public function addFields(array $data, string $contentType = 'text/plain'): void
    {
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
    public function addFile(string $name, string $filePath, string $contentType = 'application/octet-stream'): void
    {
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
    public function addFiles(array $data, string $contentType = 'application/octet-stream'): void
    {
        foreach ($data as $key => $value) {
            $this->addFile($key, $value, $contentType);
        }
    }

    /**
     * Add a file field to the form from a string.
     *
     * @param string $name
     * @param string $fileContent
     * @param string $fileName
     * @param string $contentType
     */
    public function addFileFromString(string $name, string $fileContent, string $fileName, string $contentType = 'application/octet-stream'): void
    {
        $this->fields[] = [$name, $fileContent, $contentType, $fileName];
        $this->isMultipart = true;
        $this->resetCache();
    }

    /**
     * Returns an array of fields, each being an array of [name, value, content-type, file-name|null].
     * Both fields and files are returned in the array. Files use a FileBody object as the value. The file-name is
     * always null for fields.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    private function resetCache(): void
    {
        $this->cachedBody = null;
        $this->cachedLength = null;
        $this->cachedFields = null;
    }

    public function createBodyStream(): InputStream
    {
        if ($this->isMultipart) {
            return $this->generateMultipartStreamFromFields($this->getMultipartFieldArray());
        }

        return new InMemoryStream($this->getFormEncodedBodyString());
    }

    private function getMultipartFieldArray(): array
    {
        if (isset($this->cachedFields)) {
            return $this->cachedFields;
        }

        $fields = [];

        foreach ($this->fields as $fieldArr) {
            [$name, $field, $contentType, $fileName] = $fieldArr;

            $fields[] = "--{$this->boundary}\r\n";

            /** @psalm-suppress PossiblyNullArgument */
            $fields[] = $fileName !== null
                ? $this->generateMultipartFileHeader($name, $fileName, $contentType)
                : $this->generateMultipartFieldHeader($name, $contentType);

            $fields[] = $field;
            $fields[] = "\r\n";
        }

        $fields[] = "--{$this->boundary}--\r\n";

        return $this->cachedFields = $fields;
    }

    private function generateMultipartFileHeader(string $name, string $fileName, string $contentType): string
    {
        $header = "Content-Disposition: form-data; name=\"{$name}\"; filename=\"{$fileName}\"\r\n";
        $header .= "Content-Type: {$contentType}\r\n";
        $header .= "Content-Transfer-Encoding: binary\r\n\r\n";

        return $header;
    }

    private function generateMultipartFieldHeader(string $name, string $contentType): string
    {
        $header = "Content-Disposition: form-data; name=\"{$name}\"\r\n";
        if ($contentType !== "") {
            $header .= "Content-Type: {$contentType}\r\n\r\n";
        } else {
            $header .= "\r\n";
        }

        return $header;
    }

    private function generateMultipartStreamFromFields(array $fields): InputStream
    {
        foreach ($fields as $key => $field) {
            $fields[$key] = $field instanceof FileBody ? $field->createBodyStream() : new InMemoryStream($field);
        }

        return new IteratorStream(new Producer(static function (callable $emit) use ($fields) {
            foreach ($fields as $key => $stream) {
                while (($chunk = yield $stream->read()) !== null) {
                    yield $emit($chunk);
                }
            }
        }));
    }

    private function getFormEncodedBodyString(): string
    {
        if ($this->cachedBody) {
            return $this->cachedBody;
        }

        $fields = [];

        foreach ($this->fields as $fieldArr) {
            [$name, $value] = $fieldArr;
            $fields[$name][] = $value;
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = isset($value[1]) ? $value : $value[0];
        }

        return $this->cachedBody = \http_build_query($fields);
    }

    public function getHeaders(): Promise
    {
        return new Success([
            'Content-Type' => $this->determineContentType(),
        ]);
    }

    private function determineContentType(): string
    {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

    public function getBodyLength(): Promise
    {
        if ($this->cachedLength) {
            return $this->cachedLength;
        }

        if (!$this->isMultipart) {
            return $this->cachedLength = new Success(\strlen($this->getFormEncodedBodyString()));
        }

        /** @var Promise<int> $lengthPromise */
        $lengthPromise = call(function (): \Generator {
            $fields = $this->getMultipartFieldArray();
            $length = 0;

            foreach ($fields as $field) {
                if (\is_string($field)) {
                    $length += \strlen($field);
                } else {
                    $length += yield $field->getBodyLength();
                }
            }

            return $length;
        });

        return $this->cachedLength = $lengthPromise;
    }
}
