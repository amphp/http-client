<?php

namespace Artax\Ext\Cookies;

use Artax\ObservableClient,
    Artax\Ext\Extension;

class CookieExtension implements Extension {
    
    private $cookieJar;
    private $cookieParser;
    private $observation;
    private $combineOutboundCookies = TRUE;
    
    function __construct(CookieJar $cookieJar = NULL, CookieParser $cookieParser = NULL) {
        $this->cookieJar = $cookieJar ?: new ArrayCookieJar;
        $this->cookieParser = $cookieParser ?: new CookieParser;
    }
    
    /**
     * Observer event broadcasts from the specified client
     * 
     * @param Artax\ObservableClient $client The client whose events we want to observe
     * @return void
     */
    function extend(ObservableClient $client) {
        $this->unextend();
        $this->observation = $client->addObservation([
            ObservableClient::REQUEST => function($dataArr) { $this->onRequest($dataArr); },
            ObservableClient::RESPONSE => function($dataArr) { $this->onResponse($dataArr); }
        ]);
    }
    
    /**
     * Clear an existing client observation
     * 
     * @return void
     */
    function unextend() {
        if ($this->observation) {
            $this->observation->cancel();
            $this->observation = NULL;
        }
    }
    
    /**
     * Should multiple cookies be merged into a single cookie header when sent to servers?
     * 
     * While servers are technically required to correctly handle cookies split across multiple
     * headers, some fail in this regard. To provide maximum compatibility we combine cookie values
     * into a single header by default.
     * 
     * @param bool $combine Whether or not to auto-combine cookie headers on send
     * @return \Artax\Ext\Cookies\CookieExtension Returns the current object instance
     */
    public function combineOutboundCookies($combine = TRUE) {
        $this->combineOutboundCookies = filter_var($combine, FILTER_VALIDATE_BOOLEAN);
        
        return $this;
    }
    
    private function onRequest(array $dataArr) {
        $request = current($dataArr);
        
        $urlParts = parse_url($request->getUri());
        $domain = $urlParts['host'];
        $path = isset($urlParts['path']) ? $urlParts['path'] : '/';
        
        if ($applicableCookies = $this->cookieJar->get($domain, $path)) {
            $isRequestSecure = !strcasecmp($urlParts['scheme'], 'https');
            $this->assignApplicableCookies($request, $applicableCookies, $isRequestSecure);
        }
    }
    
    private function assignApplicableCookies($request, $applicableCookies, $isRequestSecure) {
        $cookiePairs = [];
        foreach ($applicableCookies as $cookie) {
            if (!$cookie->getSecure() || $isRequestSecure) {
                $cookiePairs[] = $cookie->getName() . '=' . $cookie->getValue();
            }
        }
        
        if ($cookiePairs) {
            $value = $this->combineOutboundCookies ? implode('; ', $cookiePairs) : $cookiePairs;
            $request->setHeader('Cookie', $value);
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
}
