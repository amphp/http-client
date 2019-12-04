<?php

namespace Amp\Http\Client\EventListener;

use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\Internal\HarAttributes;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Success;
use function Amp\getCurrentTime;

final class RecordHarAttributes implements EventListener
{
    public function startRequest(Request $request): Promise
    {
        if (!$request->hasAttribute(HarAttributes::STARTED_DATE_TIME)) {
            $request->setAttribute(HarAttributes::STARTED_DATE_TIME, new \DateTimeImmutable);
        }

        return $this->addTiming(HarAttributes::TIME_START, $request);
    }

    public function startDnsResolution(Request $request): Promise
    {
        return new Success; // not implemented
    }

    public function startConnectionCreation(Request $request): Promise
    {
        return $this->addTiming(HarAttributes::TIME_CONNECT, $request);
    }

    public function startTlsNegotiation(Request $request): Promise
    {
        return $this->addTiming(HarAttributes::TIME_SSL, $request);
    }

    public function startSendingRequest(Request $request, Stream $stream): Promise
    {
        $host = $stream->getRemoteAddress()->getHost();
        if (\strrpos($host, ':')) {
            $host = '[' . $host . ']';
        }

        $request->setAttribute(HarAttributes::SERVER_IP_ADDRESS, $host);

        return $this->addTiming(HarAttributes::TIME_SEND, $request);
    }

    public function completeSendingRequest(Request $request, Stream $stream): Promise
    {
        return $this->addTiming(HarAttributes::TIME_WAIT, $request);
    }

    public function startReceivingResponse(Request $request, Stream $stream): Promise
    {
        return $this->addTiming(HarAttributes::TIME_RECEIVE, $request);
    }

    public function completeReceivingResponse(Request $request, Stream $stream): Promise
    {
        return $this->addTiming(HarAttributes::TIME_COMPLETE, $request);
    }

    public function completeDnsResolution(Request $request): Promise
    {
        return new Success; // not implemented
    }

    public function completeConnectionCreation(Request $request): Promise
    {
        return new Success; // not implemented
    }

    public function completeTlsNegotiation(Request $request): Promise
    {
        return new Success; // not implemented
    }

    private function addTiming(string $key, Request $request): Promise
    {
        if (!$request->hasAttribute($key)) {
            $request->setAttribute($key, getCurrentTime());
        }

        return new Success;
    }

    public function abort(Request $request, \Throwable $cause): Promise
    {
        return new Success;
    }
}
