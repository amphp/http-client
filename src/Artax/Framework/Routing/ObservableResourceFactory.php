<?php
/**
 * ObservableResourceFactory Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Framework\Routing;

use Artax\Events\Mediator;

/**
 * Generates callable, observable resources
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ObservableResourceFactory {
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @param Mediator $mediator
     * @return void
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * @param mixed $callableResource
     * @param array $invocationArgs
     * @return mixed
     */
    public function make(callable $callableResource, array $invocationArgs) {
        return new ObservableResource($this->mediator, $callableResource, $invocationArgs);
    }
}
