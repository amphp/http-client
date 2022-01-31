<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Pipeline\Queue;
use Revolt\EventLoop;

/**
 * Used in Http2Connection.
 *
 * @internal
 */
final class Http2Stream
{
    use ForbidSerialization;
    use ForbidCloning;

    public int $id;

    public Request $request;

    public ?Response $response = null;

    public ?DeferredFuture $pendingResponse;

    public ?Future $preResponseResolution = null;

    public bool $responsePending = true;

    public ?Queue $body = null;

    public ?DeferredFuture $trailers = null;

    public Cancellation $originalCancellation;

    public Cancellation $cancellationToken;

    /** @var int Bytes received on the stream. */
    public int $received = 0;

    public int $serverWindow;

    public int $clientWindow;

    public int $bufferSize;

    public string $requestBodyBuffer = '';

    public DeferredFuture $requestBodyCompletion;

    /** @var int Integer between 1 and 256 */
    public int $weight = 16;

    public int $dependency = 0;

    public ?int $expectedLength = null;

    public Stream $stream;

    public ?DeferredFuture $windowSizeIncrease = null;

    private ?string $watcher;

    public function __construct(
        int $id,
        Request $request,
        Stream $stream,
        Cancellation $cancellationToken,
        Cancellation $originalCancellation,
        ?string $watcher,
        int $serverSize,
        int $clientSize
    ) {
        $this->id = $id;
        $this->request = $request;
        $this->stream = $stream;
        $this->cancellationToken = $cancellationToken;
        $this->originalCancellation = $originalCancellation;
        $this->watcher = $watcher;
        $this->serverWindow = $serverSize;
        $this->clientWindow = $clientSize;
        $this->pendingResponse = new DeferredFuture;
        $this->requestBodyCompletion = new DeferredFuture;
        $this->bufferSize = 0;
    }

    public function __destruct()
    {
        if ($this->watcher !== null) {
            EventLoop::cancel($this->watcher);
        }
    }

    public function disableInactivityWatcher(): void
    {
        if ($this->watcher === null) {
            return;
        }

        EventLoop::disable($this->watcher);
    }

    public function enableInactivityWatcher(): void
    {
        if ($this->watcher === null) {
            return;
        }

        EventLoop::disable($this->watcher);
        EventLoop::enable($this->watcher);
    }
}
