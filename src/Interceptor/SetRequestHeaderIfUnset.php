<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

final class SetRequestHeaderIfUnset extends ModifyRequest
{
    /**
     * @param non-empty-string $headerName
     */
    public function __construct(string $headerName, string $headerValue, string ...$headerValues)
    {
        \array_unshift($headerValues, $headerValue);

        parent::__construct(static function (Request $request) use ($headerName, $headerValues) {
            if (!$request->hasHeader($headerName)) {
                $request->setHeader($headerName, $headerValues);
            }

            return $request;
        });
    }
}
