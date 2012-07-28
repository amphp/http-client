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
    DomainException,
    InvalidArgumentException;

/**
 * A value object storing configuration directives
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */    
class Config {
    
    /**
     * @var array
     */
    protected $directives = array();
    
    /**
     * Populate the object using a key-value iterable
     * 
     * @param mixed $iterable An array, StdClass or Traversable
     * @return void
     */
    public function populate($iterable) {
        if (!($iterable instanceof Traversable
            || $iterable instanceof StdClass
            || is_array($iterable)
        )) {
            $type = is_object($iterable) ? get_class($iterable) : gettype($iterable);
            throw new InvalidArgumentException(
                get_class($this) . '::populate expects an array, StdClass or '.
                "Traversable object at Argument 1: $type specified"
            );
        }
        
        foreach ($iterable as $key => $value) {
            $setterMethod = 'set' . ucfirst($key);
            if (method_exists($this, $setterMethod)) {
                $this->$setterMethod($value);
            } else {
                $this->directives[$key] = $value;
            }
        }
    }
    
    /**
     * Is the specified directive loaded in the configuration?
     * 
     * @param string $directive
     * @return bool
     */
    public function has($directive) {
        return isset($this->directives[$directive]);
    }
    
    /**
     * Retrieves the value of the specified configuration directive
     * 
     * @param string $directive
     * @return mixed
     * @throws DomainException
     */
    public function get($directive) {
        if (!$this->has($directive)) {
            throw new DomainException;
        } else {
            return $this->directives[$directive];
        }
    }
}
