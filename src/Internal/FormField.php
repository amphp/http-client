<?php declare(strict_types=1);

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpContent;
use Amp\Http\Client\HttpException;
use Amp\Http\HttpMessage;

/** @internal */
final class FormField extends HttpMessage implements HttpContent
{
    public function __construct(
        private readonly string $name,
        private readonly HttpContent $content,
        private readonly ?string $filename = null,
    ) {
        $contentType = $content->getContentType();

        if ($this->filename === null) {
            $this->replaceHeaders([
                'Content-Disposition' => "form-data; name=\"{$name}\"",
                'Content-Type' => $contentType === '' || $contentType === null ? [] : $contentType,
            ]);
        } else {
            $this->replaceHeaders([
                'Content-Disposition' => "form-data; name=\"{$name}\"; filename=\"{$filename}\"",
                'Content-Type' => $contentType === '' || $contentType === null ? [] : $contentType,
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
    #[\Override]
    public function getContent(): ReadableStream
    {
        return $this->content->getContent();
    }

    /**
     * @throws HttpException
     */
    #[\Override]
    public function getContentLength(): ?int
    {
        return $this->content->getContentLength();
    }

    #[\Override]
    public function getContentType(): ?string
    {
        return $this->content->getContentType();
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
