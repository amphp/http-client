<?php
/**
 * ConfigValidator Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Config
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
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
 */
class ConfigValidator {

    /**
     * @return void
     * @throws ConfigException
     */
    private function validate(Config $cfg) {
        if (!$cfg->has('routes')) {
            throw new ConfigException('No routes specified');
        }
        $routes = $cfg->get('routes');
        if (!(is_array($routes) || $routes instanceof Traversable)) {
            throw new ConfigException('Routes must be an array or Traversable object');
        }
    }
}
