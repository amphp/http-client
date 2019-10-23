<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use League\Uri\Http;
use Psr\Http\Message\UriInterface;

final class IfOrigin extends ConditionalInterceptor
{
    private $origin;

    /**
     * @param string                                    $origin
     * @param ApplicationInterceptor|NetworkInterceptor $interceptor
     *
     * @throws HttpException
     * @throws \TypeError
     */
    public function __construct(string $origin, $interceptor)
    {
        parent::__construct($interceptor);

        try {
            $originUri = Http::createFromString($origin);
        } catch (\Exception $e) {
            throw new HttpException("Invalid origin provided: parsing failed: " . $origin);
        }

        if (!\in_array($originUri->getScheme(), ['http', 'https'], true)) {
            throw new HttpException('Invalid origin with unsupported scheme: ' . $origin);
        }

        if ($originUri->getHost() === '') {
            throw new HttpException('Invalid origin without host: ' . $origin);
        }

        if ($originUri->getUserInfo() !== '') {
            throw new HttpException('Invalid origin with user info, which must not be present: ' . $origin);
        }

        if (!\in_array($originUri->getPath(), ['', '/'], true)) {
            throw new HttpException('Invalid origin with path, which must not be present: ' . $origin);
        }

        if ($originUri->getQuery() !== '') {
            throw new HttpException('Invalid origin with query, which must not be present: ' . $origin);
        }

        if ($originUri->getFragment() !== '') {
            throw new HttpException('Invalid origin with fragment, which must not be present: ' . $origin);
        }

        $this->origin = $this->normalizeOrigin($originUri);
    }

    protected function matches(Request $request): bool
    {
        return $this->origin === $this->normalizeOrigin($request->getUri());
    }

    private function normalizeOrigin(UriInterface $uri): string
    {
        $defaultPort = $uri->getScheme() === 'https' ? 443 : 80;

        return $uri->getScheme() . '://' . $uri->getHost() . ':' . ($uri->getPort() ?? $defaultPort);
    }
}
