<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Cancellation;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

class RetryRequestsTest extends InterceptorTest
{
    public function testRetry(): void
    {
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'before'));
        $this->givenApplicationInterceptor(new RetryRequests(2));
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'after'));
        $this->givenNetworkInterceptor(new class implements NetworkInterceptor {
            public function requestViaNetwork(Request $request, Cancellation $cancellation, Stream $stream): Response
            {
                static $i = 0;

                if (++$i === 1) {
                    throw new HttpException('Failed');
                }

                return $stream->request($request, $cancellation);
            }
        });

        $request = new Request('http://example.com');

        $this->whenRequestIsExecuted($request);

        $this->thenRequestHasHeader('foo', 'before', 'after');

        self::assertNotSame($request, $this->getRequest());
    }
}
