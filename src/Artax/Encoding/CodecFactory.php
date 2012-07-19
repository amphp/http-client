<?php
/**
 * CodecFactory Class File
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Encoding;

use DomainException;

/**
 * Generates Codec instances
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class CodecFactory {
    
    /**
     * @param string $codecType
     * @return mixed
     * @throws DomainException
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
