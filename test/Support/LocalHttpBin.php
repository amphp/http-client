<?php declare(strict_types=1);

namespace Amp\Http\Client\Support;

use Amp\Http\Client\Connection\ConnectionFactory;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Server\ClientException;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\DefaultHttpDriverFactory;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\BindContext;
use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\ServerTlsContext;
use Psr\Log\NullLogger;
use function Amp\delay;

final class LocalHttpBin implements RequestHandler
{
    private function __construct(
        private SocketHttpServer $server,
        private string $scheme,
    ) {
    }

    public static function http(): self
    {
        return self::start('http', new BindContext);
    }

    public static function https(): self
    {
        $certificate = new Certificate(__DIR__ . '/../tls/amphp.org.pem');
        $tlsContext = (new ServerTlsContext)->withDefaultCertificate($certificate);

        return self::start('https', (new BindContext)->withTlsContext($tlsContext));
    }

    public static function untrustedConnectionFactory(): ConnectionFactory
    {
        $tlsContext = (new ClientTlsContext(''))->withoutPeerVerification()->withoutPeerNameVerification();
        $connectContext = (new ConnectContext)->withTlsContext($tlsContext);

        return new DefaultConnectionFactory(connectContext: $connectContext);
    }

    public function uri(string $path = '/'): string
    {
        return $this->scheme . '://' . $this->authority() . $path;
    }

    public function authority(): string
    {
        return $this->server->getServers()[0]->getAddress()->toString();
    }

    public function stop(): void
    {
        $this->server->stop();
    }

    public function handleRequest(Request $request): Response
    {
        $path = \trim($request->getUri()->getPath(), '/');
        [$route, $argument] = \explode('/', $path, 2) + [1 => ''];

        return match ($route) {
            'user-agent' => $this->json(['user-agent' => $request->getHeader('user-agent')]),
            'post', 'put' => $this->echoed($request),
            'status' => new Response((int) $argument),
            'delay' => $this->delayed((int) $argument),
            'bytes' => new Response(body: \str_repeat('.', (int) $argument)),
            'gzip' => $this->gzip('{"gzipped":true}'),
            'deflate' => $this->deflate('{"deflated":true}'),
            'gzip-bomb' => $this->gzip(\str_repeat("\0", 11534336)), // 11 MiB
            'redirect' => new Response(302, ['location' => $this->uri('/' . $argument)]),
            default => new Response(200),
        };
    }

    private static function start(string $scheme, BindContext $bindContext): self
    {
        $logger = new NullLogger;
        $httpDriverFactory = new DefaultHttpDriverFactory($logger, allowHttp2Upgrade: true);
        $server = SocketHttpServer::createForDirectAccess(
            $logger,
            enableCompression: false,
            httpDriverFactory: $httpDriverFactory,
        );
        $server->expose('127.0.0.1:0', $bindContext);

        $httpBin = new self($server, $scheme);
        $server->start($httpBin, new DefaultErrorHandler);

        return $httpBin;
    }

    private function json(array $data): Response
    {
        $body = \json_encode($data, \JSON_THROW_ON_ERROR);

        return new Response(200, ['content-type' => 'application/json'], $body);
    }

    private function gzip(string $content): Response
    {
        $body = \gzencode($content);

        return new Response(200, ['content-encoding' => 'gzip'], $body);
    }

    private function deflate(string $content): Response
    {
        $body = \gzcompress($content);

        return new Response(200, ['content-encoding' => 'deflate'], $body);
    }

    private function echoed(Request $request): Response
    {
        try {
            return $this->json($this->echoRequest($request));
        } catch (ClientException) {
            return new Response(400);
        }
    }

    private function delayed(int $seconds): Response
    {
        delay($seconds, reference: false);

        return new Response(200);
    }

    private function echoRequest(Request $request): array
    {
        $body = $request->getBody()->buffer();
        $contentType = $request->getHeader('content-type') ?? '';

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = \implode(', ', $values);
        }

        $form = [];
        $files = [];
        if ($contentType === 'application/x-www-form-urlencoded') {
            \parse_str($body, $form);
        } elseif (\preg_match('(^multipart/form-data; boundary=(.+)$)', $contentType, $match) === 1) {
            [$form, $files] = $this->parseMultipart($body, $match[1]);
        }

        return [
            'data' => $body,
            'form' => $form,
            'files' => $files,
            'headers' => $headers,
        ];
    }

    /**
     * @return array{array<string, string>, array<string, string>}
     */
    private function parseMultipart(string $body, string $boundary): array
    {
        $form = [];
        $files = [];

        $parts = \explode('--' . $boundary, $body);
        foreach ($parts as $part) {
            if (!\str_contains($part, "\r\n\r\n")) {
                continue;
            }

            [$headers, $content] = \explode("\r\n\r\n", $part, 2);
            $content = \substr($content, 0, -2);

            \preg_match('( name="([^"]+)")', $headers, $name);
            if (\str_contains($headers, 'filename=')) {
                $files[$name[1]] = $content;
            } else {
                $form[$name[1]] = $content;
            }
        }
        return [$form, $files];
    }
}
