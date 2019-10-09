<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Client;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use League\Uri;
use League\Uri\UriException;
use Psr\Http\Message\UriInterface as PsrUri;
use function Amp\call;

final class FollowRedirects implements ApplicationInterceptor
{
    private $maxRedirects;
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
        Client $client
    ): Promise {
        if ($onPush = $request->getPushCallable()) {
            $request->onPush(function (Request $request, Promise $promise, CancellationTokenSource $source) use (
                $onPush, $cancellation, $client
            ) {
                $promise = call(function () use ($request, $promise, $cancellation, $client) {
                    $previousResponse = null;

                    $maxRedirects = $this->maxRedirects;
                    $requestNr = 1;

                    do {
                        /** @var Response $response */
                        $response = yield $promise;

                        if ($previousResponse !== null) {
                            $response->setPreviousResponse($previousResponse);
                        }

                        $previousResponse = $response;

                        $request = yield from $this->createRedirectRequest($request, $response);

                        if ($request === null) {
                            break;
                        }

                        $promise = $client->request($request, $cancellation);
                    } while (++$requestNr <= $maxRedirects + 1);

                    if ($maxRedirects !== 0 && $redirectUri = $this->getRedirectUri($response)) {
                        throw new TooManyRedirectsException($response);
                    }

                    return $response;
                });

                return $onPush($request, $promise, $source);
            });
        }

        return call(function () use ($request, $cancellation, $client) {
            $previousResponse = null;

            $maxRedirects = $this->maxRedirects;
            $requestNr = 1;

            do {
                /** @var Response $response */
                $response = yield $client->request($request, $cancellation);

                if ($previousResponse !== null) {
                    $response->setPreviousResponse($previousResponse);
                }

                $previousResponse = $response;

                $request = yield from $this->createRedirectRequest($request, $response);
            } while ($request !== null && ++$requestNr <= $maxRedirects + 1);

            if ($maxRedirects !== 0 && $redirectUri = $this->getRedirectUri($response)) {
                throw new TooManyRedirectsException($response);
            }

            return $response;
        });
    }

    private function createRedirectRequest(Request $request, Response $response): \Generator
    {
        if ($redirectUri = $this->getRedirectUri($response)) {
            $originalUri = $request->getUri();

            // Remove user info from original URI for host comparison.
            $userInfo = $originalUri->getUserInfo();
            $originalUri = $originalUri->withUserInfo('');

            // Discard response body of redirect responses
            $body = $response->getBody();

            /** @noinspection PhpStatementHasEmptyBodyInspection */
            /** @noinspection LoopWhichDoesNotLoopInspection */
            /** @noinspection MissingOrEmptyGroupStatementInspection */
            while (null !== yield $body->read()) {
                // discard
            }

            /**
             * If this is a 302/303 we need to follow the location with a GET if the original request wasn't
             * GET. Otherwise we need to send the body again.
             *
             * We won't resend the body nor any headers on redirects to other hosts for security reasons.
             *
             * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.3
             */
            $method = $request->getMethod();
            $status = $response->getStatus();
            $isSameHost = $redirectUri->getAuthority() === $originalUri->getAuthority();

            if ($isSameHost) {
                if ($userInfo !== '') {
                    [$username, $password] = \explode(':', $userInfo) + ['', null];
                    $redirectUri = $redirectUri->withUserInfo($username, $password);
                }

                $request = clone $request;
                $request->setUri($redirectUri);

                if ($status >= 300 && $status <= 303 && $method !== 'GET') {
                    $request->setMethod('GET');
                    $request->removeHeader('transfer-encoding');
                    $request->removeHeader('content-length');
                    $request->removeHeader('content-type');
                    $request->setBody(null);
                }
            } else {
                // We ALWAYS follow with a GET and without any set headers or body for redirects to other hosts.
                $request = new Request($redirectUri);
            }

            if ($this->autoReferrer) {
                $this->assignRedirectRefererHeader($request, $originalUri, $redirectUri);
            }

            return $request;
        }

        return null;
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
            $request->setHeader('Referer', $referrerUri);
        }

        $request->removeHeader('Referer');
    }

    private function getRedirectUri(Response $response): ?PsrUri
    {
        if (!$response->hasHeader('Location')) {
            return null;
        }

        $request = $response->getRequest();
        $method = $request->getMethod();

        $status = $response->getStatus();

        if ($status < 300 || $status > 399 || $method === 'HEAD') {
            return null;
        }

        try {
            $locationUri = Uri\Http::createFromString($response->getHeader('location'));
            return self::resolve($request->getUri(), $locationUri);
        } catch (UriException $e) {
            return null;
        }
    }

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
            return $locationUri->withPath(self::removeDotSegments($locationUri->getPath()));
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
}
