<?php

namespace Artax\Encoding;

use Spl\DomainException;

class CodecFactory {

    /**
     * @param string $codecType
     * @return mixed
     * @throws Spl\DomainException
     */
    public function make($codecType) {
        switch (strtolower($codecType)) {
            case 'gzip':
                return new GzipCodec;
            case 'deflate':
                return new DeflateCodec;
            default:
                throw new DomainException(
                    "Invalid codec type specified: $codecType"
                );
        }
    }
}