<?php /** @noinspection PhpUnhandledExceptionInspection */

use Amp\Http\Http2\Http2Parser;
use Amp\Http\Http2\Http2Processor;
use function Amp\now;

require __DIR__ . '/../vendor/autoload.php';

$data = file_get_contents(__DIR__ . '/fixture/h2.log');

$processor = new class implements Http2Processor {
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

    public function handleHeaders(int $streamId, array $pseudo, array $headers, bool $streamEnded): void
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

    public function handleStreamException(\Amp\Http\Http2\Http2StreamException $exception): void
    {
        // empty stub
    }

    public function handleConnectionException(\Amp\Http\Http2\Http2ConnectionException $exception): void
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

$start = now();

for ($i = 0; $i < 10000; $i++) {
    $parser = (new Http2Parser($processor))->parse();
    $parser->send($data);
}

print 'Runtime: ' . (now() - $start) . ' seconds' . "\r\n";
