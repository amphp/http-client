<?php

namespace Artax\Encoding;

interface Decoder {

    /**
     * @param string $dataToBeDecoded
     * @return string
     */
    function decode($dataToBeDecoded);
}