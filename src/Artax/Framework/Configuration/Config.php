<?php
/**
 * Config Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Configuration;

use StdClass,
    Traversable,
    DomainException;

/**
 * An abstract value object extended to store immutable configuration directives
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */    
abstract class Config {
    
    protected $directives = array(
        'requiredFiles'            => array(),
        'eventListeners'           => array(),
        'injectionDefinitions'     => array(),
        'injectionImplementations' => array(),
        'sharedClasses'            => array()
    );
    
    /**
     * Populate the object using a key-value iterable
     * 
     * @param mixed $iterable An array, StdClass or Traversable
     * @return void
     */
    abstract public function populate($iterable);
    
    /**
     * Determines if the specified directive is loaded
     * 
     * @param string $directive
     * @return bool
     */
    public function has($directive) {
        return isset($this->directives[$directive])
            || array_key_exists($directive, $this->directives);
    }
    
    /**
     * Retrieves the value of the specified directive
     * 
     * @param string $directive
     * @return mixed
     * @throws DomainException
     */
    public function get($directive) {
        if ($this->has($directive)) {
            return $this->directives[$directive];
        } else {
            throw new DomainException(
                "Invalid config directive: $directive does not exist"
            );
        }
    }
    
    protected function isTraversable($iterable) {
        return (is_array($iterable) || $iterable instanceof Traversable);
    }
    
    protected function isTraversableMap($iterable) {
        return ($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable
        );
    }
    
    protected function setRequiredFiles($iterable) {
        if ($this->isTraversable($iterable)) {
            $this->directives['requiredFiles'] = $iterable;
        } else {
            throw new ConfigException(
                'Invalid config directive: requiredFiles requires an array or Traversable'
            );
        }
    }
    
    protected function setEventListeners($iterable) {
        if ($this->isTraversableMap($iterable)) {
            $this->directives['eventListeners'] = $iterable;
        } else {
            throw new ConfigException(
                'Invalid config directive: eventListeners requires an array, StdClass ' .
                'or Traversable'
            );
        }
    }
    
    protected function setInjectionDefinitions($iterable) {
        if ($this->isTraversableMap($iterable)) {
            $this->directives['injectionDefinitions'] = $iterable;
        } else {
            throw new ConfigException(
                'Invalid config directive: injectionDefinitions requires an array, StdClass ' .
                'or Traversable'
            );
        }
    }
    
    protected function setInjectionImplementations($iterable) {
        if ($this->isTraversableMap($iterable)) {
            $this->directives['injectionImplementations'] = $iterable;
        } else {
            throw new ConfigException(
                'Invalid config directive: injectionImplementations requires an array, StdClass ' .
                'or Traversable'
            );
        }
    }
    
    protected function setSharedClasses($iterable) {
        if ($this->isTraversable($iterable)) {
            $this->directives['sharedClasses'] = $iterable;
        } else {
            throw new ConfigException(
                'Invalid config directive: sharedClasses requires an array or Traversable'
            );
        }
    }
}
