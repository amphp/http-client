<?php
/**
 * ConfigValidator Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Config
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Config;

use Traversable;

/**
 * Validates Config value objects
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Config
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
class ConfigValidator {

    /**
     * @return void
     * @throws ConfigException
     */
    public function validate(Config $cfg) {
        $this->validateRoutes($cfg);
    }
    
    /**
     * @return void
     * @throws ConfigException
     */
    protected function validateRoutes(Config $cfg) {
        if (!$cfg->has('routes')) {
            throw new ConfigException('No resource routes specified');
        }
        $routes = $cfg->get('routes');
        if (!(is_array($routes) || $routes instanceof Traversable)) {
            throw new ConfigException('Routes must be an array or Traversable object');
        }
    }
}
