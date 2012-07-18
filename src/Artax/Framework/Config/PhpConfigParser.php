<?php
/**
 * 
 */
namespace Artax\Framework\Config;

use StdClass,
    Traversable,
    SplFileInfo;

/**
 * Generates an iterable set of values for Config injection
 */
class PhpConfigParser {
    
    /**
     * @param string $configFilePath
     * @return void
     */
    public function __construct($configFilePath) {
        $this->configFilePath = $configFilePath;
    }
    
    /**
     * @return mixed
     * @throws ConfigException
     */
    public function parse() {
        require $this->configFilePath;
        
        if (isset($cfg)
            && (is_array($cfg) || $cfg instanceof StdClass || $cfg instanceof Traversable)
        ) {
            return $cfg;
        }
        throw new ConfigException(
            'Config file must specify an array, StdClass or Traversable $cfg variable'
        );
    }
}
