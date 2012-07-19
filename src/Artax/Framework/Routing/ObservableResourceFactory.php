<?php
/**
 * ObservableResourceFactory Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Routing;

use InvalidArgumentException,
    Artax\Events\Mediator;

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
     * @throws InvalidArgumentException
     */
    public function make($callableResource, array $invocationArgs) {
        if (!is_callable($callableResource)) {
            throw new InvalidArgumentException(
                get_class($this) . "::make requires a callable parameter at Argument 1"
            );
        }
        return new ObservableResource($this->mediator, $callableResource, $invocationArgs);
    }
}
