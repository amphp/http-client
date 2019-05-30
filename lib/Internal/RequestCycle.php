<?php

namespace Amp\Http\Client\Internal;

use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Socket\ResourceSocket;
use Amp\Struct;
use League\Uri;

class RequestCycle
{
    use Struct;

    /** @var string */
    public $protocolVersion;

    /** @var Request */
    public $request;

    /** @var Uri\Http */
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

    /** @var ResourceSocket */
    public $socket;

    /** @var CancellationToken */
    public $cancellation;

    /** @var int */
    public $retryCount = 0;

    /** @var bool */
    public $bodyTooLarge = false;
}
