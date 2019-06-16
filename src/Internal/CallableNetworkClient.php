<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Failure;
use Amp\Http\Client\Client;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;
use function Amp\call;

class CallableNetworkClient implements Client
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
        $callable = $this->callable;
        $this->callable = null;

        if ($callable === null) {
            return new Failure(new HttpException(__METHOD__ . ' may only be invoked once per instance. '
                . 'If you need to implement retries or otherwise issue multiple requests, register an ApplicationInterceptor to do so.'));
        }

        return call($callable);
    }

    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void
    {
        throw new \RuntimeException('Operation not supported');
    }
}
