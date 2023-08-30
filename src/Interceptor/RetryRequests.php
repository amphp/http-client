<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

final class RetryRequests implements ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    private int $retryLimit;

    public function __construct(int $retryLimit)
    {
        $this->retryLimit = $retryLimit;
    }

    public function request(
        Request $request,
        Cancellation $cancellation,
        DelegateHttpClient $httpClient
    ): Response {
        $attempt = 1;

        do {
            $clonedRequest = clone $request;

            try {
                return $httpClient->request($request, $cancellation);
            } catch (HttpException $exception) {
                if ($request->isIdempotent() || $request->isUnprocessed()) {
                    // Request was deemed retryable by connection, so carry on.
                    $request = $clonedRequest;
                    continue;
                }

                throw $exception;
            }
        } while ($attempt++ <= $this->retryLimit);

        throw $exception;
    }
}
