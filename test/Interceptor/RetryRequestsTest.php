<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;

class RetryRequestsTest extends AsyncTestCase
{
    public function testFailedAttemptIsReleasedBeforeTheNextOne(): void
    {
        $client = new class implements DelegateHttpClient {
            /** @var list<bool> */
            public array $previousAttemptAlive = [];

            private ?\WeakReference $previousAttempt = null;

            public function request(Request $request, Cancellation $cancellation): Response
            {
                $this->previousAttemptAlive[] = $this->previousAttempt?->get() !== null;

                $exception = new HttpException('Request failed');
                $this->previousAttempt = \WeakReference::create($exception);

                throw $exception;
            }
        };

        $interceptor = new RetryRequests(2);

        try {
            $interceptor->request(new Request('http://localhost'), new NullCancellation(), $client);
            self::fail('Expected the request to fail');
        } catch (HttpException) {
            self::assertSame([false, false, false], $client->previousAttemptAlive);
        }
    }

    public function testLastFailureIsThrownAfterRetryLimit(): void
    {
        $client = new class implements DelegateHttpClient {
            public int $attempts = 0;

            public function request(Request $request, Cancellation $cancellation): Response
            {
                throw new HttpException('Attempt ' . ++$this->attempts);
            }
        };

        $interceptor = new RetryRequests(2);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Attempt 3');

        $interceptor->request(new Request('http://localhost'), new NullCancellation(), $client);
    }
}
