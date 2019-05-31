<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Dns\InvalidNameException;
use Amp\Http\Client\Cookie\Cookie;
use Amp\Http\Client\Cookie\CookieFormatException;
use Amp\Http\Client\Cookie\CookieJar;
use Amp\Http\Client\Internal\PublicSuffixList;
use Amp\Promise;
use function Amp\call;

final class CookieHandler implements NetworkInterceptor
{
    /** @var CookieJar */
    private $cookieJar;

    public function __construct(CookieJar $cookieJar)
    {
        $this->cookieJar = $cookieJar;
    }

    public function interceptNetworkRequest(
        Request $request,
        CancellationToken $cancellationToken,
        ConnectionInfo $connectionInfo,
        Client $next
    ): Promise {
        return call(function () use ($request, $cancellationToken, $next) {
            $request = $this->assignApplicableRequestCookies($request);

            /** @var Response $response */
            $response = yield $next->request($request, $cancellationToken);

            if ($response->hasHeader('Set-Cookie')) {
                $requestDomain = $response->getRequest()->getUri()->getHost();
                $cookies = $response->getHeaderArray('Set-Cookie');

                foreach ($cookies as $rawCookieStr) {
                    $this->storeResponseCookie($requestDomain, $rawCookieStr);
                }
            }
        });
    }

    private function assignApplicableRequestCookies(Request $request): Request
    {
        $uri = $request->getUri();

        $domain = $uri->getHost();
        $path = $uri->getPath();

        if (!$applicableCookies = $this->cookieJar->get($domain, $path)) {
            // No cookies matched our request; we're finished.
            return $request;
        }

        $isRequestSecure = \strcasecmp($uri->getScheme(), "https") === 0;
        $cookiePairs = [];

        /** @var Cookie $cookie */
        foreach ($applicableCookies as $cookie) {
            if ($isRequestSecure || !$cookie->isSecure()) {
                $cookiePairs[] = $cookie->getName() . "=" . $cookie->getValue();
            }
        }

        if ($cookiePairs) {
            if ($request->hasHeader('Cookie')) {
                \array_unshift($cookiePairs, $request->getHeader('Cookie'));
            }

            return $request->withHeader("Cookie", \implode("; ", $cookiePairs));
        }

        return $request;
    }

    private function storeResponseCookie(string $requestDomain, string $rawCookieStr): void
    {
        try {
            $cookie = Cookie::fromString($rawCookieStr);

            if (!$cookie->getDomain()) {
                $cookie = $cookie->withDomain($requestDomain);
            } else {
                // https://tools.ietf.org/html/rfc6265#section-4.1.2.3
                $cookieDomain = $cookie->getDomain();

                // If a domain is set, left dots are ignored and it's always a wildcard
                $cookieDomain = \ltrim($cookieDomain, ".");

                if ($cookieDomain !== $requestDomain) {
                    // ignore cookies on domains that are public suffixes
                    if (PublicSuffixList::isPublicSuffix($cookieDomain)) {
                        return;
                    }

                    // cookie origin would not be included when sending the cookie
                    if (\substr($requestDomain, 0, -\strlen($cookieDomain) - 1) . "." . $cookieDomain !== $requestDomain) {
                        return;
                    }
                }

                // always add the dot, it's used internally for wildcard matching when an explicit domain is sent
                $cookie = $cookie->withDomain("." . $cookieDomain);
            }

            $this->cookieJar->store($cookie);
        } catch (CookieFormatException | InvalidNameException $e) {
            // Ignore malformed Set-Cookie headers
        }
    }
}
