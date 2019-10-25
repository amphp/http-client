<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Promise;
use function Amp\call;

final class RetryRequests implements ApplicationInterceptor
{
    /** @var int */
    private $retryLimit;

    public function __construct(int $retryLimit)
    {
        $this->retryLimit = $retryLimit;
    }

    public function request(Request $request, CancellationToken $cancellation, DelegateHttpClient $next): Promise
    {
        return call(function () use ($request, $cancellation, $next) {
            $attempt = 1;

            do {
                try {
                    return yield $next->request(clone $request, $cancellation);
                } catch (UnprocessedRequestException $exception) {
                    // Request was deemed retryable by connection, so carry on.
                } catch (SocketException $exception) {
                    if (!$request->isIdempotent()) {
                        throw $exception;
                    }

                    // Request can safely be retried.
                }
            } while ($attempt++ <= $this->retryLimit);

            throw $exception;
        });
    }
}
