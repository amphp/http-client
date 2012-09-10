<?php

namespace Artax\Encoding;

interface Encoder {

    /**
     * @param string $dataToBeEncoded
     * @return string
     */
    function encode($dataToBeEncoded);
}