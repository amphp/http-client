<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableStream;
use Amp\File\Filesystem;
use Amp\File\FilesystemException;
use function Amp\File\getSize;
use function Amp\File\openFile;

final class StreamedContent implements HttpContent
{
    public static function fromStream(ReadableStream $content, ?int $contentLength = null, ?string $contentType = null): self
    {
        return new self($content, $contentLength, $contentType);
    }

    /**
     * @throws HttpException
     */
    public static function fromFile(
        string $path,
        ?string $contentType = null,
    ): self {
        if (!\class_exists(Filesystem::class)) {
            throw new \Error("File request bodies require amphp/file to be installed");
        }

        try {
            return self::fromStream(openFile($path, 'r'), getSize($path), $contentType);
        } catch (FilesystemException $filesystemException) {
            throw new HttpException('Failed to open file: ' . $path, 0, $filesystemException);
        }
    }

    private ?ReadableStream $content;

    private function __construct(
        ReadableStream $content,
        private readonly ?int $contentLength,
        private readonly ?string $contentType,
    ) {
        $this->content = $content;
    }

    public function getContent(): ReadableStream
    {
        if ($this->content === null) {
            throw new HttpException('The content has already been streamed and can\'t be streamed again');
        }

        try {
            return $this->content;
        } finally {
            $this->content = null;
        }
    }

    public function getContentLength(): ?int
    {
        return $this->contentLength;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}
