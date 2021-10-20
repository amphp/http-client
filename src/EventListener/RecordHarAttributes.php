<?php

namespace Amp\Http\Client\EventListener;

use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\Internal\HarAttributes;
use Amp\Http\Client\Request;
use function Revolt\EventLoop\now;

final class RecordHarAttributes implements EventListener
{
    public function startRequest(Request $request): void
    {
        if (!$request->hasAttribute(HarAttributes::STARTED_DATE_TIME)) {
            $request->setAttribute(HarAttributes::STARTED_DATE_TIME, new \DateTimeImmutable);
        }

        $this->addTiming(HarAttributes::TIME_START, $request);
    }

    public function startDnsResolution(Request $request): void
    {
        // not implemented
    }

    public function startConnectionCreation(Request $request): void
    {
        $this->addTiming(HarAttributes::TIME_CONNECT, $request);
    }

    public function startTlsNegotiation(Request $request): void
    {
        $this->addTiming(HarAttributes::TIME_SSL, $request);
    }

    public function startSendingRequest(Request $request, Stream $stream): void
    {
        $host = $stream->getRemoteAddress()->getHost();
        if (\strrpos($host, ':')) {
            $host = '[' . $host . ']';
        }

        $request->setAttribute(HarAttributes::SERVER_IP_ADDRESS, $host);

        $this->addTiming(HarAttributes::TIME_SEND, $request);
    }

    public function completeSendingRequest(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_WAIT, $request);
    }

    public function startReceivingResponse(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_RECEIVE, $request);
    }

    public function completeReceivingResponse(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_COMPLETE, $request);
    }

    public function completeDnsResolution(Request $request): void
    {
        // not implemented
    }

    public function completeConnectionCreation(Request $request): void
    {
        // not implemented
    }

    public function completeTlsNegotiation(Request $request): void
    {
        // not implemented
    }

    private function addTiming(string $key, Request $request): void
    {
        if (!$request->hasAttribute($key)) {
            $request->setAttribute($key, now());
        }
    }

    public function abort(Request $request, \Throwable $cause): void
    {
        // nothing to do
    }
}
