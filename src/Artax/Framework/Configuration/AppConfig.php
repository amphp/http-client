<?php
/**
 * Application Config Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace Artax\Framework\Configuration;

use InvalidArgumentException;

/**
 * An immutable value object storing application configuration directives
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */    
class AppConfig extends Config {
    
    public function __construct() {
        $this->directives['routes'] = array();
        $this->directives['plugins'] = array();
    }
    
    public function populate($iterable) {
        if (!$this->isTraversableMap($iterable)) {
            throw new ConfigException(
                'Invalid configuration iterable: ' . get_class($this) . '::populate requires an ' .
                'array, StdClass or Traversable at Argument 1'
            );
        }
        
        foreach ($iterable as $directive => $value) {
            $setterMethod = 'set' . ucfirst($directive);
            if (method_exists($this, $setterMethod)) {
                $this->$setterMethod($value);
            } else {
                $this->assignCustomDirective($directive, $value);
            }
        }
    }
    
    protected function assignCustomDirective($directive, $value) {
        if (is_scalar($value)) {
            $this->directives[$directive] = $value;
        } else {
            $directiveType = is_object($value) ? get_class($value) : gettype($value);
            throw new ConfigException(
                "Invalid config directive: $directive requires a scalar value"
            );
        }
    }
    
    protected function setPlugins($iterable) {
        if (!$this->isTraversableMap($iterable)) {
            throw new ConfigException(
                'Invalid config directive: plugins requires an array, StdClass or Traversable'
            );
        }
        
        $plugins = array();
        foreach ($iterable as $plugin => $enabled) {
            $plugins[$plugin] = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        }
        
        $this->directives['plugins'] = $plugins;
    }
    
    protected function setRoutes($iterable) {
        if (!$this->isTraversableMap($iterable)) {
            throw new ConfigException(
                'Invalid config directive: routes requires an array, StdClass or Traversable'
            );
        }
        
        $this->directives['routes'] = $iterable;
    }
}
