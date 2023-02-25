<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\HttpException;

final class UnprocessedRequestException extends HttpException
{
    public function __construct(HttpException $previous)
    {
        parent::__construct(
            \sprintf(
                'The request was not processed and can be safely retried; '
                . 'use %s::getPrevious() to get the previous exception object; '
                . 'Previous exception message: "%s"',
                self::class,
                $previous->getMessage(),
            ),
            previous: $previous
        );
    }
}
