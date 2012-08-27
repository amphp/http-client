<?php
/**
 * ObservableResponse Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Http;

use Artax\Events\Mediator,
    Artax\Http\StdResponse;

/**
 * An observable HTTP response
 * 
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableResponse extends StdResponse {
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @param Mediator
     * @notifies sys.response.new(StdResponse $response)
     */
    public function __construct(Mediator $mediator) {
        parent::__construct();
        
        $this->mediator = $mediator;
        $this->notify('__sys.response.new');
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    protected function notify($eventName) {
        $this->mediator->notify($eventName, $this);
    }
    
    /**
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        parent::setHttpVersion($httpVersion);
        $this->notify('__sys.response.setHttpVersion');
    }

    /**
     * @param int $httpStatusCode
     * @return void
     */
    public function setStatusCode($httpStatusCode) {
        parent::setStatusCode($httpStatusCode);
        $this->notify('__sys.response.setStatusCode');
    }

    /**
     * @param string $httpStatusDescription
     * @return void
     */
    public function setStatusDescription($httpStatusDescription) {
        parent::setStatusDescription($httpStatusDescription);
        $this->notify('__sys.response.setStatusDescription');
    }

    /**
     * @param string $headerName
     * @param string $value
     * @return void
     */
    public function setHeader($headerName, $value) {
        parent::setHeader($headerName, $value);
        $this->notify('__sys.response.setHeader');
    }

    /**
     * @param string $headerName
     * @param string $value
     * @return void
     */
    public function removeHeader($headerName) {
        parent::removeHeader($headerName);
        $this->notify('__sys.response.removeHeader');
    }

    /**
     * @param mixed $body
     * @return void
     * @notifies sys.response.set-body(StdResponse $response)
     */
    public function setBody($body) {
        parent::setBody($body);
        $this->notify('__sys.response.setBody');
    }

    /**
     * @return void
     * @notifies sys.response.beforeSend
     * @notifies sys.response.afterSend
     */
    public function send() {
        $this->notify('__sys.response.beforeSend');
        parent::send();
        $this->notify('__sys.response.afterSend');
    }
}
