<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request as ClientRequest;
use Amp\Http\Client\Response as ClientResponse;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketServer;
use Amp\Socket\StaticSocketConnector;
use Psr\Log\NullLogger;
use function Amp\Socket\listen;
use function Amp\Socket\socketConnector;

abstract class InterceptorTest extends AsyncTestCase
{
    private HttpClientBuilder $builder;

    private HttpClient $client;

    private SocketServer $serverSocket;

    private Server $server;

    private ClientRequest $request;

    private ClientResponse $response;

    final public function getServerAddress(): SocketAddress
    {
        return $this->serverSocket->getAddress();
    }

    final protected function givenApplicationInterceptor(ApplicationInterceptor $interceptor): void
    {
        $this->builder = $this->builder->intercept($interceptor);
        $this->client = $this->builder->build();
    }

    final protected function givenNetworkInterceptor(NetworkInterceptor $interceptor): void
    {
        $this->builder = $this->builder->interceptNetwork($interceptor);
        $this->client = $this->builder->build();
    }

    final protected function whenRequestIsExecuted(?ClientRequest $request = null): void
    {
        $this->server->start();

        try {
            $response = $this->client->request($request ?? new ClientRequest('http://example.org/'));

            $this->request = $response->getRequest();
            $this->response = $response;

            $this->response->getBody()->buffer();
            $this->response->getTrailers()->await();
        } finally {
            $this->server->stop();
            $this->serverSocket->close();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverSocket = listen('tcp://127.0.0.1:0');
        $this->server = new Server(
            [$this->serverSocket],
            new ClosureRequestHandler(static function () {
                return new Response(Status::OK, ['content-type' => 'text-plain; charset=utf-8'], 'OK');
            }),
            new NullLogger
        );

        $staticConnector = new StaticSocketConnector($this->serverSocket->getAddress()->toString(), socketConnector());
        $this->builder = (new HttpClientBuilder)->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory($staticConnector)));
        $this->client = $this->builder->build();
    }

    final protected function thenRequestHasHeader(string $field, string ...$values): void
    {
        $this->assertSame($values, $this->request->getHeaderArray($field));
    }

    final protected function thenRequestDoesNotHaveHeader(string $field): void
    {
        $this->assertSame([], $this->request->getHeaderArray($field));
    }

    final protected function thenResponseHasHeader(string $field, string ...$values): void
    {
        $this->assertSame($values, $this->response->getHeaderArray($field));
    }

    final protected function thenResponseDoesNotHaveHeader(string $field): void
    {
        $this->assertSame([], $this->response->getHeaderArray($field));
    }
}
