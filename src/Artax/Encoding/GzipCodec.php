<?php
/**
 * GzipCodec Class File
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Encoding;

/**
 * A codec for encoding/decoding gzip compressed data
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class GzipCodec implements Codec {
    
    /**
     * @param string $dataToBeEncoded
     * @return string
     * @throws CodecException
     */
    public function encode($dataToBeEncoded) {
        $encoded = $this->doEncode($dataToBeEncoded);
        
        if ($encoded !== false) {
            return $encoded;
        } else {
            throw new CodecException('Gzip encode failure');
        }
    }
    
    /**
     * @param string $dataToBeDecoded
     * @return string
     * @throws CodecException
     */
    public function decode($dataToBeDecoded) {
        $decoded = $this->doDecode($dataToBeDecoded);
        
        if ($decoded !== false) {
            return $decoded;
        } else {
            throw new CodecException('Gzip decode failure');
        }
    }
    
    /**
     * @param string $dataToBeEncoded
     * @return mixed Returns encoded string or false on failure
     */
    protected function doEncode($dataToBeEncoded) {
        return gzencode($dataToBeEncoded);
    }
    
    /**
     * @param string $dataToBeDecoded
     * @return mixed Returns decoded string or false on failure
     */
    protected function doDecode($dataToBeDecoded) {
        return gzdecode($dataToBeDecoded);
    }
}
