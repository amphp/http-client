<?php

namespace Amp\Test\Artax\Cookie;

use Amp\Http\Client\ClientBuilder;
use Amp\Http\Client\Cookie\ArrayCookieJar;
use Amp\Http\Client\Cookie\Cookie;
use Amp\Http\Client\Cookie\CookieJar;
use Amp\Http\Client\CookieHandler;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketClient;
use Amp\Http\Server\Options;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response as ServerResponse;
use Amp\Http\Server\Server;
use Amp\Http\Status;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\StaticConnector;
use Amp\Socket\UnlimitedSocketPool;
use Psr\Log\NullLogger;
use function Amp\Promise\wait;
use function Amp\Socket\connector;

class ClientCookieTest extends AsyncTestCase
{
    /** @var SocketClient */
    private $client;

    /** @var CookieJar */
    private $jar;

    /** @var Server */
    private $server;

    /** @var string */
    private $address;

    /** @var string */
    private $cookieHeader;

    public function setUp(): void
    {
        parent::setUp();

        $this->jar = new ArrayCookieJar;

        $socket = \Amp\Socket\listen('127.0.0.1:0');
        $socket->unreference();
        $this->address = $socket->getAddress();
        $this->server = new Server([$socket], new CallableRequestHandler(function () {
            return new ServerResponse(Status::OK, ['set-cookie' => $this->cookieHeader], '');
        }), new NullLogger, (new Options)->withConnectionTimeout(1));

        wait($this->server->start());

        $this->client = (new ClientBuilder(new UnlimitedSocketPool(1000, new StaticConnector($this->address, connector()))))->addNetworkInterceptor(new CookieHandler($this->jar))->build();
    }

    /** @dataProvider provideCookieDomainMatchData */
    public function testCookieAccepting(Cookie $cookie, string $requestDomain, bool $accept): \Generator
    {
        $this->cookieHeader = (string) $cookie;

        $response = yield $this->client->request(new Request('http://' . $requestDomain . '/'));

        if ($accept) {
            $this->assertCount(1, $this->jar->getAll());
        } else {
            $this->assertSame([], $this->jar->getAll());
        }

        Loop::stop();
    }

    public function provideCookieDomainMatchData(): array
    {
        return [
            [new Cookie("foo", "bar", null, "/", ".foo.bar.example.com"), "foo.bar", false],
            [new Cookie("foo", "bar", null, "/", ".example.com"), "example.com", true],
            [new Cookie("foo", "bar", null, "/", ".example.com"), "www.example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "www.example.com", true],
            [new Cookie("foo", "bar", null, "/", "example.com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", "anotherexample.com"), "example.com", false],
            [new Cookie("foo", "bar", null, "/", "com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", ".com"), "anotherexample.com", false],
            [new Cookie("foo", "bar", null, "/", ""), "example.com", true],
        ];
    }
}
