<?php

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request as ClientRequest;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class Http2IntegrationTest extends TestCase
{
    private HttpServer $httpServer;
    private HttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpServer = SocketHttpServer::createForDirectAccess(
            new NullLogger(),
            httpDriverFactory: new DefaultHttpDriverFactory(new NullLogger(), bodySizeLimit: 2147483647, allowHttp2Upgrade: true)
        );

        $this->httpServer->expose('127.0.0.1:0');
        $this->httpServer->start(new ClosureRequestHandler(function (Request $request): Response {
            return new Response(200, [], (string)\strlen($request->getBody()->buffer()));
        }), new DefaultErrorHandler());

        $this->httpClient = (new HttpClientBuilder())->build();
    }

    private function createRequest(string $method = 'GET'): ClientRequest
    {
        $address = $this->httpServer->getServers()[0]->getAddress()->toString();

        $request = new ClientRequest('http://' . $address, $method);
        $request->setProtocolVersions(['2']);

        return $request;
    }

    public function testHttp2Support(): void
    {
        $response = $this->httpClient->request($this->createRequest());
        $body = $response->getBody()->buffer();

        self::assertSame('0', $body);
        self::assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2SupportBody(): void
    {
        $request = $this->createRequest('POST');
        $request->setBody('foobar');

        $response = $this->httpClient->request($request);
        $body = $response->getBody()->buffer();

        self::assertSame('6', $body);
        self::assertSame('2', $response->getProtocolVersion());
    }

    public function testHttp2SupportLargeBody(): void
    {
        $request = $this->createRequest('POST');
        $request->setBody(\str_repeat(',', 256 * 1024));

        $response = $this->httpClient->request($request);
        $body = $response->getBody()->buffer();

        self::assertSame((string) (256 * 1024), $body);
        self::assertSame('2', $response->getProtocolVersion());
    }
}
