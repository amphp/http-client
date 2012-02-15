<?php

/**
 * Artax HttpResponse Class File
 * 
 * PHP version 5.3
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax\blocks\http {
  
  /**
   * HttpResponse Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpResponse implements HttpResponseInterface
  {
    /**
     * The HTTP response body to output to send to the client
     * @var string
     */
    protected $body;
    
    /**
     * A key/value array of header parameters to send to the client
     * @var array
     */
    protected $headers;
    
    /**
     * Initializes object properties
     * 
     * @return void
     */
    public function __construct()
    {
      $this->body = '';
      $this->headers = [];
    }
    
    /**
     * Output the response to the client
     * 
     * @return void
     */
    public function exec()
    {
      $this->sendHeaders();
      echo $this->body;
    }
    
    /**
     * Setter method for object's $body property
     * 
     * @param string $body Response body text
     * 
     * @return Response Current object instance for method chaining
     */
    public function setBody($body)
    {
      $this->body = $body;
      return $this;
    }
    
    /**
     * Getter method for object's $body property
     * 
     * @return string Body text
     */
    public function getBody()
    {
      return $this->body;
    }
    
    /**
     * Add HTTP headers to send on response execution
     * 
     * @param string $name  The name of the header
     * @param string $value The header value
     * 
     * @return void
     */
    public function addHeader($name, $value)
    {
      $this->headers[$name] = $value;
    }
    
    /**
     * Getter method for object's $headers array
     * 
     * @return array Returns array of headers added to the response
     */
    public function getHeaders()
    {
      return $this->headers;
    }
    
    /**
     * Outputs all headers to the client
     * 
     * @return void
     */
    protected function sendHeaders()
    {
      foreach ($this->headers as $name => $value) {
        header("$name: $value");
      }
    }
    
    /**
     * Return the response body when object accessed as a string
     * 
     * @return string Body text
     */
    public function __toString()
    {
      return $this->body;
    }
    
    /**
     * Allow object invocation to execute the request
     * 
     * @return void
     */
    public function __invoke()
    {
      $this->exec();
    }
  }
}
