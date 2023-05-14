<?php declare(strict_types=1);

namespace Amp\Http\Client\EventListener;

use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\HarAttributes;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Socket\InternetAddress;
use Amp\Socket\TlsException;
use function Amp\now;

final class RecordHarAttributes implements EventListener
{
    public function requestStart(Request $request): void
    {
        if (!$request->hasAttribute(HarAttributes::STARTED_DATE_TIME)) {
            $request->setAttribute(HarAttributes::STARTED_DATE_TIME, new \DateTimeImmutable);
        }

        $this->addTiming(HarAttributes::TIME_START, $request);
    }

    public function connectStart(Request $request): void
    {
        $this->addTiming(HarAttributes::TIME_CONNECT, $request);
    }

    public function startTlsNegotiation(Request $request): void
    {
        $this->addTiming(HarAttributes::TIME_SSL, $request);
    }

    public function requestHeaderStart(Request $request, Stream $stream): void
    {
        $address = $stream->getRemoteAddress();
        $host = match (true) {
            $address instanceof InternetAddress => $address->getAddress(),
            default => $address->toString(),
        };
        if (\strrpos($host, ':')) {
            $host = '[' . $host . ']';
        }

        $request->setAttribute(HarAttributes::SERVER_IP_ADDRESS, $host);
        $this->addTiming(HarAttributes::TIME_SEND, $request);
    }

    public function requestBodyEnd(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_WAIT, $request);
    }

    public function responseHeaderStart(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_RECEIVE, $request);
    }

    public function requestEnd(Request $request, Response $response): void
    {
        $this->addTiming(HarAttributes::TIME_COMPLETE, $request);
    }

    /**
     * @param non-empty-string $key
     */
    private function addTiming(string $key, Request $request): void
    {
        if (!$request->hasAttribute($key)) {
            $request->setAttribute($key, now());
        }
    }

    public function requestFailed(Request $request, HttpException $exception): void
    {
        // nothing to do
    }

    public function connectFailed(Request $request, SocketException $exception): void
    {
        // nothing to do
    }

    public function connectEnd(Request $request, Connection $connection): void
    {
        // nothing to do
    }

    public function tlsHandshakeStart(Request $request, Connection $connection): void
    {
        // nothing to do
    }

    public function tlsHandshakeFailed(Request $request, Connection $connection, TlsException $exception): void
    {
        // nothing to do
    }

    public function tlsHandshakeEnd(Request $request, Connection $connection): void
    {
        // nothing to do
    }

    public function requestHeaderEnd(Request $request, Stream $stream): void
    {
        // nothing to do
    }

    public function requestBodyStart(Request $request, Stream $stream): void
    {
        // nothing to do
    }

    public function requestBodyProgress(Request $request, Stream $stream): void
    {
        // nothing to do
    }

    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function responseBodyStart(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }
}
