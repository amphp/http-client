<?php

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\File\Filesystem;
use Amp\File\FilesystemException;
use function Amp\File\read;

final class BufferedContent implements Content
{
    public static function binary(string $content, string $contentType = 'application/octet-stream'): BufferedContent
    {
        return new BufferedContent($content, $contentType);
    }

    public static function text(string $content, string $contentType = 'text/plain; charset=utf-8'): BufferedContent
    {
        return new BufferedContent($content, $contentType);
    }

    public static function json(string $content): BufferedContent
    {
        return new BufferedContent($content, 'application/json; charset=utf-8');
    }

    public static function file(
        string $path,
        string $contentType = 'application/octet-stream',
    ): BufferedContent {
        if (!\class_exists(Filesystem::class)) {
            throw new \Error("File request bodies require amphp/file to be installed");
        }

        try {
            return BufferedContent::binary(read($path), $contentType);
        } catch (FilesystemException $filesystemException) {
            throw new HttpException('Failed to open file: ' . $path, 0, $filesystemException);
        }
    }

    private function __construct(
        private readonly string $content,
        private readonly string $contentType,
    ) {
    }

    public function getContent(): ReadableStream
    {
        return new ReadableBuffer($this->content);
    }

    public function getContentLength(): int
    {
        return \strlen($this->content);
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
