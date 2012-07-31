<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException;

class Client {

    /**
     * @var array
     */
    protected $contextOptions = array('http' => array(
        'max_redirects' => 10,
        'ignore_errors' => TRUE
    ));
    
    /**
     * @var bool
     */
    protected $allowUrlFopen;

    /**
     * @return void
     */
    public function __construct() {
        $this->allowUrlFopen = $this->getAllowUrlFopenStatus();
    }
    
    /**
     * @return bool
     */
    protected function getAllowUrlFopenStatus()  {
        return filter_var(
            ini_get('allow_url_fopen'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @throws RuntimeException
     * @return Artax\Http\Response
     */
    public function send(Request $request) {
        if (!$this->allowUrlFopen) {
            throw new RuntimeException(
                '`allow_url_fopen` must be enabled to use Artax\\Http\\Client'
            );
        }
        
        $context = $this->buildStreamContext($request);
        $stream  = $this->buildStream($request->getUri(), $context);
        
        if ($stream === FALSE) {
            throw new RuntimeException();
        }
        
        $bodyData = $this->getStreamBodyData($stream);
        $metaData = $this->getStreamMetaData($stream);
        $headers  = $this->buildHeadersFromWrapperData($metaData);
        
        return $this->buildResponse($headers, $bodyData);
    }
    
    /**
     * @return resource
     */
    protected function buildStreamContext(Request $request) {
        if ($headers = $request->getAllHeaders()) {
            $streamFormattedHeaders = array();
            foreach($headers as $header => $value) {
                $streamFormattedHeaders[] = "$header: $value";
            }
            $this->contextOptions['http']['header'] = $streamFormattedHeaders;
        }

        $this->contextOptions['http']['content'] = $request->getBody();
        $this->contextOptions['http']['method'] = $request->getMethod();
        $this->contextOptions['http']['protocol_version'] = $request->getHttpVersion();
        
        return stream_context_create($this->contextOptions);
        
    }
    
    /**
     * @return resource
     */
    protected function buildStream($uri, $context) {
        return @fopen($uri, 'rb', $useIncludePath = FALSE, $context);
    }
    
    /**
     * @param resource $stream
     * @return array
     */
    protected function getStreamMetaData($stream) {
        $metaData = stream_get_meta_data($stream);
        return $metaData['wrapper_data'];
    }
    
    /**
     * @param resource $stream
     * @return string
     */
    protected function getStreamBodyData($stream) {
        return stream_get_contents($stream);
    }
    
    /**
     * @return array
     */
    protected function buildHeadersFromWrapperData($wrapperData) {
        $headers = array();

        foreach ($wrapperData as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $headers[] = array($header);
            } else {
                $headers[count($headers)-1][] = $header;
            }

        }

        return $headers;
    }

    /**
     * @todo Add more error handling
     * @param array $headers
     * @param string $body
     * @return Artax\Http\Response
     */
    protected function buildResponse($headers, $body) {
        $lastHeader = $headers[count($headers) - 1];
        $response = new StdResponse();

        $response->setStartLine($lastHeader[0]);
        for ($i = 1, $headerCount = count($lastHeader); $i < $headerCount; $i++) {
            $response->setRawHeader($lastHeader[$i]);
        }

        $response->setBody($body);

        return $response;
    }

    /**
     * @param int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->contextOptions['http']['max_redirects'] = $maxRedirects;
    }
}

