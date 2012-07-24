<?php
/**
 * ProvisionedNotifier Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Events
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Events;

use InvalidArgumentException,
    ArrayAccess,
    Traversable,
    StdClass,
    Artax\Events\Notifier,
    Artax\Injection\Injector,
    Artax\Injection\ProviderDefinitionException;

/**
 * Extends base notifier allowing lazy class listener instantiation
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Events
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ProvisionedNotifier extends Notifier {
    
    /**
     * @var Injector
     */
    private $injector;
    
    /**
     * @param Injector $injector
     * @return void
     */
    public function __construct(Injector $injector) {
        $this->injector = $injector;
    }
    
    /**
     * Allows lazily-instantiated class name listeners
     * 
     * @param mixed $listener
     * @return bool
     */
    protected function isValidListener($listener) {
        return is_string($listener) || is_callable($listener);
    }
    
    /**
     * @param string $eventName
     * @param int $queuePos
     * @return mixed Returns a callable event listener
     * @throws BadListenerException
     */
    protected function getCallableListenerFromQueue($eventName, $queuePos) {
        $rawListener = $this->listeners[$eventName][$queuePos];
        
        if (!is_string($rawListener)) {
            return $rawListener;
        }
        
        try {
            $listener = $this->injector->make($rawListener);
        } catch (ProviderDefinitionException $e) {
            throw new BadListenerException(
                "Invalid class listener ($rawListener) specified in the `$eventName` " .
                "queue at position $queuePos. Provisioned auto-instantiation failed.",
                null,
                $e
            );
        }
        
        if (!is_callable($listener)) {
            throw new BadListenerException(
                "Invalid listener specified in the `$eventName` queue at position " .
                "$queuePos: object of type ".get_class($listener).' is not callable'
            );
        }
        
        return $listener;
    }
}
