<?php

namespace Artax\Http;

use RuntimeException,
    Artax\Http\Exceptions\InfiniteRedirectException,
    Artax\Http\Exceptions\MessageParseException,
    Artax\Http\Exceptions\TransferException,
    Artax\Http\Exceptions\ConnectException,
    Artax\Http\Exceptions\NoResponseException;

class Client {
    
    /**
     * @var string
     */
    protected $userAgent = 'Artax-Http/0.1 (PHP5.3+)';
    
    /**
     * @var int
     */
    protected $timeout = 60;
    
    /**
     * @var int
     */
    protected $maxRedirects = 10;
    
    /**
     * @var int
     */
    protected $currentRedirectIteration = 0;
    
    /**
     * @var bool
     */
    protected $nonStandardRedirectFlag = false;
    
    /**
     * @var bool
     */
    protected $proxyStyle = false;
    
    /**
     * @var int
     */
    protected $chunkSize = 8192;
    
    /**
     * @var array
     */
    protected $sslOptions = array();

    /**
     * @var array
     */
    protected $responseChain;
    
    /**
     * @var array
     */
    protected $redirectHistory;
    
    /**
     * @var bool
     */
    protected $acceptedEncodings;
    
    /**
     * @var array
     */
    protected $connectionPool = array();

    public function __construct() {
        $this->acceptedEncodings = $this->getAcceptedEncodings();
    }
    
    /**
     * @return bool
     */
    protected function isOpenSslLoaded() {
        return extension_loaded('openssl');
    }
    
    /**
     * @return void
     */
    public function getAcceptedEncodings() {
        $encodings = array();
        
        if (function_exists('gzdecode')) {
            $encodings[] = 'gzip';
        }
        if (function_exists('gzinflate')) {
            $encodings[] = 'deflate';
        }
        
        $encodings[] = 'identity';
        
        return implode(',', $encodings);
    }

    /**
     * Turn on/off the use of proxy-style requests
     * 
     * @param bool boolFlag
     * @return void
     */
    public function setProxyStyle($boolFlag) {
        $this->proxyStyle = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set the number of seconds before a socket connection idles out.
     * 
     * @param int $seconds
     * @return void
     */
    public function setTimeout($seconds) {
        $this->timeout = (int) $seconds;
    }

    /**
     * Set custom SSL request options
     * 
     * To customize SSL connections, assign a key-value associative array of option values:
     * 
     *     $options = array(
     *         'verify_peer'       => true,
     *         'allow_self_signed' => true,
     *         'cafile'            => '/hard/path/to/cert/authority/file'
     *     );
     *     
     *     $client->setOptions($options);
     *  
     * 
     * A full list of available options may be viewed here:
     * http://www.php.net/manual/en/context.ssl.php
     * 
     * @param array $options
     * @return void
     */
    public function setSslOptions(array $options) {
        $this->sslOptions = $options;
    }

    /**
     * Set the maximum number of redirects allowed to fulfill a request. Defaults to 10.
     * 
     * @param int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->maxRedirects = (int) $maxRedirects;
    }
    
    /**
     * Should the client transparently redirect requests not using GET or HEAD? Defaults to false.
     * 
     * According to RFC2616-10.3, "If the 301 status code is received in response to a request other
     * than GET or HEAD, the user agent MUST NOT automatically redirect the request unless it can be
     * confirmed by the user, since this might change the conditions under which the request was
     * issued."
     * 
     * This directive, if set to true, serves as confirmation that requests made using methods other
     * than GET/HEAD may be redirected automatically.
     * 
     * @param bool $boolFlag
     * @return void
     */
    public function allowNonStandardRedirects($boolFlag) {
        $this->nonStandardRedirectFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Request an HTTP resource
     * 
     * @param Request $request
     * @return Response
     * @throws Artax\Http\Exceptions\MessageValidationException
     * @throws Artax\Http\Exceptions\ConnectException
     */
    public function request(Request $request) {
        $this->responseChain = array();
        $this->redirectHistory = array();
        return $this->doRequest($request);
    }
    
    /**
     * Request an HTTP resource, returning an array of transparently redirected Responses
     * 
     * @param Request $request
     * @return array
     * @throws Artax\Http\Exceptions\MessageValidationException
     * @throws Artax\Http\Exceptions\ConnectException
     */
    public function requestRedirectTrace(Request $request) {
        $this->request($request);
        return $this->responseChain;
    }
    
    /**
     * Send an HTTP request and don't bother to wait for a response
     * 
     * @todo add error handling for networking failures
     * @param Request $request
     * @return void
     * @throws Artax\Http\Exceptions\MessageValidationException
     * @throws Artax\Http\Exceptions\ConnectException
     */
    public function requestAsync(Request $request) {
        $request = $this->normalizeRequestHeaders($request);
        
        $socketUri = $this->getSocketUriFromRequest($request);
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $stream = $this->openConnection($socketUri, $flags);
        
        $this->writeRequestToStream($request, $stream);
        $this->closeConnection($stream);
    }
    
    /**
     * @param Request $request
     * @return Response
     * @throws Artax\Http\Exceptions\MessageParseException
     */
    protected function doRequest(Request $request) {
        $request   = $this->normalizeRequestHeaders($request);
        $socketUri = $this->getSocketUriFromRequest($request);
        $stream    = $this->openConnection($socketUri);
        
        $this->writeRequestToStream($request, $stream);
        $response = $this->readResponseHeadersFromStream($stream);
        
        $statusCode = $response->getStatusCode();
        
        if ($this->statusCodeAllowsEntityBody($statusCode)) {
            $this->readEntityBodyFromStream($response, $stream);
        }
        
        if ($this->shouldCloseConnection($response)) {
            $this->closeConnection($stream);
        }
        
        $this->responseChain[] = $response;
        
        if ($this->canRedirect($request, $response)) {
            $response = $this->doRedirect($request, $response);
        }
        
        return $response;
    }
    
    /**
     * Whether or not a Response should result in a closed connection
     * 
     * "... unless otherwise indicated, the client SHOULD assume that the server will maintain "
     * a persistent connection"
     * 
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec8.html#sec8.1.2
     * 
     * @param Response $response
     * @return bool
     */
    protected function shouldCloseConnection(Response $response) {
        if (!$response->hasHeader('Connection')) {
            return false;
        }
        return ('close' == $response->getHeader('Connection'));
    }
    
    /**
     * @param Request $request
     * @return Request
     * @throws Artax\Http\Exceptions\MessageValidationException
     */
    public function normalizeRequestHeaders(Request $request) {
        $mutable = new MutableStdRequest();
        
        $mutable->populateFromRequest($request);
        $mutable->validateMessage();
        
        $mutable->setHeader('User-Agent', $this->userAgent);
        $mutable->setHeader('Accept-Encoding', $this->acceptedEncodings);
        
        $body = $mutable->getBodyStream() ?: $mutable->getBody();
        
        if (!$body) {
            return $mutable;
        } elseif (is_resource($body)) {
            $mutable->setHeader('Transfer-Encoding', 'chunked');
        } else {
            $mutable->setHeader('Content-Length', strlen($body));
        }
        
        return $mutable;
    }
    
    /**
     * @param Request $request
     * @param resource $stream
     * @return void
     * @throws Artax\Http\Exceptions\TransferException
     */
    protected function writeRequestToStream(Request $request, $stream) {
        $rawHeaders = $this->buildRawRequestHeaders($request);
        $this->writeStringDataToStream($rawHeaders, $stream);
        
        $body = $request->getBodyStream() ?: $request->getBody();
        
        if (!$body) {
            return;
        } elseif (is_resource($body)) {
            $this->streamOutboundRequestBody($body, $stream);
        } else {
            $this->writeStringDataToStream($body, $stream);
        }
    }
    
    /**
     * @param Request $request
     * @return string
     */
    protected function buildRawRequestHeaders(Request $request) {
        $rawHeaders = '';
        
        if (!$this->proxyStyle) {
            $rawHeaders.= $request->getRequestLine() . "\r\n";
            $rawHeaders.= 'HOST: ' . $request->getAuthority() . "\r\n";
            
            $headers = $request->getAllHeaders();
            unset($headers['HOST']);
            foreach ($headers as $header => $value) {
                $rawHeaders.= "$header: $value\r\n";
            }
            
        } else {
            $rawHeaders.= $request->getProxyRequestLine() . "\r\n";
            foreach ($request->getAllHeaders() as $header => $value) {
                $rawHeaders.= "$header: $value\r\n";
            }
        }
        
        $rawHeaders.= "\r\n";
        return $rawHeaders;
    }
    
    /**
     * @param string $dataStr
     * @param resource $stream
     */
    protected function writeStringDataToStream($dataStr, $stream) {
        $originalLength = strlen($dataStr);
        $bytesRemaining = $originalLength;
        
        while (false !== ($bytes = @fwrite($stream, $dataStr))) {
            $bytesRemaining -= $bytes;
            if ($bytesRemaining <= 0) {
                return;
            }
        }
        
        throw new TransferException(
            "Connection failure: $bytesRemaining remaining to send at failure"
        );
    }
    
    /**
     * @param resource $inputBodyStream
     * @param resource $outputStream
     * @return void
     * @throws RuntimeException
     * @throws Artax\Http\Exceptions\TransferException
     */
    protected function streamOutboundRequestBody($inputBodyStream, $outputStream) {
        $bytesSent = 0;
        
        while (!feof($inputBodyStream)) {
            
            if (false !== ($data = @fread($inputBodyStream, $this->chunkSize))) {
                
                $chunkLength = strlen($data);
                $chunk = dechex($chunkLength) . "\r\n$data\r\n";
                
                if (false !== @fwrite($outputStream, $chunk)) {
                    $bytesSent += $chunkLength;
                    continue;
                }
                
                throw new TransferException(
                    "Connection failure: $bytesSent bytes streamed from request body " .
                    "$inputBodyStream prior to transmission failure"
                );
            }
            
            throw new RuntimeException(
                "Failed reading data from request body stream $inputBodyStream"
            );
        }
        
        fwrite($outputStream, "0\r\n\r\n");
    }
    
    
    /**
     * @param resource $stream
     * @return Response $response
     * @throws Artax\Http\Exceptions\TransferException
     */
    protected function readResponseHeadersFromStream($stream) {
        $response = new MutableStdResponse;
        if (false !== ($startLine = @fgets($stream))) {
            $response->setStartLine($startLine);
        } else {
            throw new NoResponseException();
        }
        
        $headerBuffer = '';
        while (false !== ($line = @fgets($stream))) {
            $headerBuffer .= $line;
            if ($line == "\r\n") {
                break;
            }
        }
        
        $headers = rtrim($headerBuffer);
        
        if ($headers) {
            $response->setAllRawHeaders($headers);
        }
        
        return $response;
    }
    
    /**
     * @param Response $response
     * @param resource $stream
     * @return void
     */
    protected function readEntityBodyFromStream(Response $response, $stream) {
        
        if ($this->isChunked($response)) {
            $body = $this->readChunkedEntityBody($stream);
        } elseif ($response->hasHeader('Content-Length')) {
            $length = (int) $response->getHeader('Content-Length');
            $body = $this->readEntityBodyWithContentLength($stream, $length);
        } else {
            $body = $this->readEntityBodyFromClosingConnection($stream);
        }
        
        if ($response->hasHeader('Content-Encoding')) {
            $encoding = strtolower($response->getHeader('Content-Encoding'));
            $body = $this->decodeEntityBody($encoding, $body);
        }
        
        $response->setBody($body);
    }
    
    /**
     * @param resource $stream
     * @return string
     * @throws Artax\Http\Exceptions\TransferException
     */
    protected function readChunkedEntityBody($stream) {
        $tmpHandle = fopen('php://memory', 'r+');
        $bytesRead = 0;
        while (false !== ($data = @fgets($stream))) {
            fwrite($tmpHandle, $data);
            $bytesRead += strlen($data);
            if ($data == "0\r\n") {
                break;
            }
        }
        
        if (false === $data) {
            $s = $bytesRead == 1 ? '' : 's';
            throw new TransferException(
                "Connection failure: headers received, $bytesRead byte$s of chunked entity body " .
                "read prior to error"
            );
        }
        
        stream_filter_prepend($tmpHandle, 'dechunk');
        rewind($tmpHandle);
        return stream_get_contents($tmpHandle);
    }
    
    /**
     * @param resource $stream
     * @param int $contentLength
     * @return string
     * @throws Artax\Http\Exceptions\TransferException
     */
    protected function readEntityBodyWithContentLength($stream, $contentLength) {
        if (!$contentLength) {
            return;
        }
        
        $buffer = '';
        $bytesRead = 0;
        while (false !== ($data = @fread($stream, $contentLength))) {
            $buffer.= $data;
            $bytesRead += strlen($data);
            if ($bytesRead >= $contentLength) {
                return $buffer;
            }
        }
        
        $s = $bytesRead == 1 ? '' : 's';
        throw new TransferException(
            "Connection failure: headers received, $bytesRead entity body byte$s read prior to error"
        );
    }
    
    /**
     * @param resource $stream
     * @return string
     * @throws Artax\Http\Exceptions\TransferException
     */
    protected function readEntityBodyFromClosingConnection($stream) {
        $buffer = '';
        while ($data = @fread($stream, $this->chunkSize)) {
            $buffer .= $data;
        }
        
        if (false === $data) {
            $bytesRead = strlen($buffer);
            $s = $bytesRead == 1 ? '' : 's';
            throw new TransferException(
                "Connection failure: headers received, $bytesRead entity body byte$s read prior  " .
                "to error"
            );
        }
        
        return $buffer;
    }
    
    /**
     * @param Response $response
     * @return bool
     */
    protected function isChunked(Response $response) {
        if (!$response->hasHeader('Transfer-Encoding')) {
            return false;
        }
        
        $transferEncoding = $response->getHeader('Transfer-Encoding');
        
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
        // 
        // "If a Transfer-Encoding header field (section 14.41) is present and has any value other 
        // than "identity", then the transfer-length is defined by use of the "chunked" 
        // transfer-coding (section 3.6), unless the message is terminated by closing the 
        // connection.
        $isChunked = strtolower($transferEncoding) !== 'identity';
        
        return $isChunked;
    }
    
    /**
     * @param int $statusCode
     * @return bool
     */
    protected function statusCodeAllowsEntityBody($statusCode) {
        if ($statusCode == 204) {
            return false;
        }
        if ($statusCode == 304) {
            return false;
        }
        if ($statusCode >= 100 && $statusCode < 200) {
            return false;
        }
        return true;
    }
    
    /**
     * @param string $encoding
     * @param string $encodedBodyStr
     * @return string
     */
    protected function decodeEntityBody($encoding, $encodedBodyStr) {
        switch (strtolower($encoding)) {
            case 'gzip':
                return gzdecode($encodedBodyStr);
            case 'deflate':
                return gzinflate($encodedBodyStr);
            default:
                return $encodedBodyStr;
        }
    }
    
    /**
     * @param Request $request
     * @return string
     */
    protected function getSocketUriFromRequest(Request $request) {
        $scheme = strcmp('https', $request->getScheme()) ? 'tcp' : 'ssl';
        $authority = $request->getHost() . ':' . $request->getPort();
        
        return "$scheme://$authority";
    }
    
    /**
     * @param string $socketUri
     * @return resource
     */
    protected function openConnection($socketUri, $flags = STREAM_CLIENT_CONNECT) {
        if (isset($this->connectionPool[$socketUri])) {
            return $this->connectionPool[$socketUri];
        }
        
        $stream = $this->makeStreamFromSocketUri($socketUri, $flags);
        $this->connectionPool[$socketUri] = $stream;
        
        return $stream;
    }
    
    /**
     * @param string $uri
     * @param int $flags
     * @return resource
     * @throws Artax\Http\Exceptions\ConnectException
     */
    protected function makeStreamFromSocketUri($uri, $flags) {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        
        if ('tcp' == $scheme) {
            list($stream, $errNo, $errStr) = $this->getTcpStream($uri, $flags);
        } else {
            if (!$this->isOpenSslLoaded()) {
                throw new RuntimeException(
                    '`openssl` extension must be loaded to originate SSL requests'
                );
            }
            
            $context = stream_context_create(array('ssl' => $this->sslOptions));
            list($stream, $errNo, $errStr) = $this->getSslStream($uri, $flags, $context);
        }
        
        if (false === $stream) {
            throw new ConnectException($errStr, $errNo);
        }
        
        return $stream;
    }
    
    /**
     * A test seam for mocking TCP socket streams
     * 
     * @param string $uri
     * @param int $flags
     */
    protected function getTcpStream($uri, $flags) {
        $stream = @stream_socket_client($uri, $errNo, $errStr, $this->timeout, $flags);
        
        // The stupid "by-reference" error parameters make this a PITA to mock and still provide
        // meaningful error context information. As a result, we return the stream result in tandem
        // with the generated error info inside an array.
        return array($stream, $errNo, $errStr);
    }
    
    /**
     * A test seam for mocking SSL socket streams
     * 
     * @param string $uri
     * @param int $flags
     * @param resource $context
     */
    protected function getSslStream($uri, $flags, $context) {
        $stream = @stream_socket_client($uri, $errNo, $errStr, $this->timeout, $flags, $context);
        
        // An array is returned for the same reason as in Client::getTcpStream
        return array($stream, $errNo, $errStr);
    }
    
    /**
     * @param string $socketUri
     * @return void
     */
    protected function closeConnection($stream) {
        @fclose($stream);
        if (false !== ($key = array_search($stream, $this->connectionPool))) {
            unset($this->connectionPool[$key]);
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    protected function canRedirect(Request $request, Response $response) {
        if ($this->currentRedirectIteration >= $this->maxRedirects) {
            return false;
        }
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        $requestMethod = strtoupper($request->getMethod());
        if (!$this->nonStandardRedirectFlag && !in_array($requestMethod, array('GET', 'HEAD'))) {
            return false;
        }
        
        return true;
    }

    /**
     * @param Request $lastRequest
     * @param Response $lastResponse
     * @return Response
     */
    protected function doRedirect(Request $lastRequest, Response $lastResponse) {
        $this->redirectHistory[] = $lastRequest->getRawUri();
        $newLocation = $this->normalizeLocationHeader($lastRequest, $lastResponse);
        
        if (in_array($newLocation, $this->redirectHistory)) {
            throw new InfiniteRedirectException(
                "Infinite redirect loop detected and aborted while redirecting to $newLocation"
            );
        }
        
        $redirectedRequest = new StdRequest(
            $newLocation,
            $lastRequest->getMethod(),
            $lastRequest->getAllHeaders(),
            $lastRequest->getBody(),
            $lastRequest->getHttpVersion()
        );
        
        ++$this->currentRedirectIteration;
        
        return $this->doRequest($redirectedRequest);
    }

    /**
     * @param Request $lastRequest
     * @param Response $lastResponse
     * @return string
     */
    protected function normalizeLocationHeader(Request $lastRequest, Response $lastResponse) {
        $locationHeader = $lastResponse->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $lastRequest->getScheme() . '://' . $lastRequest->getRawAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $lastResponse->setHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
        } else {
            $newLocation = $locationHeader;
        }
        
        return $newLocation;
    }
    
}
