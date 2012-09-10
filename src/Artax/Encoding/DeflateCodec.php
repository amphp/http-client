<?php

namespace Artax\Encoding;

class DeflateCodec implements Codec {

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
            throw new CodecException('DEFLATE encode failure');
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
            throw new CodecException('DEFLATE decode failure');
        }
    }

    /**
     * @param string $dataToBeEncoded
     * @return mixed Returns encoded string or false on failure
     */
    protected function doEncode($dataToBeEncoded) {
        return gzdeflate($dataToBeEncoded);
    }

    /**
     * @param string $dataToBeDecoded
     * @return mixed Returns decoded string or false on failure
     */
    protected function doDecode($dataToBeDecoded) {
        return gzinflate($dataToBeDecoded);
    }
}