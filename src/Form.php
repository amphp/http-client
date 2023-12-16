<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamChain;
use Amp\Http\Client\Internal\FormField;
use Amp\Http\Http1\Rfc7230;
use Amp\Http\InvalidHeaderException;
use League\Uri\QueryString;
use function Amp\ByteStream\buffer;

final class Form implements HttpContent
{
    /** @var FormField[] */
    private array $fields = [];

    private string $boundary;

    private bool $isMultipart = false;

    private bool $used = false;

    private ?ReadableStream $content = null;

    private ?int $contentLength = null;

    /**
     * @param string|null $boundary An optional multipart boundary string
     * @throws HttpException
     */
    public function __construct(?string $boundary = null)
    {
        try {
            $this->boundary = $boundary ?? \bin2hex(\random_bytes(16));
        } catch (\Exception $exception) {
            throw new HttpException('Failed to obtain random boundary', 0, $exception);
        }
    }

    public function addField(string $name, string $content, ?string $contentType = null): void
    {
        if ($this->used) {
            throw new \Error('Form body is already used and can no longer be modified');
        }

        $this->fields[] = new FormField($name, BufferedContent::fromString($content, $contentType));
    }

    /**
     * Adds each member of the array as an entry for the given key name. Array keys are persevered.
     *
     * @param array<string|array> $fields
     */
    public function addNestedFields(string $name, array $fields): void
    {
        foreach ($this->flattenArray($fields) as $key => $value) {
            $this->addField($name . $key, $value);
        }
    }

    /**
     * @return array<string, string>
     */
    private function flattenArray(array $fields): array
    {
        $result = [];
        foreach ($fields as $outerKey => $value) {
            $key = "[{$outerKey}]";
            if (!\is_array($value)) {
                $result[$key] = (string) $value;
                continue;
            }

            foreach ($this->flattenArray($value) as $innerKey => $flattened) {
                $result[$key . $innerKey] = $flattened;
            }
        }

        return $result;
    }

    public function addStream(string $name, HttpContent $content, ?string $filename = null): void
    {
        if ($this->used) {
            throw new \Error('Form body is already used and can no longer be modified');
        }

        $this->fields[] = new FormField($name, $content, $filename);
        $this->isMultipart = true;
    }

    /**
     * @param string $path Local file path. Filename will be provided to the server.
     * @throws HttpException
     */
    public function addFile(string $name, string $path, ?string $contentType = null): void
    {
        $this->addStream($name, StreamedContent::fromFile($path, $contentType), \basename($path));
    }

    public function getContent(): ReadableStream
    {
        $this->used = true;

        if ($this->content === null) {
            if ($this->isMultipart) {
                $this->content = $this->generateMultipartStream($this->getMultipartParts());
            } else {
                $this->content = new ReadableBuffer($this->generateFormEncodedBody());
            }
        }

        try {
            return $this->content;
        } finally {
            $this->content = null;
        }
    }

    public function getContentType(): string
    {
        return $this->isMultipart
            ? "multipart/form-data; boundary={$this->boundary}"
            : 'application/x-www-form-urlencoded';
    }

    /**
     * @throws HttpException
     */
    public function getContentLength(): ?int
    {
        if ($this->contentLength !== null) {
            return $this->contentLength;
        }

        if ($this->isMultipart) {
            $fields = $this->getMultipartParts();
            $length = 0;

            foreach ($fields as $field) {
                if (\is_string($field)) {
                    $length += \strlen($field);
                } else {
                    $contentLength = $field->getContentLength();
                    if ($contentLength === null) {
                        return null;
                    }

                    $length += $contentLength;
                }
            }

            return $this->contentLength = $length;
        }

        $body = $this->generateFormEncodedBody();
        $this->content = new ReadableBuffer($body);

        return $this->contentLength = \strlen($body);
    }

    /**
     * @throws HttpException
     */
    private function getMultipartParts(): array
    {
        try {
            $parts = [];

            foreach ($this->fields as $field) {
                $parts[] = "--{$this->boundary}\r\n" . Rfc7230::formatHeaderPairs($field->getHeaderPairs()) . "\r\n";
                $parts[] = $field;
                $parts[] = "\r\n";
            }

            $parts[] = "--{$this->boundary}--\r\n";

            return $parts;
        } catch (InvalidHeaderException|HttpException $e) {
            throw new HttpException('Failed to build request body', 0, $e);
        }
    }

    /**
     * @throws HttpException
     */
    private function generateFormEncodedBody(): string
    {
        $pairs = [];
        foreach ($this->fields as $field) {
            try {
                $pairs[] = [$field->getName(), buffer($field->getContent())];
            } catch (BufferException|HttpException $e) {
                throw new HttpException('Failed to build request body', 0, $e);
            }
        }

        /** @psalm-suppress InvalidArgument */
        return QueryString::build($pairs, '&', \PHP_QUERY_RFC1738) ?? '';
    }

    /**
     * @param (FormField|string)[] $parts
     * @throws HttpException
     */
    private function generateMultipartStream(array $parts): ReadableStream
    {
        $streams = [];
        foreach ($parts as $part) {
            if (\is_string($part)) {
                $streams[] = new ReadableBuffer($part);
            } else {
                $streams[] = $part->getContent();
            }
        }

        return new ReadableStreamChain(...$streams);
    }
}
