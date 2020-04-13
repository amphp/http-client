<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
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

    /** @var int */
    public $id;

    /** @var Request */
    public $request;

    /** @var Response|null */
    public $response;

    /** @var Deferred|null */
    public $pendingResponse;

    /** @var Promise|null */
    public $preResponseResolution;

    /** @var bool */
    public $responsePending = true;

    /** @var Emitter|null */
    public $body;

    /** @var Deferred|null */
    public $trailers;

    /** @var CancellationToken */
    public $originalCancellation;

    /** @var CancellationToken */
    public $cancellationToken;

    /** @var int Bytes received on the stream. */
    public $received = 0;

    /** @var int */
    public $serverWindow;

    /** @var int */
    public $clientWindow;

    /** @var string */
    public $requestBodyBuffer = '';

    /** @var bool */
    public $requestBodyComplete = false;

    /** @var Deferred */
    public $requestBodyCompletion;

    /** @var int Integer between 1 and 256 */
    public $weight = 16;

    /** @var int */
    public $dependency = 0;

    /** @var int|null */
    public $expectedLength;

    /** @var Stream */
    public $stream;

    /** @var Deferred|null */
    public $windowSizeIncrease;

    /** @var string|null */
    private $watcher;

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
