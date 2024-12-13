<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use League\Uri\Http;
use Psr\Http\Message\UriInterface;

final class MatchOrigin implements ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var ApplicationInterceptor[] */
    private readonly array $originMap;

    /**
     * @param ApplicationInterceptor[] $originMap
     *
     * @throws HttpException
     */
    public function __construct(array $originMap, private readonly ?ApplicationInterceptor $default = null)
    {
        $validatedMap = [];
        foreach ($originMap as $origin => $interceptor) {
            if (!$interceptor instanceof ApplicationInterceptor) {
                $type = \get_debug_type($interceptor);
                throw new HttpException('Origin map must be a map from origin to ApplicationInterceptor, got ' . $type);
            }

            $validatedMap[$this->checkOrigin($origin)] = $interceptor;
        }

        $this->originMap = $validatedMap;
    }

    public function request(
        Request $request,
        Cancellation $cancellation,
        DelegateHttpClient $httpClient
    ): Response {
        $interceptor = $this->originMap[$this->normalizeOrigin($request->getUri())] ?? $this->default;

        if (!$interceptor) {
            return $httpClient->request($request, $cancellation);
        }

        return $interceptor->request($request, $cancellation, $httpClient);
    }

    /**
     * @throws HttpException
     */
    private function checkOrigin(string $origin): string
    {
        try {
            $originUri = Http::new($origin);
        } catch (\Exception) {
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

        return $this->normalizeOrigin($originUri);
    }

    private function normalizeOrigin(UriInterface $uri): string
    {
        $defaultPort = $uri->getScheme() === 'https' ? 443 : 80;

        return $uri->getScheme() . '://' . $uri->getHost() . ':' . ($uri->getPort() ?? $defaultPort);
    }
}
