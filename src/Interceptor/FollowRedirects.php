<?php

namespace Amp\Http\Client\Interceptor;

use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use League\Uri;
use Psr\Http\Message\UriInterface as PsrUri;
use function Amp\call;

final class FollowRedirects implements ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * Resolves the given path in $locationUri using $baseUri as a base URI. For example, a base URI of
     * http://example.com/example/path and a location path of 'to/resolve' will return a URI of
     * http://example.com/example/to/resolve.
     *
     * @param PsrUri $baseUri
     * @param PsrUri $locationUri
     *
     * @return PsrUri
     */
    public static function resolve(PsrUri $baseUri, PsrUri $locationUri): PsrUri
    {
        if ((string) $locationUri === '') {
            return $baseUri;
        }

        if ($locationUri->getScheme() !== '' || $locationUri->getHost() !== '') {
            $resultUri = $locationUri->withPath(self::removeDotSegments($locationUri->getPath()));

            if ($locationUri->getScheme() === '') {
                $resultUri = $resultUri->withScheme($baseUri->getScheme());
            }

            return $resultUri;
        }

        $baseUri = $baseUri->withQuery($locationUri->getQuery());
        $baseUri = $baseUri->withFragment($locationUri->getFragment());

        if ($locationUri->getPath() !== '' && \substr($locationUri->getPath(), 0, 1) === "/") {
            $baseUri = $baseUri->withPath(self::removeDotSegments($locationUri->getPath()));
        } else {
            $baseUri = $baseUri->withPath(self::mergePaths($baseUri->getPath(), $locationUri->getPath()));
        }

        return $baseUri;
    }

    /**
     * @param string $input
     *
     * @return string
     *
     * @link http://www.apps.ietf.org/rfc/rfc3986.html#sec-5.2.4
     */
    private static function removeDotSegments(string $input): string
    {
        $output = '';
        $patternA = ',^(\.\.?/),';
        $patternB1 = ',^(/\./),';
        $patternB2 = ',^(/\.)$,';
        $patternC = ',^(/\.\./|/\.\.),';
        // $patternD  = ',^(\.\.?)$,';
        $patternE = ',(/*[^/]*),';

        while ($input !== '') {
            if (\preg_match($patternA, $input)) {
                $input = \preg_replace($patternA, '', $input);
            } elseif (\preg_match($patternB1, $input, $match) || \preg_match($patternB2, $input, $match)) {
                $input = \preg_replace(",^" . $match[1] . ",", '/', $input);
            } elseif (\preg_match($patternC, $input, $match)) {
                $input = \preg_replace(',^' . \preg_quote($match[1], ',') . ',', '/', $input);
                $output = \preg_replace(',/([^/]+)$,', '', $output);
            } elseif ($input === '.' || $input === '..') { // pattern D
                $input = '';
            } elseif (\preg_match($patternE, $input, $match)) {
                $initialSegment = $match[1];
                $input = \preg_replace(',^' . \preg_quote($initialSegment, ',') . ',', '', $input, 1);
                $output .= $initialSegment;
            }
        }

        return $output;
    }

    /**
     * @param string $basePath
     * @param string $pathToMerge
     *
     * @return string
     *
     * @link http://tools.ietf.org/html/rfc3986#section-5.2.3
     */
    private static function mergePaths(string $basePath, string $pathToMerge): string
    {
        if ($pathToMerge === '') {
            return self::removeDotSegments($basePath);
        }

        if ($basePath === '') {
            return self::removeDotSegments('/' . $pathToMerge);
        }

        $parts = \explode('/', $basePath);
        \array_pop($parts);
        $parts[] = $pathToMerge;

        return self::removeDotSegments(\implode('/', $parts));
    }

    /** @var int */
    private $maxRedirects;
    /** @var bool */
    private $autoReferrer;

    public function __construct(int $limit, bool $autoReferrer = true)
    {
        if ($limit < 1) {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \Error("Invalid redirection limit: " . $limit);
        }

        $this->maxRedirects = $limit;
        $this->autoReferrer = $autoReferrer;
    }

    public function request(
        Request $request,
        CancellationToken $cancellation,
        DelegateHttpClient $httpClient
    ): Promise {
        // Don't follow redirects on pushes, just store the redirect in cache (if an interceptor is configured)

        return call(function () use ($request, $cancellation, $httpClient) {
            /** @var Response $response */
            $response = yield $httpClient->request(clone $request, $cancellation);
            $response = yield from $this->followRedirects($request, $response, $httpClient, $cancellation);

            return $response;
        });
    }

    private function followRedirects(
        Request $request,
        Response $response,
        DelegateHttpClient $client,
        CancellationToken $cancellationToken
    ): \Generator {
        $previousResponse = null;

        $maxRedirects = $this->maxRedirects;
        $requestNr = 2;

        do {
            $request = yield from $this->createRedirectRequest($request, $response);
            if ($request === null) {
                return $response;
            }

            /** @var Response $redirectResponse */
            $redirectResponse = yield $client->request(clone $request, $cancellationToken);
            $redirectResponse->setPreviousResponse($response);

            $response = $redirectResponse;
        } while (++$requestNr <= $maxRedirects + 1);

        if ($this->getRedirectUri($response) !== null) {
            throw new TooManyRedirectsException($response);
        }

        return $response;
    }

    private function createRedirectRequest(Request $originalRequest, Response $response): \Generator
    {
        $redirectUri = $this->getRedirectUri($response);
        if ($redirectUri === null) {
            return null;
        }

        $originalUri = $response->getRequest()->getUri();
        $isSameHost = $redirectUri->getAuthority() === $originalUri->getAuthority();

        $request = clone $originalRequest;
        $request->setMethod('GET');
        $request->setUri($redirectUri);
        $request->removeHeader('transfer-encoding');
        $request->removeHeader('content-length');
        $request->removeHeader('content-type');
        $request->removeAttributes();
        $request->setBody(null);

        if (!$isSameHost) {
            // Remove for security reasons, any interceptor headers will be added again,
            // but application headers will be discarded.
            foreach ($request->getRawHeaders() as [$field]) {
                $request->removeHeader($field);
            }
        }

        if ($this->autoReferrer) {
            $this->assignRedirectRefererHeader($request, $originalUri, $redirectUri);
        }

        yield from $this->discardResponseBody($response);

        return $request;
    }

    /**
     * Clients must not add a Referer header when leaving an unencrypted resource and redirecting to an encrypted
     * resource.
     *
     * @param Request $request
     * @param PsrUri  $referrerUri
     * @param PsrUri  $followUri
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec15.html#sec15.1.3
     */
    private function assignRedirectRefererHeader(
        Request $request,
        PsrUri $referrerUri,
        PsrUri $followUri
    ): void {
        $referrerIsEncrypted = $referrerUri->getScheme() === 'https';
        $destinationIsEncrypted = $followUri->getScheme() === 'https';

        if (!$referrerIsEncrypted || $destinationIsEncrypted) {
            $request->setHeader('Referer', (string) $referrerUri->withUserInfo('')->withFragment(''));
        } else {
            $request->removeHeader('Referer');
        }
    }

    private function getRedirectUri(Response $response): ?PsrUri
    {
        if (\count($response->getHeaderArray('location')) !== 1) {
            return null;
        }

        $status = $response->getStatus();
        $request = $response->getRequest();
        $method = $request->getMethod();

        if ($method !== 'GET' && \in_array($status, [307, 308], true)) {
            return null;
        }

        // We don't automatically follow:
        // - 300 (Multiple Choices)
        // - 304 (Not Modified)
        // - 305 (Use Proxy)
        if (!\in_array($status, [301, 302, 303, 307, 308], true)) {
            return null;
        }

        try {
            $locationUri = Uri\Http::createFromString($response->getHeader('location'));
        } catch (\Exception $e) {
            return null;
        }

        return self::resolve($request->getUri(), $locationUri);
    }

    private function discardResponseBody(Response $response): \Generator
    {
        // Discard response body of redirect responses
        $body = $response->getBody();

        try {
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            /** @noinspection LoopWhichDoesNotLoopInspection */
            /** @noinspection MissingOrEmptyGroupStatementInspection */
            while (null !== yield $body->read()) {
                // discard
            }
        } catch (HttpException | StreamException $e) {
            // ignore streaming errors on previous responses
        } finally {
            unset($body);
        }
    }
}
