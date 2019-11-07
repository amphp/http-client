<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Sync\KeyedSemaphore;
use Amp\Sync\Lock;
use function Amp\call;
use function Amp\coroutine;

abstract class LimitedConnectionPool implements ConnectionPool
{
    /** @var ConnectionPool */
    private $delegate;

    /** @var KeyedSemaphore */
    private $semaphore;

    public function __construct(ConnectionPool $delegate, KeyedSemaphore $mutex)
    {
        $this->delegate = $delegate;
        $this->semaphore = $mutex;
    }

    final public function getStream(Request $request, CancellationToken $token): Promise
    {
        return call(function () use ($request, $token) {
            /** @var Lock $lock */
            $lock = yield $this->semaphore->acquire($request->getUri()->getHost());

            /** @var Stream $stream */
            $stream = yield $this->delegate->getStream($request, $token);

            return HttpStream::fromStream(
                $stream,
                coroutine(static function (Request $request, CancellationToken $cancellationToken) use (
                    $stream,
                    $lock
                ) {
                    try {
                        return yield $stream->request($request, $cancellationToken);
                    } finally {
                        $lock->release();
                    }
                }),
                static function () use ($lock) {
                    $lock->release();
                }
            );
        });
    }

    abstract protected function getKey(Request $request): string;
}
