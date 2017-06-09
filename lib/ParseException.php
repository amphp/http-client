<?php

namespace Amp\Artax;

class ParseException extends HttpException {
    private $parserResult;

    /**
     * Adds an array of parsed message values to the standard exception.
     *
     * @param array           $parserResult
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previousException
     */
    public function __construct(array $parserResult, string $message, int $code, \Throwable $previousException = null) {
        parent::__construct($message, $code, $previousException);
        $this->parserResult = $parserResult;
    }

    /**
     * Retrieve message values parsed prior to the error.
     *
     * @return array Message values parsed prior to the error
     */
    public function getParserResult(): array {
        return $this->parserResult;
    }
}
