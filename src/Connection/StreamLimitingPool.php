<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Sync\KeyedSemaphore;
use function Amp\coroutine;

final class StreamLimitingPool implements ConnectionPool
{
    use ForbidCloning;
    use ForbidSerialization;

    public static function byHost(ConnectionPool $delegate, KeyedSemaphore $semaphore): self
    {
        return new self($delegate, $semaphore, static function (Request $request) {
            return $request->getUri()->getHost();
        });
    }

    public static function byStaticKey(
        ConnectionPool $delegate,
        KeyedSemaphore $semaphore,
        string $key = ''
    ): self {
        return new self($delegate, $semaphore, static function () use ($key) {
            return $key;
        });
    }

    public static function byCustomKey(
        ConnectionPool $delegate,
        KeyedSemaphore $semaphore,
        callable $requestToKeyMapper
    ): self {
        return new self($delegate, $semaphore, $requestToKeyMapper);
    }

    private ConnectionPool $delegate;

    private KeyedSemaphore $semaphore;

    /** @var callable */
    private $requestToKeyMapper;

    private function __construct(ConnectionPool $delegate, KeyedSemaphore $semaphore, callable $requestToKeyMapper)
    {
        $this->delegate = $delegate;
        $this->semaphore = $semaphore;
        $this->requestToKeyMapper = $requestToKeyMapper;
    }

    public function getStream(Request $request, CancellationToken $cancellation): Stream
    {
        $lock = $this->semaphore->acquire(($this->requestToKeyMapper)($request));

        $stream = $this->delegate->getStream($request, $cancellation);

        return HttpStream::fromStream(
            $stream,
            static function (Request $request, CancellationToken $cancellationToken) use (
                $stream,
                $lock
            ): Response {
                try {
                    $response = $stream->request($request, $cancellationToken);
                } catch (\Throwable $e) {
                    $lock->release();
                    throw $e;
                }

                // await response being completely received
                coroutine(static function () use ($response, $lock): void {
                    try {
                        $response->getTrailers()->await();
                    } finally {
                        $lock->release();
                    }
                })->ignore();

                return $response;
            },
            static function () use ($lock): void {
                $lock->release();
            }
        );
    }
}
