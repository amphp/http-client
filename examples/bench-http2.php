<?php /** @noinspection PhpUnhandledExceptionInspection */

use Amp\Http\Client\Connection\Http2ConnectionException;
use Amp\Http\Client\Connection\Http2StreamException;
use Amp\Http\Client\Connection\Internal\Http2FrameProcessor;
use Amp\Http\Client\Connection\Internal\Http2Parser;
use function Amp\getCurrentTime;

require __DIR__ . '/.helper/functions.php';

$data = \file_get_contents(__DIR__ . '/../test/fixture/h2.log');

$processor = new class implements Http2FrameProcessor
{
    public function handlePong(string $data): void
    {
        // empty stub
    }

    public function handlePing(string $data): void
    {
        // empty stub
    }

    public function handleShutdown(int $lastId, int $error): void
    {
        // empty stub
    }

    public function handleStreamWindowIncrement(int $streamId, int $windowSize): void
    {
        // empty stub
    }

    public function handleConnectionWindowIncrement(int $windowSize): void
    {
        // empty stub
    }

    public function handleHeaders(int $streamId, array $pseudo, array $headers): void
    {
        // empty stub
    }

    public function handlePushPromise(int $streamId, int $pushId, array $pseudo, array $headers): void
    {
        // empty stub
    }

    public function handlePriority(int $streamId, int $parentId, int $weight): void
    {
        // empty stub
    }

    public function handleStreamReset(int $streamId, int $errorCode): void
    {
        // empty stub
    }

    public function handleStreamException(Http2StreamException $exception): void
    {
        // empty stub
    }

    public function handleConnectionException(Http2ConnectionException $exception): void
    {
        // empty stub
    }

    public function handleData(int $streamId, string $data): void
    {
        // empty stub
    }

    public function handleSettings(array $settings): void
    {
        // empty stub
    }

    public function handleStreamEnd(int $streamId): void
    {
        // empty stub
    }
};

$start = getCurrentTime();

for ($i = 0; $i < 10000; $i++) {
    $parser = (new Http2Parser($processor))->parse();
    $parser->send($data);
}

print 'Runtime: ' . (getCurrentTime() - $start) . ' milliseconds' . "\r\n";
