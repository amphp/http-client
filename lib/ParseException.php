<?php

namespace Amp\Artax;

class ParseException extends ClientException {
    private $parsedMsgArr;

    /**
     * Adds an array of parsed message values to the standard exception
     */
    public function __construct(array $parsedMsgArr, $msg, $errno, \Exception $previousException = null) {
        $this->parsedMsgArr = $parsedMsgArr;
        /** @noinspection PhpParamsInspection */
        parent::__construct($msg, $errno, $previousException);
    }

    /**
     * Retrieve message values parsed prior to the error
     *
     * @return array Message values parsed prior to the error
     */
    public function getParsedMsgArr() {
        return $this->parsedMsgArr;
    }
}

