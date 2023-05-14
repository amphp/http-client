<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Stream extends DelegateHttpClient
{
    /**
     * Executes the request.
     *
     * This method may only be invoked once per instance.
     *
     * The implementation must ensure that events are called on {@see events()} and may use {@see request()} for that.
     *
     * @throws \Error Thrown if this method is called more than once.
     */
    public function request(Request $request, Cancellation $cancellation): Response;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
