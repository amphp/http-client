<?php

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableStream;
use Amp\Http\HttpMessage;

final class FormField extends HttpMessage implements Content
{
    /**
     * @throws HttpException
     */
    public static function text(string $name, string $content, string $contentType = 'text/plain; charset=utf-8'): self
    {
        return new self($name, BufferedContent::text($content, $contentType));
    }

    /**
     * @param string|null $filename Must be provided to make this a file upload.
     *
     * @throws HttpException
     */
    public static function stream(string $name, Content $content, ?string $filename = null): self
    {
        return new self($name, $content, $filename);
    }

    /**
     * @throws HttpException
     */
    private function __construct(
        private readonly string $name,
        private readonly Content $content,
        private readonly ?string $filename = null,
    ) {
        $contentType = $content->getContentType();

        if ($this->filename === null) {
            $this->replaceHeaders([
                'Content-Disposition' => "form-data; name=\"{$name}\"",
                'Content-Type' => $contentType === '' ? [] : $contentType,
            ]);
        } else {
            $this->replaceHeaders([
                'Content-Disposition' => "form-data; name=\"{$name}\"; filename=\"{$filename}\"",
                'Content-Type' => $contentType === '' ? [] : $contentType,
                'Content-Transfer-Encoding' => 'binary',
            ]);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws HttpException
     */
    public function getContent(): ReadableStream
    {
        return $this->content->getContent();
    }

    /**
     * @throws HttpException
     */
    public function getContentLength(): ?int
    {
        return $this->content->getContentLength();
    }

    public function getContentType(): string
    {
        return $this->content->getContentType();
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
