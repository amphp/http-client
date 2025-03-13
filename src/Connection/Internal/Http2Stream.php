<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection\Internal;

use Amp\Cancellation;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Pipeline\Queue;
use Revolt\EventLoop;

/**
 * Used in Http2ConnectionProcessor.
 *
 * @internal
 */
final class Http2Stream
{
    use ForbidSerialization;
    use ForbidCloning;

    public ?Response $response = null;

    public ?DeferredFuture $pendingResponse;

    public ?Future $preResponseResolution = null;

    public bool $responsePending = true;

    public ?Queue $body;

    public bool $bodyStarted = false;

    public ?DeferredFuture $trailers;

    /** @var int Bytes received on the stream. */
    public int $received = 0;

    public int $bufferSize = 0;

    public bool $ended = false;

    public string $requestBodyBuffer = '';

    public readonly DeferredFuture $requestHeaderCompletion;

    public readonly DeferredFuture $requestBodyCompletion;

    /** @var int Integer between 1 and 256 */
    public int $weight = 16;

    public int $dependency = 0;

    public ?int $expectedLength = null;

    public ?DeferredFuture $windowSizeIncrease = null;

    private readonly DeferredCancellation $deferredCancellation;

    public readonly Cancellation $cancellation;

    public function __construct(
        public readonly int $id,
        public readonly Request $request,
        public readonly Stream $stream,
        Cancellation $cancellation,
        public readonly ?string $transferWatcher,
        public readonly ?string $inactivityWatcher,
        public int $serverWindow,
        public int $clientWindow,
    ) {
        $this->pendingResponse = new DeferredFuture();
        $this->requestHeaderCompletion = new DeferredFuture();
        $this->requestBodyCompletion = new DeferredFuture();
        $this->body = new Queue();

        $this->deferredCancellation = new DeferredCancellation();
        $this->cancellation = new CompositeCancellation($cancellation, $this->deferredCancellation->getCancellation());

        // Trailers future may never be exposed to the user if the request fails, so ignore.
        $this->trailers = new DeferredFuture();
        $this->trailers->getFuture()->ignore();
    }

    public function cancel(): void
    {
        $this->deferredCancellation->cancel();
    }

    public function __destruct()
    {
        if ($this->transferWatcher !== null) {
            EventLoop::cancel($this->transferWatcher);
        }

        if ($this->inactivityWatcher !== null) {
            EventLoop::cancel($this->inactivityWatcher);
        }

        $this->deferredCancellation->cancel();

        // Setting these to null due to PHP's random destruct order on shutdown to avoid errors from double completion.
        $this->pendingResponse = null;
        $this->body = null;
        $this->trailers = null;
    }

    public function disableInactivityWatcher(): void
    {
        if ($this->inactivityWatcher === null) {
            return;
        }

        EventLoop::disable($this->inactivityWatcher);
    }

    public function enableInactivityWatcher(): void
    {
        if ($this->inactivityWatcher === null) {
            return;
        }

        $watcher = $this->inactivityWatcher;

        EventLoop::disable($watcher);
        EventLoop::enable($watcher);
    }
}
