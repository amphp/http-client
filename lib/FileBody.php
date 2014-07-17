<?php

namespace Artax;

class FileBody extends ResourceBody implements AggregateBody {
    public function __construct($filePath) {
        $resource = $this->generateFileResourceFromPath($filePath);
        parent::__construct($resource);
    }

    private function generateFileResourceFromPath($filePath) {
        if (false === ($value = @fopen($filePath, 'r'))) {
            throw new \RuntimeException(
                'Failed reading file: ' . $filePath
            );
        }

        return $value;
    }

    public function getBody() {
        return $this;
    }

    /**
     * @TODO Determine Content-Type (mime type from filename)
     * @TODO Determine Content-Length
     */
    public function getHeaders() {
        return [];
    }
}
