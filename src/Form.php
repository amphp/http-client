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

final class Form implements Content
{
    /** @var FormField[] */
    private array $fields = [];

    private string $boundary;

    private bool $isMultipart = false;

    private ?ReadableStream $content = null;

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

    public function addText(string $name, string $content, string $contentType = 'text/plain; charset=utf-8'): void
    {
        if ($this->content !== null) {
            throw new \Error('Form body is already frozen and can no longer be modified');
        }

        $this->fields[] = new FormField($name, BufferedContent::text($content, $contentType));
    }

    /**
     * @param string|null $filename Must be provided to make this a file upload.
     */
    public function addStream(string $name, Content $content, ?string $filename = null): void
    {
        if ($this->content !== null) {
            throw new \Error('Form body is already frozen and can no longer be modified');
        }

        $this->fields[] = new FormField($name, $content, $filename);

        if ($filename !== null) {
            $this->isMultipart = true;
        }
    }

    public function getContent(): ReadableStream
    {
        if ($this->content !== null) {
            return $this->content;
        }

        if ($this->isMultipart) {
            return $this->content = $this->generateMultipartStream($this->getMultipartParts());
        }

        return $this->content = new ReadableBuffer($this->generateFormEncodedBody());
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
        if (!$this->isMultipart) {
            return \strlen($this->generateFormEncodedBody());
        }

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

        return $length;
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

        return QueryString::build($pairs) ?? '';
    }

    /**
     * @param (FormField|string)[] $parts
     * @return ReadableStream
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
