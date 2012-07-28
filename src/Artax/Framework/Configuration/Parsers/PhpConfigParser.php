<?php
/**
 * PhpConfigParser Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Configuration\Parsers;

use StdClass,
    Traversable,
    Artax\Framework\Configuration\ConfigException;

/**
 * Parses configuration from a PHP file's traversable $cfg variable
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class PhpConfigParser implements ConfigParser {
    
    /**
     * @param string $configFilepath
     * @return mixed
     * @throws ConfigException
     */
    public function parse($configFilepath) {
        if (false === @include $configFilepath) {
            throw new ConfigException("Failed loading config file: $configFilepath");
        }
        
        if (!isset($cfg)) {
            throw new ConfigException(
                'Config file must specify a $cfg array, StdClass or Traversable storing ' .
                "configuration directives; none found in $configFilepath"
            );
        }
        
        if (!(is_array($cfg) || $cfg instanceof StdClass || $cfg instanceof Traversable)) {
            $cfgType = is_object($cfg) ? get_class($cfg) : gettype($cfg);
            throw new ConfigException(
                "\$cfg must be an array, StdClass or Traversable; $cfgType specified"
            );
        }
        
        return $cfg;
    }
}
