<?php

namespace Artax\Ext\Cookies;

use Artax\ObservableClient,
    Artax\Ext\Extension;

class CookieExtension implements Extension {
    
    private $cookieJar;
    private $cookieParser;
    private $observation;
    private $combineResponseCookies = false;
    
    function __construct(CookieJar $cookieJar = NULL, CookieParser $cookieParser = NULL) {
        $this->cookieJar = $cookieJar ?: new ArrayCookieJar;
        $this->cookieParser = $cookieParser ?: new CookieParser;
    }
    
    function unextend() {
        if ($this->observation) {
            $this->observation->cancel();
            $this->observation = NULL;
        }
    }
    
    function extend(ObservableClient $client) {
        $this->unextend();
        $this->observation = $client->addObservation([
            ObservableClient::REQUEST => function($dataArr) { $this->onRequest($dataArr); },
            ObservableClient::RESPONSE => function($dataArr) { $this->onResponse($dataArr); }
        ]);
    }
    
    private function onRequest(array $dataArr) {
        $request = current($dataArr);
        
        $urlParts = parse_url($request->getUri());
        $scheme = $urlParts['scheme'];
        $domain = $urlParts['host'];
        $path = isset($urlParts['path']) ? $urlParts['path'] : '/';

        $applicableCookies = $this->cookieJar->get($domain, $path);
        
        if ($this->combineResponseCookies) {
            $cookieKeyValuePairs = [];
            
            foreach ($applicableCookies as $cookie) {
                if (!$cookie->getSecure() || !strcasecmp($scheme, 'https')) {
                    $cookieKeyValuePairs[] = $cookie->getName() . '=' . $cookie->getValue();
                }
            }

            if (!empty($cookieKeyValuePairs)) {
                $request->setHeader('Cookie', implode('; ', $cookieKeyValuePairs));
            }
        }
        else {
            foreach ($applicableCookies as $cookie) {
                if (!$cookie->getSecure() || !strcasecmp($scheme, 'https')) {
                    $cookieStr = $cookie->getName() . '=' . $cookie->getValue();
                    $request->appendHeader('Cookie', $cookieStr);
                }
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
            $cookie = $this->cookieParser->parse($rawCookieStr);
            
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
    
    public function combineResponseCookies($combine = true)
    {
        $this->combineResponseCookies = $combine;

        return $this;
    }
}
