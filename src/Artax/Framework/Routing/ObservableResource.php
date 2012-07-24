<?php
/**
 * ObservableResource Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Routing;

use Artax\Events\Mediator;

/**
 * Wraps and makes routed resources observable
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableResource {
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @var mixed
     */
    private $callableResource;
    
    /**
     * @var array
     */
    private $invocationArgs;
    
    /**
     * @var mixed
     */
    private $invocationResult;
    
    /**
     * @param Mediator $mediator
     * @param callable $callableResource
     * @param array $args
     * @return void
     */
    public function __construct(
        Mediator $mediator,
        $callableResource,
        array $invocationArgs
    ) {
        $this->mediator = $mediator;
        $this->callableResource = $callableResource;
        $this->invocationArgs = $invocationArgs;
        
        $this->notify('__sys.resource.new');
    }
    
    /**
     * @return mixed
     * @notifies sys.resource.return(mixed $returnValue)
     */
    public function __invoke() {
        $this->notify('__sys.resource.beforeInvocation');
        
        $this->invocationResult = $this->invocationArgs
            ? call_user_func_array($this->callableResource, $this->invocationArgs)
            : call_user_func($this->callableResource);
        
        $this->notify('__sys.resource.afterInvocation');
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    private function notify($eventName) {
        $this->mediator->notify($eventName, $this);
    }
    
    /**
     * @return mixed Returns a callable resource/method in the array callback construction
     */
    public function getCallableResource() {
        return $this->callableResource;
    }
    
    /**
     * @return array
     */
    public function getInvocationArgs() {
        return $this->invocationArgs;
    }
    
    /**
     * @return mixed
     */
    public function getInvocationResult() {
        return $this->invocationResult;
    }
}
