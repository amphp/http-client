<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use Amp\Promise;
use function Amp\call;

final class RetryRequests implements ApplicationInterceptor
{
    /** @var int */
    private $retryLimit;

    public function __construct(int $retryLimit = 2)
    {
        $this->retryLimit = $retryLimit;
    }

    public function request(Request $request, CancellationToken $cancellation, Client $client): Promise
    {
        return call(function () use ($request, $cancellation, $client) {
            $attempts = 0;

            do {
                try {
                    return yield $client->request(clone $request, $cancellation);
                } catch (UnprocessedRequestException $exception) {
                    // Request was deemed retryable by connection, so carry on.
                } catch (InvalidRequestException $exception) {
                    // Request is invalid, so do not retry.
                    throw $exception;
                } catch (HttpException $exception) {
                    if (!$this->isRetryable($request)) {
                        throw $exception;
                    }
                    // Request can safely be retried.
                }
            } while (++$attempts < $this->retryLimit);

            if ($exception instanceof UnprocessedRequestException) {
                throw $exception->getPrevious();
            }

            throw $exception;
        });
    }

    private function isRetryable(Request $request): bool
    {
        // https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
        return \in_array($request->getMethod(), ['GET', 'HEAD', 'PUT', 'DELETE'], true);
    }
}
