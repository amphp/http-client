<?php

namespace Amp\Http\Client\Body;

use Amp\ByteStream\ReadableStream;
use Amp\File;
use Amp\File\Filesystem;
use Amp\Http\Client\RequestBody;

final class FileBody implements RequestBody
{
    private string $path;

    private Filesystem $filesystem;

    /**
     * @param string $path The filesystem path for the file we wish to send
     * @param Filesystem|null $filesystem Use the global filesystem returned by Amp\File\filesystem() if null.
     */
    public function __construct(string $path, ?Filesystem $filesystem = null)
    {
        if (!\class_exists(Filesystem::class)) {
            throw new \Error("File request bodies require amphp/file to be installed");
        }

        $this->path = $path;
        $this->filesystem = $filesystem ?? File\filesystem();
    }

    public function createBodyStream(): ReadableStream
    {
        return $this->filesystem->openFile($this->path, "r");
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function getBodyLength(): int
    {
        return $this->filesystem->getSize($this->path);
    }
}
