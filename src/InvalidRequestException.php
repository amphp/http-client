<?php declare(strict_types=1);

namespace Amp\Http\Client;

final class InvalidRequestException extends HttpException
{
    private Request $request;

    public function __construct(Request $request, string $message, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->request = $request;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
