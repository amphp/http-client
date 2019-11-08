<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\KeyExtractor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Sync\KeyedSemaphore;
use Amp\Sync\Lock;
use function Amp\call;
use function Amp\coroutine;

final class LimitedConnectionPool implements ConnectionPool
{
    /** @var ConnectionPool */
    private $delegate;

    /** @var KeyedSemaphore */
    private $semaphore;

    /** @var KeyExtractor */
    private $keyExtractor;

    public function __construct(ConnectionPool $delegate, KeyedSemaphore $semaphore, KeyExtractor $keyExtractor)
    {
        $this->delegate = $delegate;
        $this->semaphore = $semaphore;
        $this->keyExtractor = $keyExtractor;
    }

    public function getStream(Request $request, CancellationToken $token): Promise
    {
        return call(function () use ($request, $token) {
            /** @var Lock $lock */
            $lock = yield $this->semaphore->acquire($this->keyExtractor->getKey($request));

            /** @var Stream $stream */
            $stream = yield $this->delegate->getStream($request, $token);

            return HttpStream::fromStream(
                $stream,
                coroutine(static function (Request $request, CancellationToken $cancellationToken) use (
                    $stream,
                    $lock
                ) {
                    try {
                        /** @var Response $response */
                        $response = yield $stream->request($request, $cancellationToken);

                        // await response being completely received
                        $response->getTrailers()->onResolve(static function () use ($lock) {
                            $lock->release();
                        });
                    } catch (\Throwable $e) {
                        $lock->release();

                        throw $e;
                    }

                    return $response;
                }),
                static function () use ($lock) {
                    $lock->release();
                }
            );
        });
    }
}
