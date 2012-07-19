<?php

namespace Artax\Http;

use RuntimeException;

class Client {

    /**
     * @var array
     */
    protected $contextOptions = array('http' => array(
        'max_redirects' => 10
    ));

    /**
     * @var array
     */
    protected $contextParameters = array();

    public function __construct() {

        $allowsUrlFOpen = filter_var(
            ini_get('allow_url_fopen'), 
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$allowsUrlFOpen) {
            throw new RuntimeException(
                'Artax\\Http\\Client requires setting allow_url_fopen to be enabled'
            );
        }

        $this->contextParameters['notification'] = array(
            $this,
            'notificationCallback'
        );

    }

    protected function notificationCallback(
        $notification_code, $severity, $message, 
        $message_code, $bytes_transferred, $bytes_max
    ) {
        echo "$notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max\n";
    }

    /**
     * @var int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->contextOptions['http']['max_redirects'] = $maxRedirects;
    }

    /**
     * @param \Artax\Http\Request $request
     * @throws RuntimeException
     * @return \Artax\Http\Response
     */
    public function send(Request $request) {
        $headers = $request->getAllHeaders();
        if (!empty($headers)) {
            $streamFormattedHeaders = array();
            foreach($headers as $header => $value) {
                $streamFormattedHeaders[] = "$header: $value";
            }
            $this->contextOptions['http']['header'] = $streamFormattedHeaders;
        }

        $this->contextOptions['http']['content'] = $request->getBody();
        $this->contextOptions['http']['method'] = $request->getMethod();
        
        $context = stream_context_create(
            $this->contextOptions,
            $this->contextParameters
        );

        $fp = @fopen(
            $request->getUri(),
            'rb',
            $useIncludePath = FALSE,
            $context
        );

        if ($fp === FALSE) {
            throw new RuntimeException();
        } 

        return $this->buildResponseFromStream($fp);

    }

    /**
     * @todo Add more error handling to the stream.
     * @param resource $stream
     * @return \Artax\Http\Response
     */
    protected function buildResponseFromStream($stream) {

        $meta_data = stream_get_meta_data($stream);
        $headers = $meta_data['wrapper_data'];
        
        $response = new StdResponse();
        
        $response->setStartLine($headers[0]);
        for ($i = 1, $headerCount = count($headers); $i < $headerCount; $i++) {
            $response->setRawHeader($headers[$i]);
        }

        $body = stream_get_contents($stream);

        $response->setBody($body);

        return $response;
    }

}

