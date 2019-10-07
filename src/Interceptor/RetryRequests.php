<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\UnprocessedRequestException;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
use Amp\Http\Client\TimeoutException;
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

    public function request(Request $request, CancellationToken $cancellation, Client $client): Promise
    {
        return call(function () use ($request, $cancellation, $client) {
            $attempts = 1;

            do {
                try {
                    return yield $client->request(clone $request, $cancellation);
                } catch (UnprocessedRequestException $exception) {
                    // Request was deemed retryable by connection, so carry on.
                } catch (InvalidRequestException | ParseException | TimeoutException $exception) {
                    // Request is or response is invalid or request timed out, so do not retry.
                    throw $exception;
                } catch (HttpException $exception) {
                    if (!$request->isIdempotent()) {
                        throw $exception;
                    }
                    // Request can safely be retried.
                }
            } while ($attempts++ <= $this->retryLimit);

            if ($exception instanceof UnprocessedRequestException) {
                throw $exception->getPrevious();
            }

            throw $exception;
        });
    }
}
