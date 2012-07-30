<?php
/**
 * PluginManifest Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace Artax\Framework\Configuration;

/**
 * A value object storing plugin manifest configuration directives
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */    
class PluginManifest extends Config {
    
    public function __construct() {
        $this->directives['name'] = '';
        $this->directives['description'] = '';
        $this->directives['version'] = 0;
        $this->directives['minSystemVersion'] = 0;
        $this->directives['pluginDependencies'] = array();
    }
    
    public function populate($iterable) {
        if (!$this->isTraversableMap($iterable)) {
            throw new ConfigException(
                'Invalid manifest iterable: ' . get_class($this) . '::populate requires an ' .
                'array, StdClass or Traversable at Argument 1'
            );
        }
        
        foreach ($iterable as $key => $value) {
            $setterMethod = 'set' . ucfirst($key);
            if (method_exists($this, $setterMethod)) {
                $this->$setterMethod($value);
            }
        }
        
        $this->validate();
    }
    
    public function __toString() {
        return $this->directives['name'];
    }
    
    protected function validate() {
        if (!$this->get('name')) {
            throw new ConfigException(
                'Invalid plugin manifest: name directive is required'
            );
        }
    }
    
    protected function setName($name) {
        if (!is_string($name)) {
            throw new ConfigException(
                'Invalid plugin manifest: name requires a non-empty string'
            );
        }
        
        $this->directives['name'] = $name;
    }
    
    protected function setDescription($description) {
        if (!is_string($description)) {
            throw new ConfigException(
                'Invalid plugin manifest: description requires a string value'
            );
        }
        
        $this->directives['description'] = $description;
    }
    
    protected function setVersion($version) {
        if (!(is_float($version) || is_int($version)) || $version < 0) {
            throw new ConfigException(
                'Invalid plugin manifest: version requires a non-negative integer or float'
            );
        }
        
        $this->directives['version'] = $version;
    }
    
    protected function setMinSystemVersion($minSystemVersion) {
        if (!(is_float($minSystemVersion) || is_int($minSystemVersion)) || $minSystemVersion < 0) {
            throw new ConfigException(
                'Invalid plugin manifest: minSystemVersion requires a non-negative integer or float'
            );
        }
        
        $this->directives['minSystemVersion'] = $minSystemVersion;
    }
    
    protected function setPluginDependencies($dependencies) {
        if (!$this->isTraversable($dependencies)) {
            throw new ConfigException(
                'Invalid plugin manifest: pluginDependencies requires an array or Traversable'
            );
        }
        
        $this->directives['pluginDependencies'] = $dependencies;
    }
}
