<?php

/**
 * Artax HttpHandlers Class File
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
   * Artax HttpHandlers Class
   *
   * Provides 500 error handling and termination for 404 requests
   *
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpHandlers extends \artax\HandlersAbstract
  {
    /**
     * @var HttpResponse
     */
    protected $response;
    
    /**
     * Initializes the exception/shutdown handlers
     * 
     * @return void
     */
    public function __construct(HttpResponse $response)
    {
      $this->response = $response;
    }
    
    /**
     * Handle requests for resources that can't be found
     * 
     * @return void
     * @throws exceptions\ScriptHaltException Ends script execution
     */
    public function notFound()
    {
      $body = "<h1>404 Not Found</h1>\n<hr/>\n";
      $body.= '<p>The requested resource was not found</p>' . PHP_EOL;
      $this->response->addHeader('HTTP/1.1 404 Not Found');
      $this->response->setBody($body);
      $this->response->exec();
    }

    /**
     * Handle undexpected exceptions
     *
     * @param \Exception $e Exception object instance
     *
     * @return void
     */
    public function unexpectedError(\Exception $e)
    {
      if ($this->debug) {
        $body = $body = $e->getMessage() .' in '. $e->getFile() .' on line ' .
          $e->getLine();
      } else {
        $body = "<h1>500 Internal Server Error</h1>\n<hr/>\n";
        $body.= '<p>It seems we\'ve messed up something on our end. Hang tight ' .
          'and let us know if you continue to receive this message.</p>';
      }
      $body .= PHP_EOL;
      $this->response->addHeader('HTTP/1.1 500 Internal Server Error');
      $this->response->setBody($body);
      $this->response->exec();
    }
  }
}
