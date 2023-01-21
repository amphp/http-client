<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Response;

final class AddResponseHeader extends ModifyResponse
{
    /**
     * @param non-empty-string $headerName
     */
    public function __construct(string $headerName, string ...$headerValues)
    {
        parent::__construct(static function (Response $response) use ($headerName, $headerValues) {
            $response->addHeader($headerName, $headerValues);

            return $response;
        });
    }
}
