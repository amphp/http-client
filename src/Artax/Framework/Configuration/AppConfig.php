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

use StdClass,
    Traversable;

/**
 * A value object storing application configuration directives
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */    
class AppConfig extends Config {
    
    /**
     * Populate the object using a key-value iterable
     * 
     * @param mixed $iterable An array, StdClass or Traversable
     * @return void
     * @throws InvalidArgumentException
     */
    public function populate($iterable) {
        parent::populate($iterable);
        
        if (!$this->has('routes') || !$this->get('routes')) {
            throw new ConfigException('No resource routes specified');
        }
    }
    
    /**
     * @return void
     */
    protected function setRoutes(array $routes) {
        $this->directives['routes'] = $routes;
    }
    
    /**
     * @return void
     */
    protected function setPlugins(array $plugins) {
        $this->directives['plugins'] = array_map(function($plugin) {
            return filter_var($plugin, FILTER_VALIDATE_BOOLEAN);
        }, $plugins);
    }
}
