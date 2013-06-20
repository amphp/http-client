<?php

namespace Artax\Ext\Cookies;

use Artax\ObservableClient,
    Artax\Extension;

class CookieExtension implements Extension {
    
    private $cookieJar;
    private $eventSubscription;
    
    function __construct(CookieJar $cookieJar = NULL) {
        $this->cookieJar = $cookieJar ?: new ArrayCookieJar;
    }
    
    private function onRequest(array $dataArr) {
        $request = current($dataArr);
        
        $urlParts = parse_url($request->getUri());
        $scheme = $urlParts['scheme'];
        $domain = $urlParts['host'];
        $path = isset($urlParts['path']) ? $urlParts['path'] : '/';
        
        $applicableCookies = $this->cookieJar->get($domain, $path);
        
        foreach ($applicableCookies as $cookie) {
            if (!$cookie->getSecure() || !strcasecmp($scheme, 'https')) {
                $cookieStr = $cookie->getName() . '=' . $cookie->getValue();
                $request->appendHeader('Cookie', $cookieStr);
            }
        }
    }
    
    private function onResponse(array $dataArr) {
        list($request, $response) = $dataArr;
        
        if ($response->hasHeader('Set-Cookie')) {
            $requestDomain = parse_url($request->getUri(), PHP_URL_HOST);
            foreach ($response->getHeader('Set-Cookie') as $rawCookieStr) {
                $this->storeRawResponseCookie($requestDomain, $rawCookieStr);
            }
        }
    }
    
    private function storeRawResponseCookie($requestDomain, $rawCookieStr) {
        try {
            $cookie = Cookie::fromString($rawCookieStr);
            
            if (!$cookie->getDomain()) {
                $cookie = new Cookie(
                    $cookie->getName(),
                    $cookie->getValue(),
                    $cookie->getExpirationTime(),
                    $cookie->getPath(),
                    $requestDomain,
                    $cookie->getSecure(),
                    $cookie->getHttpOnly()
                );
            }
            $this->cookieJar->store($cookie);
            
        } catch (\InvalidArgumentException $e) {
            // Ignore malformed Set-Cookie headers
        }
    }
    
    function subscribe(ObservableClient $client) {
        $this->unsubscribe();
        $this->eventSubscription = $client->subscribe([
            ObservableClient::REQUEST => function($dataArr) { $this->onRequest($dataArr); },
            ObservableClient::RESPONSE => function($dataArr) { $this->onResponse($dataArr); }
        ]);
    }
    
    function unsubscribe() {
        if ($this->eventSubscription) {
            $this->eventSubscription->cancel();
            $this->eventSubscription = NULL;
        }
    }
    
}
