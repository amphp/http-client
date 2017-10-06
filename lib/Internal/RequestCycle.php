<?php

namespace Amp\Artax\Internal;

use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Socket\ClientSocket;
use Amp\Struct;
use Amp\Uri\Uri;

class RequestCycle {
    use Struct;

    /** @var string */
    public $protocolVersion;

    /** @var Request */
    public $request;

    /** @var Uri */
    public $uri;

    /** @var array */
    public $options;

    /** @var Response|null */
    public $previousResponse;

    /** @var Deferred */
    public $deferred;

    /** @var Deferred */
    public $bodyDeferred;

    /** @var Emitter */
    public $body;

    /** @var ClientSocket */
    public $socket;

    /** @var CancellationToken */
    public $cancellation;

    /** @var int */
    public $retryCount = 0;

    /** @var bool */
    public $bodyTooLarge = false;
}
