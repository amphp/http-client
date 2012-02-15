<?php

/**
 * Artax HttpRequest Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\http {

  /**
   * HttpRequest Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpRequest implements \artax\RequestInterface
  {
    /**
     * Bucket of server vars generated for the request
     * @var ServerBucket
     */
    protected $server;
    
    /**
     * Bucket of headers submitted with the request
     * @var HeaderBucket
     */
    protected $headers;
    
    /**
     * Bucket of GET/POST parameters submitted with the request
     * @var ParamBucket
     */
    protected $params;
    
    /**
     * Bucket of COOKIE parameters submitted with the request
     * @var CookieBucket
     */
    protected $cookies;
    
    /**
     * Request URI
     * @var string
     */
    protected $uri;
    
    /**
     * HTTP request method (GET/PUT/POST/HEAD/DELETE/etc.)
     * @var string
     */
    protected $method;
    
    /**
     * HTTP request data body (PUT/POST)
     * @var string
     */
    protected $body;
    
    /**
     * HTTP request protocol (http/https)
     * @var string
     */
    protected $protocol;
    
    /**
     * Requested HTTP host
     * @var string
     */
    protected $host;
    
    /**
     * HTTP request query string
     * @var string
     */
    protected $queryString;
    
    /**
     * Flag specifying if the request was made via AJAX
     * @var bool
     */
    protected $isAajax;
    
    /**
     * IP address of requesting client
     * @var string
     */
    protected $clientIP;
    
    /**
     * PHP DateTime object representing the time of the request
     * @var \DateTime
     */
    protected $time;
    
    /**
     * 
     */
    public function __construct(Array $server=NULL, Array $get=NULL, Array $post=NULL,
      Array $cookie=NULL)
    {
      $this->server  = new ServerBucket($server);
      $this->headers = new HeaderBucket($server);
      $this->params  = new ParamBucket($get, $post);
      $this->cookies = new CookieBucket($cookie);
      
      $this->detectUri()
        ->detectMethod()
        ->detectBody()
        ->detectHost()
        ->detectProtocol()
        ->detectQueryString()
        ->detectIsAjax()
        ->detectClientIP()
        ->detectAddress()
        ->detectTime();
    }
    
    /**
     * Returns the requested target (the REQUEST_URI for HTTP requests)
     * 
     * Note that if the request URI has not been detected the method will return
     * `NULL`. This is different from a request for the base of the webroot, which
     * will result in a `/` return value.
     * 
     * @return string Returns requested URI target or `NULL` if not set
     */
    public function getTarget()
    {
      return $this->uri;
    }
    
    /**
     * Retrieve the HTTP request method
     * 
     * This method is used by the built-in matcher to apply HTTP method match
     * constraints for specific routes.
     * 
     * @return string Returns request method
     */
    public function getMethod()
    {
      return $this->method;
    }
    
    /**
     * Detect HTTP request URI
     * 
     * @return Object instance for method chaining
     * @throws HttpException On URI detection failure
     */
    protected function detectUri()
    {
      if ( ! empty($this->server['REQUEST_URI'])) {
        $uri = $this->server['REQUEST_URI'];
        if ($q = strpos($uri, '?')) {
          $uri = substr($uri, 0, $q);
        }
      } elseif ( ! empty($this->server['REDIRECT_URL'])) {
        $uri = $this->server['REDIRECT_URL'];
      } else {
        $msg = 'Cannot detect URI: No valid SERVER key exists';
        throw new HttpException($msg);
      }
      $uri = trim(urldecode($uri), '/');
      $this->uri = "/$uri";
      return $this;
    }
    
    /**
     * Detect HTTP Request Method
     * 
     * @return Object instance for method chaining
     * @throws HttpException On method detection failure
     */
    protected function detectMethod()
    {
      if ( ! isset($this->server['REQUEST_METHOD'])) {
        $msg = 'Cannot detect method: No valid SERVER key exists';
        throw new HttpException($msg);
      }
      $this->method = strtoupper($this->server['REQUEST_METHOD']);
      return $this;
    }
    
    /**
     * Setter method for object $protocol property
     * 
     * When using ISAPI with IIS, the value will be "off" if the request was
     * not made through the HTTPS protocol. As a result, we filter the
     * value to a bool.
     * 
     * @return Object instance for method chaining
     */
    protected function detectProtocol()
    {
      if (isset($this->server['HTTPS'])
        && filter_var($this->server['HTTPS'], FILTER_VALIDATE_BOOLEAN)) {
        
        $this->protocol = 'https';
      } else {
        $this->protocol = 'http';
      }
      return $this;
    }
    
    /**
     * Detect HTTP Host property
     * 
     * @return Object instance for method chaining
     * @throws HttpException On host detection failure
     */
    protected function detectHost()
    {
      if ( ! isset($this->headers['Host'])) {
        $msg = 'Cannot detect host: No "Host" header exists';
        throw new HttpException($msg);
      }
      $this->host = $this->headers['Host'];
      return $this;
    }
    
    /**
     * Assign Request Query String property
     * 
     * @return Object instance for method chaining
     */
    protected function detectQueryString()
    {
      $this->queryString = ! empty($this->server['QUERY_STRING'])
        ? $this->server['QUERY_STRING']
        : '';
      return $this;
    }
    
    /**
     * Combines detected URL parts together to form the full request address
     * 
     * @return Object instance for method chaining
     */
    protected function detectAddress()
    {
      $uri = $this->protocol . '://' . $this->host . $this->uri;
      $uri.= $this->queryString ? '?' . $this->queryString : '';
      $this->address = $uri;
      return $this;
    }
    
    /**
     * Assigns request POST or PUT data to the protected $body property
     * 
     * If no request body is sent with an HTTP request, the STDIN constant is
     * not defined and we return an empty string. If STDIN is defined and we
     * are operating in the CLI environment, PHP will wait forever for
     * stream_get_contents() to return. So, we check the stream meta data to
     * see if there are any unread bytes in the stream and only wait for the
     * stream data if it actually exists.
     * 
     * @return Object instance for method chaining
     */
    protected function detectBody()
    {
      if ( ! defined('STDIN')) {
        $body = '';
      } else {
        $body = stream_get_meta_data(STDIN)['unread_bytes'] > 0
          ? stream_get_contents(STDIN)
          : '';
      }
      $this->body = $body;
      return $this;
    }
    
    /**
     * Setter method for object $ajax_flag property
     * 
     * @return Object instance for method chaining
     */
    protected function detectIsAjax()
    {
      if (isset($this->headers['X-Requested-With'])
        && strtolower($this->headers['X-Requested-With']) == 'xmlhttprequest'
      ) {
        $flag = TRUE;
      } else {
        $flag = FALSE;
      }
      $this->isAjax = $flag;
      return $this;
    }
    
    /**
     * Setter method for object $client_ip property
     * 
     * @return Object instance for method chaining
     */
    protected function detectClientIP()
    {
      if ( ! empty($this->server['HTTP_X_FORWARDED_FOR'])) {
        // Use the forwarded IP address, typically set when the
        // client is using a proxy server.
        $ip = $this->server['HTTP_X_FORWARDED_FOR'];
      } elseif ( ! empty($this->server['HTTP_CLIENT_IP'])) {
        // Use the forwarded IP address, typically set when the
        // client is using a proxy server.
        $ip = $this->server['HTTP_CLIENT_IP'];
      } elseif ( ! empty($this->server['REMOTE_ADDR'])) {
        // The remote IP address
        $ip = $this->server['REMOTE_ADDR'];
      } else {
        $msg = 'Cannot detect client IP: No valid SERVER keys exist';
        throw new HttpException($msg);
      }
      $this->clientIP = $ip;
      
      return $this;
    }
    
    /**
     * Detect request timestamp
     * 
     * @return \DateTime Returns a DateTime object or `NULL` on missing key
     */
    protected function detectTime()
    {
      $time = empty($this->server['REQUEST_TIME'])
        ? NULL
        : new \DateTime(date(\DateTime::ISO8601, $this->server['REQUEST_TIME']));
        
      $this->time = $time;
      return $this;
    }
    
    /**
     * Magic retrieval for protected properties
     * 
     * @param string $prop Object property name
     * 
     * @return mixed Returns value for requested property
     * @throws \artax\exceptions\OutOfBoundsException On inaccessible property name
     */
    public function __get($prop)
    {
      if (property_exists($this, $prop)) {
        return $this->$prop;
      }
      $msg = 'Invalid property: ' . get_class($this) . "::\$$prop does not exist";
      throw new \artax\exceptions\OutOfBoundsException($msg);
    }
  }
}
