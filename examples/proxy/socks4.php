<?php

use Amp\CancellationToken;
use Amp\Dns\Record;
use Amp\Http\Client\Connection\ConnectionLimitingPool;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Promise;
use Amp\ReactAdapter\ReactAdapter;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\ResourceSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketException;
use Clue\React\Socks\Client;
use React\Socket\Connection;
use React\Socket\Connector as ReactConnector;
use function Amp\call;
use function Amp\Dns\resolve;

require __DIR__ . '/../.helper/functions.php';

Loop::run(static function () use ($argv) {
    try {
        $proxy = new class implements Connector {
            public function connect(
                string $uri,
                ?ConnectContext $context = null,
                ?CancellationToken $token = null
            ): Promise {
                $context = $context ?? (new ConnectContext);

                return call(static function () use ($uri, $context) {
                    $options = $context->toStreamContextArray();

                    $socketAddress = SocketAddress::fromSocketName(\str_replace(['tcp://', 'udp://'], '', $uri));

                    /** @var Record[] $records */
                    $records = yield resolve($socketAddress->getHost(), Record::A);

                    $connector = new Client(
                        'socks4://127.0.0.1:1234',
                        new ReactConnector(ReactAdapter::get(), [
                            'tcp' => $options['socket'],
                            'tls' => false,
                        ])
                    );

                    /** @var Connection $connection */
                    $connection = yield $connector->connect($records[0]->getValue() . ':' . $socketAddress->getPort());
                    /** @psalm-suppress InternalMethod */
                    $connection->pause();

                    if ($context->getTlsContext()) {
                        /** @psalm-suppress InternalProperty */
                        \stream_context_set_option($connection->stream, $options);
                    }

                    /** @psalm-suppress InternalProperty */
                    return ResourceSocket::fromClientSocket($connection->stream);
                });
            }
        };

        $pool = ConnectionLimitingPool::byAuthority(6, new DefaultConnectionFactory($proxy));

        // Instantiate the HTTP client
        $client = (new HttpClientBuilder)
            ->usingPool($pool)
            ->build();

        // Make an asynchronous HTTP request
        $promise = $client->request(new Request($argv[1] ?? 'https://api.myip.com/'));

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        /** @var Response $response */
        $response = yield $promise;

        dumpRequestTrace($response->getRequest());
        dumpResponseTrace($response);

        dumpResponseBodyPreview(yield $response->getBody()->buffer());
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The HttpClient::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
