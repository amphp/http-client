<?php

use Amp\CancellationToken;
use Amp\Dns\Record;
use Amp\Future;
use Amp\Http\Client\Connection\ConnectionLimitingPool;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\ReactAdapter\ReactAdapter;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\ResourceSocket;
use Amp\Socket\SocketAddress;
use Clue\React\Socks\Client;
use React\Socket\Connector as ReactConnector;
use function Amp\Dns\resolve;

require __DIR__ . '/../.helper/functions.php';

try {
    $proxy = new class implements Connector {
        public function connect(
            string $uri,
            ?ConnectContext $context = null,
            ?CancellationToken $token = null
        ): EncryptableSocket {
            $context = $context ?? (new ConnectContext);

            $options = $context->toStreamContextArray();

            $socketAddress = SocketAddress::fromSocketName(\str_replace(['tcp://', 'udp://'], '', $uri));

            /** @var Record[] $records */
            $records = resolve($socketAddress->getHost(), Record::A);

            $connector = new Client(
                'socks4://127.0.0.1:1234',
                new ReactConnector(ReactAdapter::get(), [
                    'tcp' => $options['socket'],
                    'tls' => false,
                ])
            );

            $connection = Future\adapt(
                $connector->connect($records[0]->getValue() . ':' . $socketAddress->getPort())
            )->await();
            /** @psalm-suppress InternalMethod */
            $connection->pause();

            if ($context->getTlsContext()) {
                /** @psalm-suppress InternalProperty */
                \stream_context_set_option($connection->stream, $options);
            }

            /** @psalm-suppress InternalProperty */
            return ResourceSocket::fromClientSocket($connection->stream);
        }
    };

    $pool = ConnectionLimitingPool::byAuthority(6, new DefaultConnectionFactory($proxy));

    // Instantiate the HTTP client
    $client = (new HttpClientBuilder)
        ->usingPool($pool)
        ->build();

    // Make an asynchronous HTTP request
    $response = $client->request(new Request($argv[1] ?? 'https://api.myip.com/'));

    dumpRequestTrace($response->getRequest());
    dumpResponseTrace($response);

    dumpResponseBodyPreview($response->getBody()->buffer());
} catch (HttpException $error) {
    // If something goes wrong Amp will throw the exception where the promise was yielded.
    // The HttpClient::request() method itself will never throw directly, but returns a promise.
    echo $error;
}
