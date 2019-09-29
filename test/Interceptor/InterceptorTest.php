<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Client;
use Amp\Http\Client\Connection\DefaultConnectionPool;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Request as ClientRequest;
use Amp\Http\Client\Response as ClientResponse;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket\Server as SocketServer;
use Amp\Socket\StaticConnector;
use Psr\Log\NullLogger;
use function Amp\call;
use function Amp\Socket\connector;

abstract class InterceptorTest extends AsyncTestCase
{
    private $client;
    private $serverSocket;
    private $server;

    /** @var Request */
    private $request;
    /** @var Response */
    private $response;

    final protected function givenNetworkInterceptor(NetworkInterceptor $interceptor): void
    {
        $this->client->addNetworkInterceptor($interceptor);
    }

    final protected function givenApplicationInterceptor(ApplicationInterceptor $interceptor): void
    {
        $this->client->addApplicationInterceptor($interceptor);
    }

    final protected function whenRequestIsExecuted(?ClientRequest $request = null): Promise
    {
        return call(function () use ($request) {
            yield $this->server->start();

            /** @var ClientResponse $response */
            $response = yield $this->client->request($request ?? new ClientRequest('http://example.org/'));

            $this->request = $response->getRequest();
            $this->response = $response;

            yield $this->response->getBody()->buffer();

            yield $this->server->stop();

            $this->serverSocket->close();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverSocket = SocketServer::listen('tcp://127.0.0.1:0');
        $this->server = new Server(
            [$this->serverSocket],
            new CallableRequestHandler(static function () {
                return new Response(Status::OK, ['content-type' => 'text-plain; charset=utf-8'], 'OK');
            }),
            new NullLogger
        );

        $staticConnector = new StaticConnector($this->serverSocket->getAddress()->toString(), connector());
        $this->client = new Client(new DefaultConnectionPool($staticConnector));
    }

    final protected function thenRequestHasHeader(string $field, string $value): void
    {
        $this->assertSame([$value], $this->request->getHeaderArray($field));
    }

    final protected function thenRequestDoesNotHaveHeader(string $field): void
    {
        $this->assertSame([], $this->request->getHeaderArray($field));
    }

    final protected function thenResponseHasHeader(string $field, string $value): void
    {
        $this->assertSame([$value], $this->response->getHeaderArray($field));
    }

    final protected function thenResponseDoesNotHaveHeader(string $field): void
    {
        $this->assertSame([], $this->response->getHeaderArray($field));
    }
}
