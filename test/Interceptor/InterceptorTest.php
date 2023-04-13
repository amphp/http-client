<?php declare(strict_types=1);
/** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request as ClientRequest;
use Amp\Http\Client\Response as ClientResponse;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\ServerSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\StaticSocketConnector;
use Psr\Log\NullLogger;
use function Amp\Socket\socketConnector;

abstract class InterceptorTest extends AsyncTestCase
{
    private HttpClientBuilder $builder;

    private HttpClient $client;

    private ServerSocket $serverSocket;

    private HttpServer $server;

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

        $this->server = new SocketHttpServer(new NullLogger, new ResourceServerSocketFactory(), new SocketClientFactory(new NullLogger()));
        $this->server->expose(new InternetAddress('127.0.0.1', 0));

        $this->server->start(new ClosureRequestHandler(static function () {
            return new Response(HttpStatus::OK, ['content-type' => 'text-plain; charset=utf-8'], 'OK');
        }), new DefaultErrorHandler());

        $this->serverSocket = $this->server->getServers()[0] ?? self::fail('HTTP server did not create any server sockets');

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
