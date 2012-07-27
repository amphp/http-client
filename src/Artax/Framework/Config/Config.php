<?php

/**
 * Application Config Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Config
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace Artax\Framework\Config;

use StdClass,
    Traversable,
    DomainException,
    InvalidArgumentException;

/**
 * A value object storing configuration directives
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Config
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */    
class Config {
    
    /**
     * @var ConfigValidator
     */
    private $validator;
    
    /**
     * @var array
     */
    private $directives = array();
    
    /**
     * @var array
     */
    private $defaults = array(
        'applyRouteShortcuts' => true,
        'autoResponseStatus' => true,
        'autoResponseDate' => true,
        'autoResponseContentLength' => true,
        'autoResponseEncode' => false,
        'autoResponseEncodeMediaRanges' => array('text/*', 'application/json', 'application/xml')
    );
    
    /**
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
    
    /**
     * @param string $directive
     * @return bool
     */
    public function has($directive) {
        return isset($this->directives[$directive]);
    }
    
    /**
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
        
        $this->setUndefinedDefaults();
    }
    
    private function setApplyRouteShortcuts($bool) {
        $normalized = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
        $this->directives['applyRouteShortcuts'] = $normalized;
    }
    
    private function setAutoResponseStatus($bool) {
        $normalized = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
        $this->directives['autoResponseStatus'] = $normalized;
    }
    
    private function setAutoResponseDate($bool) {
        $normalized = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
        $this->directives['autoResponseDate'] = $normalized;
    }
    
    private function setAutoResponseContentLength($bool) {
        $normalized = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
        $this->directives['autoResponseContentLength'] = $normalized;
    }
    
    private function setAutoResponseEncode($bool) {
        $normalized = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
        $this->directives['autoResponseEncode'] = $normalized;
    }
    
    private function setAutoResponseEncodeMediaRanges($mediaRangeArray) {
        if (!$mediaRangeArray) {
            return;
        }
        $normalized = array_map('trim', $mediaRangeArray);
        $this->directives['autoResponseEncodeMediaRanges'] = $normalized;
    }
    
    private function setUndefinedDefaults() {
        foreach ($this->defaults as $defaultKey => $defaultValue) {
            if (!$this->has($defaultKey)) {
                $this->directives[$defaultKey] = $defaultValue;
            }
        }
    }
}
