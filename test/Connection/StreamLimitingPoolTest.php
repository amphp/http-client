<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Future;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Support\LocalHttpBin;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Sync\LocalKeyedMutex;
use function Amp\async;

class StreamLimitingPoolTest extends AsyncTestCase
{
    public function testByHost(): void
    {
        $httpBin = LocalHttpBin::https();
        $connectionPool = new UnlimitedConnectionPool(LocalHttpBin::untrustedConnectionFactory());

        $client = (new HttpClientBuilder)
            ->usingPool(StreamLimitingPool::byHost($connectionPool, new LocalKeyedMutex))
            ->build();

        $this->setMinimumRuntime(2);

        Future\await([
            async(fn () => $client->request(new Request($httpBin->uri('/delay/1')))),
            async(fn () => $client->request(new Request($httpBin->uri('/delay/1')))),
        ]);

        $httpBin->stop();
    }
}
