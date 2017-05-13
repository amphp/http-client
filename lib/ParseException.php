<?php

namespace Amp\Artax;

class ParseException extends HttpException {
    private $parsedMsgArr;

    /**
     * Adds an array of parsed message values to the standard exception.
     *
     * @param array           $parsedMsgArr
     * @param string          $message
     * @param int             $errno
     * @param \Throwable|null $previousException
     */
    public function __construct(array $parsedMsgArr, string $message, int $errno, \Throwable $previousException = null) {
        parent::__construct($message, $errno, $previousException);
        $this->parsedMsgArr = $parsedMsgArr;
    }

    /**
     * Retrieve message values parsed prior to the error.
     *
     * @return array Message values parsed prior to the error
     */
    public function getParsedMsgArr() {
        return $this->parsedMsgArr;
    }
}
