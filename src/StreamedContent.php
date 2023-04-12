<?php

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableStream;
use Amp\File\Filesystem;
use Amp\File\FilesystemException;
use function Amp\File\getSize;
use function Amp\File\openFile;

final class StreamedContent implements Content
{
    public static function fromStream(ReadableStream $content, ?int $contentLength = null, string $contentType = 'application/octet-stream'): StreamedContent
    {
        return new StreamedContent($content, $contentLength, $contentType);
    }

    /**
     * @throws HttpException
     */
    public static function fromLocalFile(
        string $path,
        string $contentType = 'application/octet-stream',
    ): StreamedContent {
        if (!\class_exists(Filesystem::class)) {
            throw new \Error("File request bodies require amphp/file to be installed");
        }

        try {
            return StreamedContent::fromStream(openFile($path, 'r'), getSize($path), $contentType);
        } catch (FilesystemException $filesystemException) {
            throw new HttpException('Failed to open file: ' . $path, 0, $filesystemException);
        }
    }

    private function __construct(
        private readonly ReadableStream $content,
        private readonly ?int $contentLength,
        private readonly string $contentType,
    ) {
    }

    public function getContent(): ReadableStream
    {
        return $this->content;
    }

    public function getContentLength(): ?int
    {
        return $this->contentLength;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
