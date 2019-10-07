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
use Amp\MultiReasonException;
use Amp\Promise;
use function Amp\call;

final class RetryRequests implements ApplicationInterceptor
{
    /** @var int */
    private $retryLimit;

    public function __construct(int $retryLimit)
    {
        if ($retryLimit < 1) {
            throw new \Error('The number of retries must be 1 or greater');
        }

        $this->retryLimit = $retryLimit;
    }

    public function request(Request $request, CancellationToken $cancellation, Client $client): Promise
    {
        return call(function () use ($request, $cancellation, $client) {
            $attempts = 0;
            $exceptions = [];

            do {
                ++$attempts;

                try {
                    return yield $client->request(clone $request, $cancellation);
                } catch (UnprocessedRequestException $exception) {
                    // Request was deemed retryable by connection, so carry on.
                    $exceptions[] = $exception->getPrevious();
                } catch (InvalidRequestException | ParseException $exception) {
                    // Request is or response is invalid, so do not retry.
                    throw $exception;
                } catch (HttpException $exception) {
                    if (!$request->isIdempotent()) {
                        throw $exception;
                    }
                    // Request can safely be retried.
                    $exceptions[] = $exception;
                }
            } while ($attempts <= $this->retryLimit);

            throw new HttpException(
                'Request failed after ' . $attempts . ' attempts',
                0,
                new MultiReasonException($exceptions)
            );
        });
    }
}
