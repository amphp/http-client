<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\Http\Client\Request;
use Amp\Struct;

/**
 * Used in Http2Connection.
 *
 * @internal
 */
final class Http2Stream
{
    use Struct;

    public const OPEN = 0;
    public const RESERVED = 0b0001;
    public const REMOTE_CLOSED = 0b0010;
    public const LOCAL_CLOSED = 0b0100;
    public const CLOSED = 0b0110;

    /** @var Request|null */
    public $request;

    /** @var self|null */
    public $parent;

    /** @var string|null Packed header string. */
    public $headers;

    /** @var int Max header length. */
    public $maxHeaderSize;

    /** @var int Max body length. */
    public $maxBodySize;

    /** @var int Bytes received on the stream. */
    public $received = 0;

    /** @var int */
    public $serverWindow;

    /** @var int */
    public $clientWindow;

    /** @var string */
    public $buffer = "";

    /** @var int */
    public $state;

    /** @var \Amp\Deferred|null */
    public $deferred;

    /** @var int Integer between 1 and 256 */
    public $weight = 0;

    /** @var int */
    public $dependency = 0;

    /** @var int|null */
    public $expectedLength;

    public function __construct(int $serverSize, int $clientSize, int $maxHeaderSize, int $maxBodySize, int $state = self::OPEN)
    {
        $this->serverWindow = $serverSize;
        $this->maxHeaderSize = $maxHeaderSize;
        $this->maxBodySize = $maxBodySize;
        $this->clientWindow = $clientSize;
        $this->state = $state;
    }
}
