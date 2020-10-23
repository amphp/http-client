<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\PipelineSource;
use Amp\Promise;
use Amp\Struct;

/**
 * Used in Http2Connection.
 *
 * @internal
 */
final class Http2Stream
{
    use Struct;
    use ForbidSerialization;
    use ForbidCloning;

    public int $id;

    public Request $request;

    public ?Response $response = null;

    public ?Deferred $pendingResponse;

    public ?Promise $preResponseResolution = null;

    public bool $responsePending = true;

    public ?PipelineSource $body = null;

    public ?Deferred $trailers = null;

    public CancellationToken $originalCancellation;

    public CancellationToken $cancellationToken;

    /** @var int Bytes received on the stream. */
    public int $received = 0;

    public int $serverWindow;

    public int $clientWindow;

    public string $requestBodyBuffer = '';

    public bool $requestBodyComplete = false;

    public Deferred $requestBodyCompletion;

    /** @var int Integer between 1 and 256 */
    public int $weight = 16;

    public int $dependency = 0;

    public ?int $expectedLength = null;

    public Stream $stream;

    public ?Deferred $windowSizeIncrease = null;

    private ?string $watcher;

    public function __construct(
        int $id,
        Request $request,
        Stream $stream,
        CancellationToken $cancellationToken,
        CancellationToken $originalCancellation,
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
        $this->pendingResponse = new Deferred;
        $this->requestBodyCompletion = new Deferred;
    }

    public function __destruct()
    {
        if ($this->watcher !== null) {
            Loop::cancel($this->watcher);
        }
    }

    public function resetInactivityWatcher(): void
    {
        if ($this->watcher === null) {
            return;
        }

        Loop::disable($this->watcher);
        Loop::enable($this->watcher);
    }
}
